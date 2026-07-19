import re
from collections.abc import Mapping, Sequence
from datetime import datetime
from urllib.parse import urljoin
from zoneinfo import ZoneInfo

from playwright.async_api import Locator, Page, expect

from mei.models import JobRecord, JobStatus
from mei.operations.artifact_validation import (
    ArtifactPublisher,
    InvalidArtifactError,
    barcode_from_pdf,
    normalize_barcode,
)
from mei.operations.base import HandlerOutcome, OperationContext, failed
from mei.operations.captcha import (
    CaptchaResolution,
    IdentificationStatus,
    captcha_solver,
    submit_identification_once,
)
from mei.operations.schemas import (
    ActiveDebtResult,
    ActiveDebtYear,
    DasArtifactResult,
    PgmeiActiveDebtInput,
    PgmeiGenerateDasInput,
)
from mei.operations.semantic_html import body_attribute, semantic_rows

PARSER_VERSION = "pgmei-1"


class PgmeiParser:
    def competencies(self, html: str) -> set[str]:
        rows = semantic_rows(html, "Competencias disponiveis")
        values = [
            row.attributes.get("data-competencia", "")
            or (row.cells[1] if len(row.cells) > 1 else "")
            for row in rows
        ]
        return self.competency_values(values)

    def competency_values(self, values: Sequence[str]) -> set[str]:
        competencies: set[str] = set()
        for raw_value in values:
            value = " ".join(raw_value.split())
            compact = "".join(character for character in value if character.isdigit())
            if re.fullmatch(r"20\d{4}", compact):
                year, month = compact[:4], compact[4:]
            else:
                match = re.search(r"\b(0[1-9]|1[0-2])/(20\d{2})\b", value)
                if match is None:
                    continue
                month, year = match.group(1), match.group(2)
            if month in {f"{number:02d}" for number in range(1, 13)}:
                competencies.add(f"{year}-{month}")
        return competencies

    def active_debt(self, html: str, calendar_year: int | None = None) -> ActiveDebtResult:
        rows = semantic_rows(html, "Debitos por ano")
        years: list[ActiveDebtYear] = []
        for row in rows:
            year_raw = row.attributes.get("data-ano", "")
            if not year_raw.isdigit() or len(row.cells) < 3:
                continue
            year = int(year_raw)
            if calendar_year is not None and year != calendar_year:
                continue
            count_raw = "".join(character for character in row.cells[2] if character.isdigit())
            if count_raw == "":
                continue
            years.append(ActiveDebtYear(year=year, status=row.cells[1], debt_count=int(count_raw)))
        if not years:
            raise ValueError("Tabela de divida ativa sem linhas reconhecidas")
        return ActiveDebtResult(
            years=years,
            parser_version=PARSER_VERSION,
            portal_version=body_attribute(html, "data-portal-version"),
        )

    def active_debt_items(
        self,
        items: Sequence[Mapping[str, object]],
        calendar_year: int | None = None,
        portal_version: str = "unknown",
    ) -> ActiveDebtResult:
        years: list[ActiveDebtYear] = []
        for item in items:
            year_match = re.search(r"\b(20\d{2})\b", str(item.get("year", "")))
            if year_match is None:
                continue
            year = int(year_match.group(1))
            if calendar_year is not None and year != calendar_year:
                continue
            status = " ".join(str(item.get("status", "")).split())[:80]
            count_match = re.search(r"\d+", str(item.get("debt_count", "")))
            if not status or count_match is None:
                continue
            years.append(
                ActiveDebtYear(
                    year=year,
                    status=status,
                    debt_count=int(count_match.group()),
                )
            )
        if not years:
            raise ValueError("Pagina de divida ativa sem linhas reconhecidas")
        return ActiveDebtResult(
            years=years,
            parser_version=PARSER_VERSION,
            portal_version=portal_version,
        )

    def barcode(self, html: str) -> str:
        rows = semantic_rows(html, "Codigo de barras DAS")
        if not rows or len(rows[0].cells) < 2:
            raise ValueError("Codigo de barras nao encontrado")
        return normalize_barcode(rows[0].cells[1])


class PgmeiHandler:
    def __init__(self) -> None:
        self._parser = PgmeiParser()
        self._captcha = CaptchaResolution(solved=False)

    async def execute(
        self, page: Page, record: JobRecord, context: OperationContext
    ) -> HandlerOutcome:
        self._captcha = CaptchaResolution(solved=False)
        if record.operation_key == "pgmei.dividaativa":
            debt_payload = PgmeiActiveDebtInput.model_validate(record.input)
            outcome = await self._active_debt(page, record, context, debt_payload)
        else:
            das_payload = PgmeiGenerateDasInput.model_validate(record.input)
            outcome = await self._generate_das(page, record, context, das_payload)

        outcome.captcha_driver = self._captcha.driver
        outcome.captcha_cost_micros = self._captcha.cost_micros
        return outcome

    async def _generate_das(
        self,
        page: Page,
        record: JobRecord,
        context: OperationContext,
        payload: PgmeiGenerateDasInput,
    ) -> HandlerOutcome:
        checkpoint = await self._identify(page, context, payload.cnpj, record.operation_key)
        if checkpoint is not None:
            return checkpoint

        submitted = False
        try:
            if context.settings.fixture_enabled:
                await self._fixture(page, context, "pgmei/competencias.html")
                html = await page.content()
                available = self._parser.competencies(html)
            else:
                years = {competence[:4] for competence in payload.competencies}
                if len(years) != 1:
                    return failed(
                        "PORTAL_COMPETENCE_RANGE_UNSUPPORTED",
                        "O PGMEI exige emissao separada para cada ano-calendario.",
                    )
                available = await self._open_emission(page, years.pop())
                html = await page.content()

            missing = [item for item in payload.competencies if item not in available]
            if missing:
                return failed(
                    "PORTAL_COMPETENCE_UNAVAILABLE",
                    "Uma ou mais competencias nao estao disponiveis no portal.",
                )

            for competence in payload.competencies:
                if context.settings.fixture_enabled:
                    locator = page.locator(
                        f'input[name="competencias"][value="{competence}"]'
                    ).first
                else:
                    locator = self._competence_locator(page, competence)
                await locator.check()

            due_date = payload.due_date or datetime.now(ZoneInfo("America/Sao_Paulo")).date()
            if not context.settings.fixture_enabled:
                await self._update_values(page, due_date.isoformat())

            publisher = ArtifactPublisher(
                context.artifact_store, context.settings.artifact_max_bytes
            )
            portal_version = body_attribute(html, "data-portal-version")
            if not context.settings.fixture_enabled:
                submitted = True
                await page.locator("#btnEmitirDas").click()
                await page.locator('a[href*="/emissao/imprimir" i]').first.wait_for()
                content = await self._download_emitted_pdf(page, context)

            if record.operation_key == "pgmei.gerardaspdf":
                if context.settings.fixture_enabled:
                    content = fixture_pdf("DAS MEI fixture sanitizada")
                artifact = publisher.pdf(record.id, "das-mei.pdf", content)
                result = DasArtifactResult(
                    competencies=payload.competencies,
                    due_date=due_date,
                    submitted=submitted,
                    parser_version=PARSER_VERSION,
                    portal_version=portal_version,
                )
            else:
                if context.settings.fixture_enabled:
                    await self._fixture(page, context, "pgmei/codigo-barras.html")
                    barcode = self._parser.barcode(await page.content())
                else:
                    barcode = barcode_from_pdf(content, context.settings.artifact_max_bytes)
                artifact = publisher.text(record.id, "codigo-barras-das.txt", barcode)
                result = DasArtifactResult(
                    competencies=payload.competencies,
                    due_date=due_date,
                    submitted=submitted,
                    parser_version=PARSER_VERSION,
                    portal_version=portal_version,
                    barcode=barcode,
                )

            return HandlerOutcome(
                status=JobStatus.SUCCEEDED,
                result=result.model_dump(mode="json"),
                artifacts=[artifact],
            )
        except InvalidArtifactError:
            return failed(
                "PORTAL_ARTIFACT_INVALID",
                "O artefato entregue pelo portal e invalido.",
                submitted=submitted,
            )
        except Exception:  # noqa: BLE001 - erro live e classificado sem detalhe sensivel
            return failed(
                "PORTAL_DRIFT",
                "O fluxo PGMEI nao corresponde aos checkpoints suportados.",
                submitted=submitted,
                retryable=not submitted,
            )

    async def _open_emission(self, page: Page, calendar_year: str) -> set[str]:
        issuance_link = page.locator('a[href*="/pgmei.app/emissao" i]').first
        await issuance_link.wait_for()
        await issuance_link.click()

        year_select = page.locator("#anoCalendarioSelect")
        await year_select.wait_for()
        available_years = await year_select.locator("option").evaluate_all(
            "options => options.map(option => option.value)"
        )
        if calendar_year not in available_years:
            return set()
        await year_select.select_option(calendar_year)
        form = year_select.locator("xpath=ancestor::form[1]")
        await form.locator('button[type="submit"], input[type="submit"]').first.click()
        await page.locator("#btnEmitirDas").wait_for()

        values = await page.locator(
            'input[type="checkbox"][value], input[type="radio"][value]'
        ).evaluate_all("inputs => inputs.map(input => input.value)")
        return self._parser.competency_values(
            [str(value) for value in values if isinstance(value, str)]
        )

    @staticmethod
    def _competence_locator(page: Page, competence: str) -> Locator:
        compact = competence.replace("-", "")
        return page.locator(
            f'input[type="checkbox"][value="{compact}"], '
            f'input[type="radio"][value="{compact}"], '
            f'input[value="{competence}"]'
        ).first

    async def _update_values(self, page: Page, due_date: str) -> None:
        payment_date = page.locator("#dataPagamentoInformada").first
        update_button = page.locator("#btnAtualizarValores").first
        emit_button = page.locator("#btnEmitirDas").first
        await payment_date.wait_for()
        await update_button.wait_for()
        await emit_button.wait_for()

        input_type = (await payment_date.get_attribute("type") or "text").casefold()
        if input_type == "date":
            formatted_due_date = due_date
        else:
            year, month, day = due_date.split("-")
            formatted_due_date = f"{day}/{month}/{year}"
        await payment_date.fill(formatted_due_date)
        await expect(payment_date).to_have_value(formatted_due_date)

        await update_button.click()
        await page.wait_for_load_state("networkidle")
        await expect(update_button).to_be_enabled()
        await expect(emit_button).to_be_enabled()

    async def _download_emitted_pdf(self, page: Page, context: OperationContext) -> bytes:
        print_link = page.locator('a[href*="/emissao/imprimir" i]').first
        href = await print_link.get_attribute("href")
        if not href:
            raise InvalidArtifactError("Link de impressao do DAS ausente")
        response = await page.request.get(
            urljoin(page.url, href),
            headers={"referer": page.url},
            timeout=context.settings.browser_timeout_ms,
            fail_on_status_code=False,
        )
        if not response.ok:
            raise InvalidArtifactError("Download do PDF DAS recusado pelo portal")
        return await response.body()

    async def _active_debt(
        self,
        page: Page,
        record: JobRecord,
        context: OperationContext,
        payload: PgmeiActiveDebtInput,
    ) -> HandlerOutcome:
        checkpoint = await self._identify(page, context, payload.cnpj, record.operation_key)
        if checkpoint is not None:
            return checkpoint
        try:
            if context.settings.fixture_enabled:
                await self._fixture(page, context, "pgmei/divida-ativa.html")
                result = self._parser.active_debt(await page.content(), payload.calendar_year)
            else:
                debt_action = page.get_by_role("link", name=re.compile("D[ií]vida ativa", re.I))
                if await debt_action.count() == 0:
                    debt_action = page.get_by_role(
                        "button", name=re.compile("D[ií]vida ativa", re.I)
                    )
                await debt_action.first.click()
                rows = await page.locator("table tbody tr").evaluate_all(
                    r"""
                    (elements) => elements.map((row) => {
                      const cells = Array.from(row.querySelectorAll('th, td'))
                        .map((cell) => (cell.textContent || '').replace(/\s+/g, ' ').trim())
                      return {
                        year: cells[0] || '',
                        status: cells[1] || '',
                        debt_count: cells[2] || ''
                      }
                    })
                    """
                )
                result = self._parser.active_debt_items(
                    [item for item in rows if isinstance(item, dict)],
                    payload.calendar_year,
                    body_attribute(await page.content(), "data-portal-version"),
                )
            return HandlerOutcome(
                status=JobStatus.SUCCEEDED,
                result=result.model_dump(mode="json"),
            )
        except Exception:  # noqa: BLE001 - parser fail-closed
            return failed(
                "PORTAL_DRIFT",
                "A pagina de divida ativa nao corresponde ao parser suportado.",
                retryable=True,
            )

    async def _identify(
        self, page: Page, context: OperationContext, cnpj: str, operation_key: str
    ) -> HandlerOutcome | None:
        if context.settings.fixture_enabled:
            await self._fixture(page, context, "pgmei/identificacao.html")
        else:
            await page.goto(
                context.settings.pgmei_identification_url,
                wait_until="domcontentloaded",
            )

        token = page.locator('input[name="__RequestVerificationToken"]')
        cnpj_input = page.locator("#cnpj")
        button = page.locator("#continuar")
        if await token.count() == 0 or await cnpj_input.count() == 0 or await button.count() == 0:
            return failed(
                "PORTAL_DRIFT",
                "A identificacao PGMEI nao corresponde a versao suportada.",
                retryable=True,
            )
        if any(character.isalpha() for character in cnpj):
            input_mode = (await cnpj_input.get_attribute("inputmode") or "").casefold()
            pattern = await cnpj_input.get_attribute("pattern") or ""
            if input_mode == "numeric" or "\\d" in pattern or "0-9" in pattern:
                return failed(
                    "PORTAL_CNPJ_FORMAT_UNSUPPORTED",
                    "O portal ainda restringe o identificador ao formato numerico.",
                )
        await cnpj_input.fill(cnpj)
        if context.settings.fixture_enabled:
            return None

        settings = context.settings
        identification = await submit_identification_once(
            page,
            button_selector="#continuar",
            success_selectors=[
                # Portal live: pos-identificacao aterra em Home/Inicio com o menu DAS.
                'a[href*="/pgmei.app/emissao" i]',
                'a[href*="/pgmei.app/Home/inicio" i]',
                'a[href*="/pgmei.app/home/sair" i]',
                'a:has-text("Emitir Guia de Pagamento")',
            ],
            validation_selectors=[
                "#identification-validation",
                ".validation-summary-errors",
                ".field-validation-error",
                ".alert-danger",
            ],
            solver=captcha_solver(settings, operation_key),
            timeout_ms=settings.browser_timeout_ms,
            presolve=(
                settings.captcha_driver.casefold() == "nopecha" and settings.nopecha_enabled
            ),
        )
        self._captcha = identification.captcha
        if identification.status is IdentificationStatus.CAPTCHA_EXHAUSTED:
            return failed(
                "CAPTCHA_EXHAUSTED",
                "O captcha do portal requer acao permitida nao disponivel.",
                retryable=True,
            )
        if identification.status is IdentificationStatus.VALIDATION_REJECTED:
            return failed(
                "PORTAL_DRIFT",
                "O portal rejeitou a identificacao informada.",
            )
        if identification.status is IdentificationStatus.PORTAL_DRIFT:
            return failed(
                "PORTAL_DRIFT",
                "A identificacao PGMEI nao atingiu um checkpoint suportado.",
                retryable=True,
            )
        return None

    async def _fixture(self, page: Page, context: OperationContext, relative_path: str) -> None:
        path = context.settings.fixture_root / relative_path
        await page.set_content(path.read_text(encoding="utf-8"))


def fixture_pdf(label: str) -> bytes:
    safe_label = label.replace("(", "[").replace(")", "]")
    return (
        "%PDF-1.4\n"
        "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
        "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 200]"
        "/Contents 4 0 R>>endobj\n"
        f"4 0 obj<</Length {len(safe_label) + 24}>>stream\n"
        f"BT /F1 12 Tf 20 100 Td ({safe_label}) Tj ET\n"
        "endstream endobj\n"
        "trailer<</Root 1 0 R>>\n%%EOF\n"
    ).encode("ascii")
