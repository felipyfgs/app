# mypy: disable-error-code="no-untyped-call"

from datetime import UTC, datetime, timedelta
from io import BytesIO
from pathlib import Path

import httpx
import pymupdf
import pytest
import zxingcpp  # type: ignore[import-not-found]
from PIL import Image
from playwright.async_api import Page, async_playwright

from mei.operations.artifact_validation import (
    InvalidArtifactError,
    barcode_from_pdf,
    normalize_barcode,
    validate_pdf,
)
from mei.operations.captcha import (
    CaptchaResolution,
    CaptchaState,
    IdentificationStatus,
    ManualCaptchaSolver,
    NoPechaCaptchaSolver,
    captcha_state,
    submit_identification_once,
)

FIXTURES = Path(__file__).parent / "fixtures"


def test_pdf_validation_rejects_html_and_truncated_content() -> None:
    with pytest.raises(InvalidArtifactError):
        validate_pdf(b"<html>erro</html>", 1024)
    with pytest.raises(InvalidArtifactError):
        validate_pdf(b"%PDF-1.4\nsem fechamento", 1024)


def test_barcode_remains_string_and_rejects_invalid_length() -> None:
    value = "85890000000 0 00000000000 0 00000000000 0 00000000000 0"

    normalized = normalize_barcode(value)
    assert normalized.startswith("8589")
    assert len(normalized) == 48
    assert isinstance(normalized, str)
    with pytest.raises(InvalidArtifactError):
        normalize_barcode("123")


def test_barcode_is_extracted_from_rendered_das_pdf() -> None:
    expected = "85890000000000000000000000000000000000000000"
    encoded = zxingcpp.create_barcode(expected, zxingcpp.BarcodeFormat.ITF)
    raster = zxingcpp.write_barcode_to_image(encoded, 1_000, 180)
    image = Image.fromarray(raster)
    image_buffer = BytesIO()
    image.save(image_buffer, format="PNG")

    document = pymupdf.open()
    page = document.new_page(width=700, height=220)
    page.insert_image(pymupdf.Rect(20, 20, 680, 200), stream=image_buffer.getvalue())
    content = document.tobytes()
    document.close()

    assert barcode_from_pdf(content, 1_048_576) == expected


async def test_captcha_solvers_are_fail_closed_without_explicit_transport() -> None:
    page = object()

    assert not (await ManualCaptchaSolver().solve(page)).valid()  # type: ignore[arg-type]
    assert not (
        await NoPechaCaptchaSolver(enabled=True, budget_micros=1, ttl_seconds=60).solve(
            page  # type: ignore[arg-type]
        )
    ).valid()


@pytest.mark.parametrize(
    ("html", "expected"),
    [
        ("<main>sem captcha</main>", CaptchaState.ABSENT),
        (
            '<div class="h-captcha" data-sitekey="public-fixture"></div>'
            '<textarea name="h-captcha-response" hidden></textarea>',
            CaptchaState.INTEGRATION_READY,
        ),
        (
            '<div class="h-captcha" data-sitekey="public-fixture">'
            '<iframe title="Widget containing checkbox for hCaptcha security challenge" '
            'style="width: 302px; height: 76px"></iframe></div>',
            CaptchaState.INTEGRATION_READY,
        ),
        (
            '<div class="h-captcha" data-sitekey="public-fixture"></div>'
            '<iframe title="Desafio hCaptcha" style="width: 300px; height: 200px"></iframe>',
            CaptchaState.CHALLENGE_ACTIVE,
        ),
        (
            '<div data-hcaptcha-error role="alert">Verifique o captcha.</div>',
            CaptchaState.CHALLENGE_ACTIVE,
        ),
    ],
)
async def test_captcha_state_distinguishes_integration_from_active_challenge(
    html: str, expected: CaptchaState
) -> None:
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.set_content(html)
        state = await captcha_state(page)
        await browser.close()

    assert state is expected


@pytest.mark.parametrize("portal", ["pgmei", "dasnsimei"])
@pytest.mark.parametrize(
    ("remote_outcome", "expected"),
    [
        ("auto-pass", IdentificationStatus.AUTO_APPROVED),
        ("challenge", IdentificationStatus.CAPTCHA_EXHAUSTED),
        ("captcha-rejection", IdentificationStatus.CAPTCHA_EXHAUSTED),
        ("validation", IdentificationStatus.VALIDATION_REJECTED),
        ("drift", IdentificationStatus.PORTAL_DRIFT),
    ],
)
async def test_identification_helper_classifies_outcome_after_one_submit(
    portal: str,
    remote_outcome: str,
    expected: IdentificationStatus,
) -> None:
    class CountingFailClosedSolver:
        def __init__(self) -> None:
            self.calls = 0

        async def solve(self, _page: Page) -> CaptchaResolution:
            self.calls += 1
            return CaptchaResolution(solved=False)

    fixture = (FIXTURES / portal / "identification-outcomes.html").as_uri()
    button = "#continuar" if portal == "pgmei" else "#identificacao-continuar"
    success = (
        ['a[href*="/pgmei.app/emissao" i]']
        if portal == "pgmei"
        else ["#iniciar-ano-calendario"]
    )
    solver = CountingFailClosedSolver()
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.goto(f"{fixture}?outcome={remote_outcome}")
        outcome = await submit_identification_once(
            page,
            button_selector=button,
            success_selectors=success,
            validation_selectors=["#identification-validation", ".alert-danger"],
            solver=solver,
            timeout_ms=150,
            poll_interval_ms=10,
        )
        submit_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    assert outcome.status is expected
    assert submit_count == 1
    # challenge e 13896 disparam exatamente uma tentativa de solver (fail-closed).
    assert solver.calls == (
        1 if remote_outcome in {"challenge", "captcha-rejection"} else 0
    )


async def test_identification_retries_once_after_13896_when_solver_injects_token() -> None:
    token = "captcha-token-" + ("y" * 96)

    class InjectingSolver:
        def __init__(self) -> None:
            self.calls = 0

        async def solve(self, page: Page) -> CaptchaResolution:
            self.calls += 1
            await page.evaluate(
                """
                (captchaToken) => {
                  const field = document.querySelector('[name="h-captcha-response"]')
                  field.value = captchaToken
                  field.dispatchEvent(new Event('change', { bubbles: true }))
                }
                """,
                token,
            )
            return CaptchaResolution(
                solved=True,
                expires_at=datetime.now(UTC) + timedelta(seconds=60),
                driver="fixture",
                cost_micros=1,
            )

    solver = InjectingSolver()
    fixture = (FIXTURES / "pgmei" / "identification-outcomes.html").as_uri()
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.goto(f"{fixture}?outcome=captcha-rejection")
        outcome = await submit_identification_once(
            page,
            button_selector="#continuar",
            success_selectors=['a[href*="/pgmei.app/emissao" i]'],
            validation_selectors=["#identification-validation"],
            solver=solver,
            timeout_ms=500,
            poll_interval_ms=10,
        )
        submit_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    assert outcome.succeeded
    assert outcome.captcha.driver == "fixture"
    assert solver.calls == 1
    assert submit_count == 2


async def test_identification_helper_calls_solver_once_and_waits_for_pending_success() -> None:
    class PendingSuccessSolver:
        def __init__(self) -> None:
            self.calls = 0

        async def solve(self, page: Page) -> CaptchaResolution:
            self.calls += 1
            await page.evaluate(
                """
                () => {
                  document.querySelector('[data-hcaptcha-challenge]').hidden = true
                  document.body.insertAdjacentHTML(
                    'afterbegin',
                    '<a href="/pgmei.app/emissao">Emitir DAS</a>'
                  )
                }
                """
            )
            return CaptchaResolution(
                solved=True,
                expires_at=datetime.now(UTC) + timedelta(seconds=60),
                driver="fixture",
            )

    solver = PendingSuccessSolver()
    fixture = (FIXTURES / "pgmei" / "identification-outcomes.html").as_uri()
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.goto(f"{fixture}?outcome=challenge")
        outcome = await submit_identification_once(
            page,
            button_selector="#continuar",
            success_selectors=['a[href*="/pgmei.app/emissao" i]'],
            validation_selectors=["#identification-validation"],
            solver=solver,
            timeout_ms=250,
            poll_interval_ms=10,
        )
        submit_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    assert outcome.succeeded
    assert outcome.captcha.driver == "fixture"
    assert solver.calls == 1
    assert submit_count == 1


@pytest.mark.parametrize("portal", ["pgmei", "dasnsimei"])
async def test_authorized_challenge_creates_one_external_job_without_sensitive_output(
    portal: str,
) -> None:
    captcha_token = "captcha-token-" + ("x" * 96)
    site_key = "public-fixture"
    cnpj = "11222333000181"
    requests: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        requests.append(request)
        if request.method == "POST":
            return httpx.Response(200, json={"data": "job-opaque"})
        return httpx.Response(200, json={"data": captcha_token})

    solver = NoPechaCaptchaSolver(
        enabled=True,
        budget_micros=500,
        ttl_seconds=60,
        api_key="secret-not-logged",
        operation_allowed=True,
        unit_cost_micros=100,
        timeout_seconds=10,
        poll_interval_seconds=0.25,
        transport=httpx.MockTransport(handler),
    )
    fixture = (FIXTURES / portal / "identification-outcomes.html").as_uri()
    button = "#continuar" if portal == "pgmei" else "#identificacao-continuar"
    cnpj_selector = "#cnpj" if portal == "pgmei" else "#identificacao-cnpj"
    success = (
        ['a[href*="/pgmei.app/emissao" i]']
        if portal == "pgmei"
        else ["#iniciar-ano-calendario"]
    )

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.goto(f"{fixture}?outcome=challenge")
        await page.locator(cnpj_selector).fill(cnpj)
        outcome = await submit_identification_once(
            page,
            button_selector=button,
            success_selectors=success,
            validation_selectors=["#identification-validation"],
            solver=solver,
            timeout_ms=500,
            poll_interval_ms=10,
        )
        submit_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    serialized = repr(outcome)
    assert outcome.succeeded
    assert submit_count == 1
    assert [request.method for request in requests] == ["POST", "GET"]
    assert captcha_token not in serialized
    assert site_key not in serialized
    assert cnpj not in serialized


async def test_nopecha_uses_one_job_and_injects_token_in_same_page() -> None:
    token = "captcha-token-" + ("x" * 96)
    requests: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        requests.append(request)
        if request.method == "POST":
            return httpx.Response(200, json={"data": "job-opaque"})
        return httpx.Response(200, json={"data": token})

    solver = NoPechaCaptchaSolver(
        enabled=True,
        budget_micros=500,
        ttl_seconds=60,
        api_key="secret-not-logged",
        operation_allowed=True,
        unit_cost_micros=100,
        timeout_seconds=10,
        poll_interval_seconds=0.25,
        transport=httpx.MockTransport(handler),
    )
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.set_content(
            '<div class="h-captcha" data-sitekey="site-key"></div>'
            '<textarea name="h-captcha-response"></textarea>'
        )
        resolution = await solver.solve(page)
        injected = await page.locator('[name="h-captcha-response"]').input_value()
        await browser.close()

    assert resolution.valid()
    assert resolution.driver == "nopecha"
    assert resolution.cost_micros == 100
    assert injected == token
    assert [request.method for request in requests] == ["POST", "GET"]


async def test_nopecha_rejects_operation_outside_allowlist_without_http() -> None:
    called = False

    def handler(_request: httpx.Request) -> httpx.Response:
        nonlocal called
        called = True
        return httpx.Response(500)

    solver = NoPechaCaptchaSolver(
        enabled=True,
        budget_micros=500,
        ttl_seconds=60,
        api_key="secret",
        operation_allowed=False,
        unit_cost_micros=100,
        transport=httpx.MockTransport(handler),
    )
    assert not (await solver.solve(object())).valid()  # type: ignore[arg-type]
    assert called is False


def test_captcha_resolution_expires() -> None:
    now = datetime.now(UTC)

    assert CaptchaResolution(True, now + timedelta(seconds=1)).valid(now)
    assert not CaptchaResolution(True, now - timedelta(seconds=1)).valid(now)
