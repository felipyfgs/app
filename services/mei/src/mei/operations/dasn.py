import asyncio
import re
from collections.abc import Mapping, Sequence
from datetime import datetime
from pathlib import Path

from playwright.async_api import Page

from mei.models import JobRecord, JobStatus
from mei.operations.artifact_validation import ArtifactPublisher, InvalidArtifactError
from mei.operations.base import HandlerOutcome, OperationContext, failed
from mei.operations.captcha import (
    CaptchaResolution,
    IdentificationStatus,
    captcha_solver,
    submit_identification_once,
)
from mei.operations.pgmei import fixture_pdf
from mei.operations.schemas import (
    Coverage,
    DasnDeclarationSummary,
    DasnHistoryInput,
    DasnHistoryResult,
)
from mei.operations.semantic_html import body_attribute, semantic_rows

PARSER_VERSION = "dasnsimei-1"


class DasnSimeiParser:
    def history(self, html: str, calendar_year: int | None = None) -> DasnHistoryResult:
        rows = semantic_rows(html, "Historico DASN-SIMEI")
        declarations: list[DasnDeclarationSummary] = []
        for row in rows:
            year_raw = row.attributes.get("data-ano", "")
            if not year_raw.isdigit() or len(row.cells) < 4:
                continue
            year = int(year_raw)
            if calendar_year is not None and year != calendar_year:
                continue
            transmitted_at = None
            if row.cells[2]:
                transmitted_at = datetime.strptime(row.cells[2], "%d/%m/%Y").date()
            declarations.append(
                DasnDeclarationSummary(
                    calendar_year=year,
                    status=row.cells[1],
                    transmitted_at=transmitted_at,
                    pending="pendente" in row.cells[1].casefold()
                    or bool(re.search(r"n[aã]o apresentada", row.cells[1], re.I)),
                    coverage=Coverage.SUMMARY,
                    receipt_available=bool(row.links),
                )
            )
        if not declarations:
            raise ValueError("Historico DASN sem linhas reconhecidas")
        return DasnHistoryResult(
            declarations=declarations,
            coverage=Coverage.SUMMARY,
            parser_version=PARSER_VERSION,
            portal_version=body_attribute(html, "data-portal-version"),
        )

    def history_items(
        self,
        items: Sequence[Mapping[str, object]],
        calendar_year: int | None = None,
        portal_version: str = "unknown",
    ) -> DasnHistoryResult:
        declarations: list[DasnDeclarationSummary] = []
        for item in items:
            year_raw = str(item.get("calendar_year", "")).strip()
            if not year_raw.isdigit():
                continue
            year = int(year_raw)
            if calendar_year is not None and year != calendar_year:
                continue
            status = " ".join(str(item.get("status", "")).split())[:80]
            if not status:
                status = "Situacao nao informada"
            transmitted_at = None
            date_raw = str(item.get("transmitted_at", "")).strip()
            if date_raw:
                transmitted_at = datetime.strptime(date_raw, "%d/%m/%Y").date()
            special_situation_date = None
            special_date_raw = str(item.get("special_situation_date", "")).strip()
            if special_date_raw and special_date_raw != "-":
                special_situation_date = datetime.strptime(special_date_raw, "%d/%m/%Y").date()
            declaration_type = " ".join(str(item.get("declaration_type", "")).split())[:40]
            special_situation = " ".join(str(item.get("special_situation", "")).split())[:80]
            declarations.append(
                DasnDeclarationSummary(
                    calendar_year=year,
                    status=status,
                    transmitted_at=transmitted_at,
                    declaration_type=declaration_type or None,
                    special_situation=special_situation or None,
                    special_situation_date=special_situation_date,
                    pending=item.get("pending") is True,
                    coverage=Coverage.SUMMARY,
                    receipt_available=item.get("receipt_available") is True,
                )
            )
        if not declarations:
            raise ValueError("Historico DASN sem anos reconhecidos")
        return DasnHistoryResult(
            declarations=declarations,
            coverage=Coverage.SUMMARY,
            parser_version=PARSER_VERSION,
            portal_version=portal_version,
        )


class DasnSimeiHandler:
    def __init__(self) -> None:
        self._parser = DasnSimeiParser()
        self._captcha = CaptchaResolution(solved=False)

    async def execute(
        self, page: Page, record: JobRecord, context: OperationContext
    ) -> HandlerOutcome:
        self._captcha = CaptchaResolution(solved=False)
        payload = DasnHistoryInput.model_validate(record.input)
        checkpoint = await self._identify(page, context, payload.cnpj, record.operation_key)
        if checkpoint is not None:
            checkpoint.captcha_driver = self._captcha.driver
            checkpoint.captcha_cost_micros = self._captcha.cost_micros
            return checkpoint
        try:
            if context.settings.fixture_enabled:
                await self._fixture(page, context, "dasnsimei/historico.html")
                result = self._parser.history(await page.content(), payload.calendar_year)
            else:
                await page.locator("#iniciar-ano-calendario").wait_for()
                items = await self._history_items(page)
                result = self._parser.history_items(
                    items,
                    payload.calendar_year,
                    body_attribute(await page.content(), "data-portal-version"),
                )
            artifacts = []
            if payload.include_full_receipt:
                publisher = ArtifactPublisher(
                    context.artifact_store, context.settings.artifact_max_bytes
                )
                for declaration in result.declarations:
                    if not declaration.receipt_available:
                        continue
                    if context.settings.fixture_enabled:
                        content = fixture_pdf("Recibo DASN-SIMEI fixture sanitizado")
                    else:
                        link = page.get_by_role(
                            "link", name=re.compile(f"Recibo.*{declaration.calendar_year}", re.I)
                        )
                        async with page.expect_download() as download_info:
                            await link.click()
                        download = await download_info.value
                        path = await download.path()
                        if path is None:
                            continue
                        content = await asyncio.to_thread(Path(path).read_bytes)
                    artifact = publisher.pdf(
                        record.id,
                        f"recibo-dasn-{declaration.calendar_year}.pdf",
                        content,
                    )
                    artifacts.append(artifact)
                    declaration.coverage = Coverage.FULL
                    declaration.receipt_artifact_id = str(artifact.id)
                if result.declarations and all(
                    declaration.coverage is Coverage.FULL for declaration in result.declarations
                ):
                    result.coverage = Coverage.FULL

            outcome = HandlerOutcome(
                status=JobStatus.SUCCEEDED,
                result=result.model_dump(mode="json"),
                artifacts=artifacts,
            )
            outcome.captcha_driver = self._captcha.driver
            outcome.captcha_cost_micros = self._captcha.cost_micros
            return outcome
        except InvalidArtifactError:
            outcome = failed(
                "PORTAL_ARTIFACT_INVALID",
                "O recibo entregue pelo portal e invalido.",
            )
            outcome.captcha_driver = self._captcha.driver
            outcome.captcha_cost_micros = self._captcha.cost_micros
            return outcome
        except Exception:  # noqa: BLE001 - parser fail-closed
            outcome = failed(
                "PORTAL_DRIFT",
                "O historico DASN-SIMEI nao corresponde ao parser suportado.",
                retryable=True,
            )
            outcome.captcha_driver = self._captcha.driver
            outcome.captcha_cost_micros = self._captcha.cost_micros
            return outcome

    async def _identify(
        self, page: Page, context: OperationContext, cnpj: str, operation_key: str
    ) -> HandlerOutcome | None:
        if context.settings.fixture_enabled:
            await self._fixture(page, context, "dasnsimei/identificacao.html")
        else:
            await page.goto(context.settings.dasn_identification_url, wait_until="domcontentloaded")

        token = page.locator('input[name="__RequestVerificationToken"]')
        cnpj_input = page.locator("#identificacao-cnpj")
        button = page.locator("#identificacao-continuar")
        if await token.count() == 0 or await cnpj_input.count() == 0 or await button.count() == 0:
            return failed(
                "PORTAL_DRIFT",
                "A identificacao DASN-SIMEI nao corresponde a versao suportada.",
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
            button_selector="#identificacao-continuar",
            success_selectors=["#iniciar-ano-calendario"],
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
                "A identificacao DASN-SIMEI nao atingiu um checkpoint suportado.",
                retryable=True,
            )
        return None

    async def _history_items(self, page: Page) -> list[dict[str, object]]:
        items = await page.locator('#iniciar-ano-calendario input[type="radio"]').evaluate_all(
            r"""
            (radios) => radios.map((radio) => {
              const container = radio.parentElement
              const text = (container?.innerText || '').replace(/\s+/g, ' ').trim()
              const date = text.match(/\b\d{2}\/\d{2}\/\d{4}\b/)
              const spans = Array.from(container?.querySelectorAll('span') || [])
              const status = spans
                .filter((span) => !String(span.className || '').includes('br-tag'))
                .map((span) => (span.textContent || '').replace(/\s+/g, ' ').trim())
                .filter(Boolean)
                .join(' ')
              const action = container?.querySelector('.br-tag')?.textContent?.trim() || ''
              const receipt = container?.querySelector(
                'a[href*="recibo" i], a[download], button[data-action*="recibo" i]'
              )
              return {
                calendar_year: radio.value,
                status: status || action || text,
                transmitted_at: date ? date[0] : '',
                receipt_available: Boolean(receipt),
                declaration_type: radio.dataset.tipoDeclaracao || '',
                special_situation: radio.dataset.situacaoEspecialTipo || '',
                special_situation_date: radio.dataset.situacaoEspecialEventobaixa || '',
                pending: !radio.disabled && /n[aã]o apresentada/i.test(status || text)
              }
            })
            """
        )
        return [item for item in items if isinstance(item, dict)]

    async def _fixture(self, page: Page, context: OperationContext, relative_path: str) -> None:
        path = context.settings.fixture_root / relative_path
        await page.set_content(path.read_text(encoding="utf-8"))
