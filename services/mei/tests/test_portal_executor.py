from datetime import date, timedelta
from pathlib import Path

import pytest
from playwright.async_api import async_playwright

from mei.artifacts import LocalArtifactStore
from mei.config import Settings
from mei.executor import PlaywrightOperationExecutor
from mei.models import JobCreate, JobRecord, JobStatus
from mei.operations.base import OperationContext
from mei.operations.dasn import DasnSimeiHandler
from mei.operations.pgmei import PgmeiHandler, fixture_pdf

FIXTURES = Path(__file__).parent / "fixtures"


def test_settings(**overrides: object) -> Settings:
    """Fixtures usam Chromium embutido; o runtime live usa Chrome headed (docapi)."""
    values: dict[str, object] = {
        "environment": "testing",
        "browser_headless": True,
        "browser_channel": "",
    }
    values.update(overrides)
    return Settings(**values)  # type: ignore[arg-type]


def record(operation_key: str, input_payload: dict[str, object]) -> JobRecord:
    return JobRecord.from_create(
        JobCreate(
            operation_key=operation_key,
            idempotency_key=f"fixture:{operation_key}:12345678",
            request_fingerprint="a" * 64,
            client_ref="opaque-client-fixture",
            input=input_payload,
        )
    )


@pytest.mark.parametrize(
    ("operation_key", "input_payload", "artifact_type"),
    [
        (
            "pgmei.gerardaspdf",
            {"cnpj": "ABCDE1230001Z9", "competencies": ["2026-01"]},
            "application/pdf",
        ),
        (
            "pgmei.gerardascodbarra",
            {"cnpj": "11222333000181", "competencies": ["2026-01"]},
            "text/plain",
        ),
        (
            "pgmei.dividaativa",
            {"cnpj": "11222333000181", "calendar_year": 2025},
            None,
        ),
        (
            "dasnsimei.consultimadecrec",
            {"cnpj": "11222333000181", "calendar_year": 2024},
            None,
        ),
    ],
)
async def test_fixture_handlers_run_in_ephemeral_browser_context(
    tmp_path: Path,
    operation_key: str,
    input_payload: dict[str, object],
    artifact_type: str | None,
) -> None:
    settings = test_settings(
        fixture_enabled=True,
        fixture_root=FIXTURES,
        artifact_root=tmp_path,
    )
    store = LocalArtifactStore(tmp_path)
    job = record(operation_key, input_payload)
    outcome = await PlaywrightOperationExecutor(settings, store).execute(job)

    assert outcome.status is JobStatus.SUCCEEDED
    assert outcome.error is None
    assert len(outcome.artifacts) == (1 if artifact_type else 0)
    if artifact_type:
        assert outcome.artifacts[0].content_type == artifact_type
        assert store.resolve(job.id, outcome.artifacts[0].id).is_file()


async def test_dasn_full_receipt_is_only_promoted_with_valid_artifact(tmp_path: Path) -> None:
    settings = test_settings(
        fixture_enabled=True,
        fixture_root=FIXTURES,
        artifact_root=tmp_path,
    )
    job = record(
        "dasnsimei.consultimadecrec",
        {
            "cnpj": "11222333000181",
            "calendar_year": 2024,
            "include_full_receipt": True,
        },
    )
    outcome = await PlaywrightOperationExecutor(settings, LocalArtifactStore(tmp_path)).execute(job)

    assert outcome.status is JobStatus.SUCCEEDED
    assert outcome.result is not None
    assert outcome.result["coverage"] == "FULL"
    assert outcome.result["declarations"][0]["coverage"] == "FULL"
    assert outcome.result["declarations"][0]["receipt_artifact_id"]
    assert len(outcome.artifacts) == 1


async def test_dasn_rejects_alphanumeric_cnpj_when_fixture_is_numeric_only(
    tmp_path: Path,
) -> None:
    settings = test_settings(
        fixture_enabled=True,
        fixture_root=FIXTURES,
        artifact_root=tmp_path,
    )
    outcome = await PlaywrightOperationExecutor(settings).execute(
        record(
            "dasnsimei.consultimadecrec",
            {"cnpj": "ABCDE1230001Z9", "calendar_year": 2024},
        )
    )

    assert outcome.status is JobStatus.FAILED
    assert outcome.error is not None
    assert outcome.error.code == "PORTAL_CNPJ_FORMAT_UNSUPPORTED"
    assert outcome.error.submitted is False


async def test_official_operation_is_blocked_without_fixture_or_live_egress(
    tmp_path: Path,
) -> None:
    settings = test_settings(artifact_root=tmp_path)
    outcome = await PlaywrightOperationExecutor(settings).execute(
        record("pgmei.dividaativa", {"cnpj": "11222333000181"})
    )

    assert outcome.status is JobStatus.FAILED
    assert outcome.error is not None
    assert outcome.error.code == "LIVE_EGRESS_DISABLED"


async def test_pgmei_live_shape_runs_each_navigation_step_once(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    settings = Settings(
        environment="testing",
        live_egress_enabled=True,
        pgmei_identification_url=(FIXTURES / "pgmei/live-flow.html").as_uri(),
        artifact_root=tmp_path,
    )
    handler = PgmeiHandler()
    due_date = date.today() + timedelta(days=1)

    async def download_once(*_args: object) -> bytes:
        return fixture_pdf("DAS MEI live flow sanitizado")

    monkeypatch.setattr(handler, "_download_emitted_pdf", download_once)
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        outcome = await handler.execute(
            page,
            record(
                "pgmei.gerardaspdf",
                {
                    "cnpj": "11222333000181",
                    "competencies": ["2026-01"],
                    "due_date": due_date.isoformat(),
                },
            ),
            OperationContext(settings, LocalArtifactStore(tmp_path)),
        )
        emission_count = await page.evaluate("window.emissionCount")
        identification_count = await page.evaluate("window.identificationSubmitCount")
        update_count = await page.evaluate("window.updateCount")
        payment_date = await page.evaluate("window.paymentDate")
        await browser.close()

    assert outcome.status is JobStatus.SUCCEEDED
    assert outcome.error is None
    assert identification_count == 1
    assert emission_count == 1
    assert update_count == 1
    assert payment_date == due_date.strftime("%d/%m/%Y")
    assert outcome.result is not None
    assert outcome.result["due_date"] == due_date.isoformat()
    assert len(outcome.artifacts) == 1


@pytest.mark.parametrize(
    ("remote_outcome", "error_code"),
    [
        ("challenge", "CAPTCHA_EXHAUSTED"),
        ("captcha-rejection", "CAPTCHA_EXHAUSTED"),
        ("drift", "PORTAL_DRIFT"),
    ],
)
async def test_pgmei_identification_failure_is_single_and_pre_submission(
    tmp_path: Path,
    remote_outcome: str,
    error_code: str,
) -> None:
    fixture = (FIXTURES / "pgmei" / "identification-outcomes.html").as_uri()
    settings = Settings(
        environment="testing",
        live_egress_enabled=True,
        pgmei_identification_url=f"{fixture}?outcome={remote_outcome}",
        browser_timeout_ms=5_000,
        artifact_root=tmp_path,
    )
    handler = PgmeiHandler()

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        outcome = await handler.execute(
            page,
            record(
                "pgmei.dividaativa",
                {"cnpj": "11222333000181", "calendar_year": 2025},
            ),
            OperationContext(settings, LocalArtifactStore(tmp_path)),
        )
        identification_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    assert outcome.status is JobStatus.FAILED
    assert outcome.error is not None
    assert outcome.error.code == error_code
    assert outcome.error.submitted is False
    assert outcome.error.retryable is True
    assert identification_count == 1


@pytest.mark.parametrize(
    ("remote_outcome", "expected_status", "error_code"),
    [
        ("auto-pass", JobStatus.SUCCEEDED, None),
        ("challenge", JobStatus.FAILED, "CAPTCHA_EXHAUSTED"),
        ("captcha-rejection", JobStatus.FAILED, "CAPTCHA_EXHAUSTED"),
        ("validation", JobStatus.FAILED, "PORTAL_DRIFT"),
        ("drift", JobStatus.FAILED, "PORTAL_DRIFT"),
    ],
)
async def test_dasn_identification_has_one_submit_and_semantic_outcome(
    tmp_path: Path,
    remote_outcome: str,
    expected_status: JobStatus,
    error_code: str | None,
) -> None:
    fixture = (FIXTURES / "dasnsimei" / "identification-outcomes.html").as_uri()
    settings = Settings(
        environment="testing",
        live_egress_enabled=True,
        dasn_identification_url=f"{fixture}?outcome={remote_outcome}",
        browser_timeout_ms=5_000,
        artifact_root=tmp_path,
    )
    handler = DasnSimeiHandler()

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(headless=True)
        page = await browser.new_page()
        outcome = await handler.execute(
            page,
            record(
                "dasnsimei.consultimadecrec",
                {"cnpj": "11222333000181", "calendar_year": 2024},
            ),
            OperationContext(settings, LocalArtifactStore(tmp_path)),
        )
        identification_count = await page.evaluate("window.identificationSubmitCount")
        await browser.close()

    assert outcome.status is expected_status
    assert identification_count == 1
    if error_code is None:
        assert outcome.error is None
        assert outcome.result is not None
        assert outcome.result["coverage"] == "SUMMARY"
    else:
        assert outcome.error is not None
        assert outcome.error.code == error_code
        assert outcome.error.submitted is False
        if remote_outcome == "captcha-rejection":
            assert outcome.error.retryable is True
