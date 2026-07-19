from datetime import UTC, datetime
from typing import Any, Protocol

from playwright.async_api import async_playwright
from pydantic import BaseModel

from mei_automation.config import Settings
from mei_automation.models import JobRecord, JobStatus, PublicError
from mei_automation.store import JobStore


class ExecutionOutcome(BaseModel):
    status: JobStatus
    result: dict[str, Any] | None = None
    error: PublicError | None = None
    action_type: str | None = None


class OperationExecutor(Protocol):
    async def execute(self, record: JobRecord) -> ExecutionOutcome: ...


class PlaywrightOperationExecutor:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings

    async def execute(self, record: JobRecord) -> ExecutionOutcome:
        if record.operation_key != "fixture.health" and not self._settings.live_egress_enabled:
            return ExecutionOutcome(
                status=JobStatus.FAILED,
                error=PublicError(
                    code="LIVE_EGRESS_DISABLED",
                    message="Egress live do portal MEI esta desabilitado.",
                ),
            )

        if record.operation_key != "fixture.health":
            return ExecutionOutcome(
                status=JobStatus.FAILED,
                error=PublicError(
                    code="OPERATION_NOT_IMPLEMENTED",
                    message="Operacao ainda nao implementada no provider portal.",
                ),
            )

        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=self._settings.browser_headless)
            context = await browser.new_context()
            try:
                page = await context.new_page()
                page.set_default_timeout(self._settings.browser_timeout_ms)
                await page.set_content("<title>mei-automation-ready</title><main>ready</main>")
                title = await page.title()
            finally:
                await context.close()
                await browser.close()

        return ExecutionOutcome(
            status=JobStatus.SUCCEEDED,
            result={"fixture": "health", "browser_title": title, "isolated_context": True},
        )


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
        record.action_type = outcome.action_type
        if record.status.terminal:
            record.finished_at = datetime.now(UTC)
        return await self._store.save(record)
