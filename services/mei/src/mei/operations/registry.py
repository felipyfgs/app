from dataclasses import dataclass

from pydantic import BaseModel

from mei.operations.schemas import (
    ActiveDebtResult,
    DasArtifactResult,
    DasnHistoryInput,
    DasnHistoryResult,
    EmptyInput,
    PgmeiActiveDebtInput,
    PgmeiGenerateDasInput,
)


class OperationNotRegisteredError(LookupError):
    pass


@dataclass(frozen=True, slots=True)
class OperationDefinition:
    input_model: type[BaseModel]
    result_model: type[BaseModel] | None


class OperationRegistry:
    def __init__(self, definitions: dict[str, OperationDefinition] | None = None) -> None:
        self._definitions = definitions or {}

    def register(
        self,
        operation_key: str,
        input_model: type[BaseModel],
        result_model: type[BaseModel] | None,
    ) -> None:
        if operation_key in self._definitions:
            raise ValueError(f"Operacao ja registrada: {operation_key}")
        self._definitions[operation_key] = OperationDefinition(input_model, result_model)

    def supports(self, operation_key: str) -> bool:
        return operation_key in self._definitions

    def parse_input(self, operation_key: str, payload: dict[str, object]) -> BaseModel:
        return self._definition(operation_key).input_model.model_validate(payload)

    def parse_result(self, operation_key: str, payload: dict[str, object]) -> BaseModel:
        result_model = self._definition(operation_key).result_model
        if result_model is None:
            raise OperationNotRegisteredError(operation_key)
        return result_model.model_validate(payload)

    def _definition(self, operation_key: str) -> OperationDefinition:
        try:
            return self._definitions[operation_key]
        except KeyError as error:
            raise OperationNotRegisteredError(operation_key) from error


operation_registry = OperationRegistry(
    {
        "fixture.health": OperationDefinition(EmptyInput, None),
        "pgmei.gerardaspdf": OperationDefinition(PgmeiGenerateDasInput, DasArtifactResult),
        "pgmei.gerardascodbarra": OperationDefinition(PgmeiGenerateDasInput, DasArtifactResult),
        "pgmei.dividaativa": OperationDefinition(PgmeiActiveDebtInput, ActiveDebtResult),
        "dasnsimei.consultimadecrec": OperationDefinition(DasnHistoryInput, DasnHistoryResult),
    }
)
