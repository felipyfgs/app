from datetime import date, datetime
from enum import StrEnum
from zoneinfo import ZoneInfo

from pydantic import BaseModel, ConfigDict, Field, field_validator


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class EmptyInput(StrictModel):
    pass


class CnpjInput(StrictModel):
    cnpj: str

    @field_validator("cnpj")
    @classmethod
    def validate_cnpj(cls, value: str) -> str:
        normalized = "".join(character for character in value.upper() if character.isalnum())
        if len(normalized) != 14 or not normalized.isascii():
            raise ValueError("CNPJ deve conter 14 caracteres ASCII alfanumericos")
        return normalized


class PgmeiGenerateDasInput(CnpjInput):
    competencies: list[str] = Field(min_length=1, max_length=12)
    due_date: date | None = None

    @field_validator("competencies")
    @classmethod
    def validate_competencies(cls, values: list[str]) -> list[str]:
        normalized = list(dict.fromkeys(values))
        for value in normalized:
            try:
                parsed = date.fromisoformat(f"{value}-01")
            except ValueError as error:
                raise ValueError("Competencia deve usar YYYY-MM") from error
            if parsed.year < 2009 or parsed.year > date.today().year + 1:
                raise ValueError("Competencia fora do intervalo suportado")
        return normalized

    @field_validator("due_date")
    @classmethod
    def validate_due_date(cls, value: date | None) -> date | None:
        if value is not None and value < datetime.now(ZoneInfo("America/Sao_Paulo")).date():
            raise ValueError("Data de pagamento nao pode estar no passado")
        return value


class PgmeiActiveDebtInput(CnpjInput):
    calendar_year: int | None = Field(default=None, ge=2009, le=date.today().year + 1)


class DasnHistoryInput(CnpjInput):
    calendar_year: int | None = Field(default=None, ge=2009, le=date.today().year)
    include_full_receipt: bool = False


class Coverage(StrEnum):
    SUMMARY = "SUMMARY"
    FULL = "FULL"


class DasArtifactResult(StrictModel):
    competencies: list[str]
    due_date: date
    submitted: bool
    parser_version: str
    portal_version: str
    barcode: str | None = None


class ActiveDebtYear(StrictModel):
    year: int
    status: str = Field(min_length=1, max_length=80)
    debt_count: int = Field(ge=0)


class ActiveDebtResult(StrictModel):
    years: list[ActiveDebtYear]
    parser_version: str
    portal_version: str


class DasnDeclarationSummary(StrictModel):
    calendar_year: int
    status: str = Field(min_length=1, max_length=80)
    transmitted_at: date | None = None
    declaration_type: str | None = Field(default=None, max_length=40)
    special_situation: str | None = Field(default=None, max_length=80)
    special_situation_date: date | None = None
    pending: bool = False
    coverage: Coverage = Coverage.SUMMARY
    receipt_available: bool = False
    receipt_artifact_id: str | None = None


class DasnHistoryResult(StrictModel):
    declarations: list[DasnDeclarationSummary]
    coverage: Coverage
    parser_version: str
    portal_version: str
