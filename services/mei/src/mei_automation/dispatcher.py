from typing import Protocol
from uuid import UUID

from celery import Celery  # type: ignore[import-untyped]


class JobDispatcher(Protocol):
    def dispatch(self, job_id: UUID) -> None: ...

    def cancel(self, job_id: UUID) -> None: ...


class CeleryJobDispatcher:
    def __init__(self, celery: Celery) -> None:
        self._celery = celery

    def dispatch(self, job_id: UUID) -> None:
        self._celery.send_task(
            "mei_automation.execute_job",
            args=[str(job_id)],
            task_id=str(job_id),
        )

    def cancel(self, job_id: UUID) -> None:
        self._celery.control.revoke(str(job_id), terminate=False)


class InMemoryDispatcher:
    def __init__(self) -> None:
        self.dispatched: list[UUID] = []
        self.cancelled: list[UUID] = []

    def dispatch(self, job_id: UUID) -> None:
        self.dispatched.append(job_id)

    def cancel(self, job_id: UUID) -> None:
        self.cancelled.append(job_id)
