import pytest
from pydantic import ValidationError

from mei.models import JobCreate
from mei.operations.registry import (
    OperationNotRegisteredError,
    OperationRegistry,
    operation_registry,
)
from mei.operations.schemas import Coverage, DasnHistoryResult, EmptyInput


def job(operation_key: str, input_payload: dict[str, object]) -> JobCreate:
    return JobCreate(
        operation_key=operation_key,
        idempotency_key="operation:12345678",
        request_fingerprint="a" * 64,
        client_ref="opaque-client-123",
        input=input_payload,
    )


def test_job_normalizes_cnpj_and_competencies_before_queue() -> None:
    parsed = job(
        "pgmei.gerardaspdf",
        {
            "cnpj": "AB.CDE.123/0001-Z9",
            "competencies": ["2026-01", "2026-01", "2026-02"],
        },
    )

    assert parsed.input == {
        "cnpj": "ABCDE1230001Z9",
        "competencies": ["2026-01", "2026-02"],
    }


@pytest.mark.parametrize(
    ("operation_key", "input_payload"),
    [
        ("pgmei.gerardascodbarra", {"cnpj": "11222333000181"}),
        (
            "pgmei.dividaativa",
            {
                "cnpj": "11222333000181",
                "calendar_year": 2026,
                "unexpected": "blocked",
            },
        ),
        (
            "dasnsimei.consultimadecrec",
            {"cnpj": "11222333000181", "calendar_year": 2008},
        ),
    ],
)
def test_c1_input_is_rejected_before_dispatch(
    operation_key: str,
    input_payload: dict[str, object],
) -> None:
    with pytest.raises(ValidationError):
        job(operation_key, input_payload)


def test_future_c2_operation_remains_catalogued_but_has_no_c1_handler() -> None:
    parsed = job("ccmei.emitirccmei", {"opaque": True})

    assert parsed.operation_key == "ccmei.emitirccmei"
    with pytest.raises(OperationNotRegisteredError):
        operation_registry.parse_input(parsed.operation_key, parsed.input)


def test_registry_rejects_duplicate_and_unknown_operation() -> None:
    registry = OperationRegistry()
    registry.register("fixture.health", EmptyInput, None)

    with pytest.raises(ValueError):
        registry.register("fixture.health", EmptyInput, None)
    with pytest.raises(OperationNotRegisteredError):
        registry.parse_input("unknown.operation", {})


def test_dasn_result_preserves_summary_coverage() -> None:
    result = operation_registry.parse_result(
        "dasnsimei.consultimadecrec",
        {
            "coverage": "SUMMARY",
            "parser_version": "fixture-1",
            "portal_version": "fixture-1",
            "declarations": [
                {
                    "calendar_year": 2024,
                    "status": "Transmitida",
                    "transmitted_at": "2025-05-15",
                    "coverage": "SUMMARY",
                    "receipt_available": True,
                }
            ],
        },
    )

    assert isinstance(result, DasnHistoryResult)
    assert result.coverage is Coverage.SUMMARY
    assert result.declarations[0].coverage is Coverage.SUMMARY
