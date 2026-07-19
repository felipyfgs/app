import hashlib

import pytest
from pydantic import ValidationError

from mei_automation.models import JobCreate, JobRecord, JobStatus, TaxpayerIdentifier


def test_cnpj_accepts_numeric_and_alphanumeric_values() -> None:
    assert TaxpayerIdentifier(cnpj="11.222.333/0001-81").cnpj == "11222333000181"
    assert TaxpayerIdentifier(cnpj="AB.CDE.123/0001-Z9").cnpj == "ABCDE1230001Z9"


def test_cnpj_rejects_wrong_length() -> None:
    with pytest.raises(ValidationError):
        TaxpayerIdentifier(cnpj="123")


def test_job_public_contract_omits_sensitive_input() -> None:
    payload = JobCreate(
        operation_key="pgmei.dividaativa",
        idempotency_key="run:12345678",
        request_fingerprint=hashlib.sha256(b"payload").hexdigest(),
        client_ref="opaque-client-123",
        input={"cnpj": "11222333000181"},
    )

    public = JobRecord.from_create(payload).public().model_dump()

    assert public["status"] == JobStatus.QUEUED
    assert "input" not in public
    assert "client_ref" not in public
    assert "idempotency_key" not in public


def test_unknown_operation_is_rejected() -> None:
    with pytest.raises(ValidationError):
        JobCreate(
            operation_key="unknown.operation",
            idempotency_key="run:12345678",
            request_fingerprint="0" * 64,
            client_ref="opaque-client-123",
        )
