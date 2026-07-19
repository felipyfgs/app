from mei.executor import ExecutionOutcome, JobRunner
from mei.models import JobCreate, JobRecord, JobStatus
from mei.store import InMemoryJobStore


class SuccessExecutor:
    async def execute(self, _record: JobRecord) -> ExecutionOutcome:
        return ExecutionOutcome(status=JobStatus.SUCCEEDED, result={"ok": True})


class FailingExecutor:
    async def execute(self, _record: JobRecord) -> ExecutionOutcome:
        raise RuntimeError("sensitive upstream detail")


async def seed(store: InMemoryJobStore) -> JobRecord:
    record, _ = await store.create_or_get(
        JobCreate(
            operation_key="fixture.health",
            idempotency_key="fixture:12345678",
            request_fingerprint="a" * 64,
            client_ref="opaque-client-fixture",
        )
    )
    return record


async def test_runner_persists_success() -> None:
    store = InMemoryJobStore()
    result = await JobRunner(store, SuccessExecutor()).run(await seed(store))
    assert result.status == JobStatus.SUCCEEDED
    assert result.result == {"ok": True}
    assert result.finished_at is not None


async def test_runner_redacts_unhandled_exception() -> None:
    store = InMemoryJobStore()
    result = await JobRunner(store, FailingExecutor()).run(await seed(store))
    assert result.status == JobStatus.FAILED
    assert result.error is not None
    assert result.error.code == "EXECUTION_ERROR"
    assert "sensitive" not in result.error.message
