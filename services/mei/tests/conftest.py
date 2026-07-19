import hashlib
import json
import time
from typing import Any

from fastapi.testclient import TestClient

from mei.api import create_app
from mei.artifacts import ArtifactStore
from mei.config import Settings
from mei.dispatcher import InMemoryDispatcher
from mei.security import (
    KEY_ID_HEADER,
    NONCE_HEADER,
    SIGNATURE_HEADER,
    TIMESTAMP_HEADER,
    HmacAuthenticator,
    InMemoryReplayStore,
    calculate_signature,
)
from mei.store import InMemoryJobStore

SECRET = "test-secret-with-at-least-32-bytes"  # noqa: S105 - fixture de teste


def build_client(
    artifact_store: ArtifactStore | None = None,
) -> tuple[TestClient, InMemoryJobStore, InMemoryDispatcher]:
    settings = Settings(environment="testing", hmac_secret=SECRET)
    store = InMemoryJobStore()
    dispatcher = InMemoryDispatcher()
    authenticator = HmacAuthenticator(settings, InMemoryReplayStore())
    return (
        TestClient(create_app(settings, store, authenticator, dispatcher, artifact_store)),
        store,
        dispatcher,
    )


def signed_request(
    client: TestClient,
    method: str,
    path: str,
    payload: dict[str, Any] | None = None,
    *,
    nonce: str = "nonce-1234567890123456",
    timestamp: str | None = None,
):
    body = b"" if payload is None else json.dumps(payload, separators=(",", ":")).encode()
    timestamp = timestamp or str(int(time.time()))
    headers = {
        KEY_ID_HEADER: "laravel",
        TIMESTAMP_HEADER: timestamp,
        NONCE_HEADER: nonce,
        SIGNATURE_HEADER: calculate_signature(SECRET, method, path, body, timestamp, nonce),
    }
    return client.request(
        method,
        path,
        content=body or None,
        headers={**headers, **({"Content-Type": "application/json"} if body else {})},
    )


def job_payload() -> dict[str, Any]:
    return {
        "operation_key": "fixture.health",
        "idempotency_key": "fixture:12345678",
        "request_fingerprint": hashlib.sha256(b"fixture").hexdigest(),
        "client_ref": "opaque-client-fixture",
        "input": {},
    }
