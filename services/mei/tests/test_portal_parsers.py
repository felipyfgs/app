from pathlib import Path

import pytest

from mei.operations.dasn import DasnSimeiParser
from mei.operations.pgmei import PgmeiParser
from mei.operations.schemas import Coverage

FIXTURES = Path(__file__).parent / "fixtures"


def fixture(relative_path: str) -> str:
    return (FIXTURES / relative_path).read_text(encoding="utf-8")


def test_pgmei_parser_reads_competencies_and_active_debt() -> None:
    parser = PgmeiParser()

    assert parser.competencies(fixture("pgmei/competencias.html")) == {
        "2026-01",
        "2026-02",
    }
    debt = parser.active_debt(fixture("pgmei/divida-ativa.html"), 2025)
    assert debt.portal_version == "fixture-1"
    assert debt.years[0].year == 2025
    assert debt.years[0].debt_count == 2


def test_pgmei_parser_normalizes_real_portal_competence_values() -> None:
    assert PgmeiParser().competency_values(["202601", "2026-02", "03/2026", "ignorar"]) == {
        "2026-01",
        "2026-02",
        "2026-03",
    }


def test_pgmei_parser_fails_closed_when_semantic_table_is_missing() -> None:
    with pytest.raises(ValueError):
        PgmeiParser().active_debt("<html><main>layout alterado</main></html>")


def test_dasn_parser_preserves_summary_and_receipt_availability() -> None:
    history = DasnSimeiParser().history(fixture("dasnsimei/historico.html"))

    assert history.coverage is Coverage.SUMMARY
    assert history.declarations[0].coverage is Coverage.SUMMARY
    assert history.declarations[0].receipt_available is True
    assert history.declarations[0].receipt_artifact_id is None
    assert history.declarations[1].receipt_available is False


def test_dasn_parser_reads_real_calendar_control_shape() -> None:
    history = DasnSimeiParser().history_items(
        [
            {
                "calendar_year": "2025",
                "status": "Transmitida em 18/05/2026",
                "transmitted_at": "18/05/2026",
                "receipt_available": False,
                "declaration_type": "Original",
                "special_situation": "Extinção",
                "special_situation_date": "20/05/2026",
                "pending": True,
            },
            {
                "calendar_year": "2024",
                "status": "Retificadora",
                "transmitted_at": "",
                "receipt_available": True,
            },
        ],
        calendar_year=2025,
        portal_version="live-unversioned",
    )

    assert history.coverage is Coverage.SUMMARY
    assert len(history.declarations) == 1
    assert history.declarations[0].calendar_year == 2025
    assert history.declarations[0].transmitted_at is not None
    assert history.declarations[0].transmitted_at.isoformat() == "2026-05-18"
    assert history.declarations[0].declaration_type == "Original"
    assert history.declarations[0].special_situation == "Extinção"
    assert history.declarations[0].special_situation_date is not None
    assert history.declarations[0].special_situation_date.isoformat() == "2026-05-20"
    assert history.declarations[0].pending is True
    assert history.portal_version == "live-unversioned"
