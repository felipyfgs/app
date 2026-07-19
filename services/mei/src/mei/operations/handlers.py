from mei.operations.base import OperationHandler
from mei.operations.dasn import DasnSimeiHandler
from mei.operations.pgmei import PgmeiHandler


def operation_handler(operation_key: str) -> OperationHandler | None:
    if operation_key.startswith("pgmei.") and operation_key in {
        "pgmei.gerardaspdf",
        "pgmei.gerardascodbarra",
        "pgmei.dividaativa",
    }:
        return PgmeiHandler()
    if operation_key == "dasnsimei.consultimadecrec":
        return DasnSimeiHandler()
    return None
