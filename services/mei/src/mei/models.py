from datetime import UTC, datetime
from enum import StrEnum
from typing import Any
from uuid import UUID, uuid4

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator

from mei.operations.registry import OperationNotRegisteredError, operation_registry

OFFICIAL_OPERATION_KEYS = frozenset(
    {
        "pgmei.gerardaspdf",
        "pgmei.gerardascodbarra",
        "pgmei.atubeneficio",
        "pgmei.dividaativa",
        "ccmei.emitirccmei",
        "ccmei.dadosccmei",
        "ccmei.ccmeisitcadastral",
        "dasnsimei.transdeclaracao",
        "dasnsimei.consultimadecrec",
        "dasnsimei.gerardasexcesso",
    }
)


class JobStatus(StrEnum):
    QUEUED = "QUEUED"
    RUNNING = "RUNNING"
    WAITING_USER_ACTION = "WAITING_USER_ACTION"
    SUCCEEDED = "SUCCEEDED"
    FAILED = "FAILED"
    CANCELLED = "CANCELLED"
    UNCERTAIN = "UNCERTAIN"

    @property
    def terminal(self) -> bool:
        return self in {
            self.SUCCEEDED,
            self.FAILED,
            self.CANCELLED,
            self.UNCERTAIN,
        }


class TaxpayerIdentifier(BaseModel):
    cnpj: str

    @field_validator("cnpj")
    @classmethod
    def validate_cnpj(cls, value: str) -> str:
        normalized = "".join(character for character in value.upper() if character.isalnum())
        if len(normalized) != 14 or not normalized.isascii():
            raise ValueError("CNPJ deve conter 14 caracteres ASCII alfanumericos")
        return normalized


class JobCreate(BaseModel):
    model_config = ConfigDict(extra="forbid")

    operation_key: str = Field(min_length=3, max_length=80)
    idempotency_key: str = Field(min_length=8, max_length=160)
    request_fingerprint: str = Field(pattern=r"^[a-f0-9]{64}$")
    client_ref: str = Field(min_length=8, max_length=120)
    input: dict[str, Any] = Field(default_factory=dict)

    @field_validator("operation_key")
    @classmethod
    def normalize_operation_key(cls, value: str) -> str:
        normalized = value.strip().lower()
        if normalized != "fixture.health" and normalized not in OFFICIAL_OPERATION_KEYS:
            raise ValueError("operation_key nao catalogada")
        return normalized

    @model_validator(mode="after")
    def validate_catalogued_input(self) -> "JobCreate":
        try:
            parsed = operation_registry.parse_input(self.operation_key, self.input)
        except OperationNotRegisteredError:
            return self
        self.input = parsed.model_dump(mode="json", exclude_none=True)
        return self


class ArtifactDescriptor(BaseModel):
    id: UUID = Field(default_factory=uuid4)
    name: str = Field(min_length=1, max_length=120)
    content_type: str = Field(min_length=3, max_length=100)
    byte_size: int = Field(ge=0, le=10_485_760)
    sha256: str = Field(pattern=r"^[a-f0-9]{64}$")

    @field_validator("name")
    @classmethod
    def validate_name(cls, value: str) -> str:
        if value in {".", ".."} or any(character in value for character in ("/", "\\", "\0")):
            raise ValueError("Nome de artefato invalido")
        return value


class PublicError(BaseModel):
    code: str = Field(min_length=3, max_length=80)
    message: str = Field(min_length=1, max_length=240)
    retryable: bool = False
    submitted: bool = False


class JobRecord(BaseModel):
    model_config = ConfigDict(extra="forbid")

    id: UUID = Field(default_factory=uuid4)
    operation_key: str
    idempotency_key: str
    request_fingerprint: str
    client_ref: str
    input: dict[str, Any] = Field(default_factory=dict)
    status: JobStatus = JobStatus.QUEUED
    result: dict[str, Any] | None = None
    error: PublicError | None = None
    artifacts: list[ArtifactDescriptor] = Field(default_factory=list)
    action_type: str | None = None
    captcha_driver: str | None = None
    captcha_cost_micros: int = Field(default=0, ge=0)
    created_at: datetime = Field(default_factory=lambda: datetime.now(UTC))
    updated_at: datetime = Field(default_factory=lambda: datetime.now(UTC))
    started_at: datetime | None = None
    finished_at: datetime | None = None

    @classmethod
    def from_create(cls, payload: JobCreate) -> "JobRecord":
        return cls(**payload.model_dump())

    def public(self) -> "JobResponse":
        return JobResponse(
            id=self.id,
            operation_key=self.operation_key,
            status=self.status,
            result=self.result,
            error=self.error,
            artifacts=self.artifacts,
            action_type=self.action_type,
            captcha_driver=self.captcha_driver,
            captcha_cost_micros=self.captcha_cost_micros,
            created_at=self.created_at,
            updated_at=self.updated_at,
            started_at=self.started_at,
            finished_at=self.finished_at,
        )


class JobResponse(BaseModel):
    id: UUID
    operation_key: str
    status: JobStatus
    result: dict[str, Any] | None = None
    error: PublicError | None = None
    artifacts: list[ArtifactDescriptor] = Field(default_factory=list)
    action_type: str | None = None
    captcha_driver: str | None = None
    captcha_cost_micros: int = Field(default=0, ge=0)
    created_at: datetime
    updated_at: datetime
    started_at: datetime | None = None
    finished_at: datetime | None = None


class HealthResponse(BaseModel):
    status: str
    redis: bool | None = None
    hmac_ready: bool | None = None
