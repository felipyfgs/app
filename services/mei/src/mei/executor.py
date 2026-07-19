from datetime import UTC, datetime
from typing import Any, Protocol

from playwright.async_api import async_playwright
from pydantic import BaseModel, Field

from mei.artifacts import ArtifactStore, LocalArtifactStore
from mei.browser import LAUNCH_ARGS, launch_browser
from mei.config import Settings
from mei.models import ArtifactDescriptor, JobRecord, JobStatus, PublicError
from mei.operations.base import OperationContext
from mei.operations.handlers import operation_handler
from mei.store import JobStore

# Reexport para testes/compat.
CHROMIUM_LAUNCH_ARGS = LAUNCH_ARGS


class ExecutionOutcome(BaseModel):
    status: JobStatus
    result: dict[str, Any] | None = None
    error: PublicError | None = None
    artifacts: list[ArtifactDescriptor] = Field(default_factory=list)
    action_type: str | None = None
    captcha_driver: str | None = None
    captcha_cost_micros: int = 0


class OperationExecutor(Protocol):
    async def execute(self, record: JobRecord) -> ExecutionOutcome: ...


class PlaywrightOperationExecutor:
    def __init__(self, settings: Settings, artifact_store: ArtifactStore | None = None) -> None:
        self._settings = settings
        self._artifact_store = artifact_store or LocalArtifactStore(settings.artifact_root)

    async def execute(self, record: JobRecord) -> ExecutionOutcome:
        if (
            record.operation_key != "fixture.health"
            and not self._settings.live_egress_enabled
            and not self._settings.fixture_enabled
        ):
            return ExecutionOutcome(
                status=JobStatus.FAILED,
                error=PublicError(
                    code="LIVE_EGRESS_DISABLED",
                    message="Egress live do portal MEI esta desabilitado.",
                ),
            )

        handler = operation_handler(record.operation_key)
        if record.operation_key != "fixture.health" and handler is None:
            return ExecutionOutcome(
                status=JobStatus.FAILED,
                error=PublicError(
                    code="OPERATION_NOT_IMPLEMENTED",
                    message="Operacao ainda nao implementada no provider portal.",
                ),
            )

        async with async_playwright() as playwright:
            browser = await launch_browser(playwright, self._settings)
            context = await browser.new_context(
                viewport={"width": 1280, "height": 720},
                locale="pt-BR",
                timezone_id="America/Sao_Paulo",
            )
            try:
                page = await context.new_page()
                page.set_default_timeout(self._settings.browser_timeout_ms)
                if record.operation_key == "fixture.health":
                    await page.set_content("<title>mei-ready</title><main>ready</main>")
                    title = await page.title()
                    outcome = ExecutionOutcome(
                        status=JobStatus.SUCCEEDED,
                        result={
                            "fixture": "health",
                            "browser_title": title,
                            "isolated_context": True,
                        },
                    )
                else:
                    assert handler is not None
                    handled = await handler.execute(
                        page,
                        record,
                        OperationContext(self._settings, self._artifact_store),
                    )
                    outcome = ExecutionOutcome(
                        status=handled.status,
                        result=handled.result,
                        error=handled.error,
                        artifacts=handled.artifacts,
                        action_type=handled.action_type,
                        captcha_driver=handled.captcha_driver,
                        captcha_cost_micros=handled.captcha_cost_micros,
                    )
            finally:
                await context.close()
                await browser.close()

        return outcome


class JobRunner:
    def __init__(self, store: JobStore, executor: OperationExecutor) -> None:
        self._store = store
        self._executor = executor

    async def run(self, record: JobRecord) -> JobRecord:
        if record.status == JobStatus.CANCELLED or record.status.terminal:
            return record

        record.status = JobStatus.RUNNING
        record.started_at = record.started_at or datetime.now(UTC)
        record.error = None
        record = await self._store.save(record)

        try:
            outcome = await self._executor.execute(record)
        except Exception:  # noqa: BLE001 - boundary redige detalhes do browser
            outcome = ExecutionOutcome(
                status=JobStatus.FAILED,
                error=PublicError(
                    code="EXECUTION_ERROR",
                    message="Falha interna ao executar automacao.",
                    retryable=True,
                ),
            )

        record.status = outcome.status
        record.result = outcome.result
        record.error = outcome.error
        record.artifacts = outcome.artifacts
        record.action_type = outcome.action_type
        record.captcha_driver = outcome.captcha_driver
        record.captcha_cost_micros = outcome.captcha_cost_micros
        if record.status.terminal:
            record.finished_at = datetime.now(UTC)
        return await self._store.save(record)
