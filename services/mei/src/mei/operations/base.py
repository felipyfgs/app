from dataclasses import dataclass, field
from typing import Any, Protocol

from playwright.async_api import Page

from mei.artifacts import ArtifactStore
from mei.config import Settings
from mei.models import ArtifactDescriptor, JobRecord, JobStatus, PublicError


@dataclass(frozen=True, slots=True)
class OperationContext:
    settings: Settings
    artifact_store: ArtifactStore


@dataclass(slots=True)
class HandlerOutcome:
    status: JobStatus
    result: dict[str, Any] | None = None
    error: PublicError | None = None
    artifacts: list[ArtifactDescriptor] = field(default_factory=list)
    action_type: str | None = None
    captcha_driver: str | None = None
    captcha_cost_micros: int = 0


class OperationHandler(Protocol):
    async def execute(
        self, page: Page, record: JobRecord, context: OperationContext
    ) -> HandlerOutcome: ...


def failed(
    code: str,
    message: str,
    *,
    submitted: bool = False,
    retryable: bool = False,
) -> HandlerOutcome:
    return HandlerOutcome(
        status=JobStatus.UNCERTAIN if submitted else JobStatus.FAILED,
        error=PublicError(
            code=code,
            message=message,
            submitted=submitted,
            retryable=retryable,
        ),
    )
