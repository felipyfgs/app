import hashlib
import time
from pathlib import Path
from uuid import UUID

from conftest import build_client, job_payload, signed_request

from mei.artifacts import LocalArtifactStore
from mei.models import ArtifactDescriptor


def test_create_get_and_cancel_job() -> None:
    client, _store, dispatcher = build_client()
    with client:
        created = signed_request(client, "POST", "/v1/jobs", job_payload())
        assert created.status_code == 202
        job_id = created.json()["id"]
        assert dispatcher.dispatched

        fetched = signed_request(
            client,
            "GET",
            f"/v1/jobs/{job_id}",
            nonce="nonce-get-123456789012",
        )
        assert fetched.status_code == 200
        assert fetched.json()["status"] == "QUEUED"

        cancelled = signed_request(
            client,
            "DELETE",
            f"/v1/jobs/{job_id}",
            nonce="nonce-del-123456789012",
        )
        assert cancelled.json()["status"] == "CANCELLED"
        assert dispatcher.cancelled


def test_idempotency_reuses_job_and_rejects_collision() -> None:
    client, _store, dispatcher = build_client()
    with client:
        first = signed_request(client, "POST", "/v1/jobs", job_payload())
        second = signed_request(
            client,
            "POST",
            "/v1/jobs",
            job_payload(),
            nonce="nonce-second-123456789",
        )
        assert second.status_code == 200
        assert second.json()["id"] == first.json()["id"]
        assert len(dispatcher.dispatched) == 1

        collision = job_payload()
        collision["request_fingerprint"] = "f" * 64
        response = signed_request(
            client,
            "POST",
            "/v1/jobs",
            collision,
            nonce="nonce-third-1234567890",
        )
        assert response.status_code == 409


def test_hmac_rejects_replay_and_expired_timestamp() -> None:
    client, _store, _dispatcher = build_client()
    with client:
        first = signed_request(client, "POST", "/v1/jobs", job_payload())
        replay = signed_request(client, "POST", "/v1/jobs", job_payload())
        expired = signed_request(
            client,
            "POST",
            "/v1/jobs",
            job_payload(),
            nonce="nonce-expired-123456789",
            timestamp=str(int(time.time()) - 120),
        )
        assert first.status_code == 202
        assert replay.status_code == 401
        assert expired.status_code == 401


def test_ready_requires_hmac_and_store() -> None:
    client, _store, _dispatcher = build_client()
    with client:
        response = client.get("/health/ready")
    assert response.status_code == 200
    assert response.json() == {"status": "ok", "redis": True, "hmac_ready": True}


def test_downloads_only_artifact_linked_to_job(tmp_path: Path) -> None:
    artifact_store = LocalArtifactStore(tmp_path)
    client, store, _dispatcher = build_client(artifact_store=artifact_store)
    content = b"%PDF-1.7\nfixture"
    with client:
        created = signed_request(client, "POST", "/v1/jobs", job_payload())
        job_id = UUID(created.json()["id"])
        artifact = ArtifactDescriptor(
            name="das.pdf",
            content_type="application/pdf",
            byte_size=len(content),
            sha256=hashlib.sha256(content).hexdigest(),
        )
        artifact_store.write(job_id, artifact.id, content)

        record = _run(store.get(job_id))
        record.artifacts.append(artifact)
        _run(store.save(record))

        response = signed_request(
            client,
            "GET",
            f"/v1/jobs/{job_id}/artifacts/{artifact.id}",
            nonce="nonce-artifact-12345678",
        )
        unknown = signed_request(
            client,
            "GET",
            f"/v1/jobs/{job_id}/artifacts/0f82d5ec-d69f-4b2b-a2d6-b2c52e0e1b92",
            nonce="nonce-artifact-unknown12",
        )

    assert response.status_code == 200
    assert response.content == content
    assert response.headers["cache-control"] == "private, no-store, max-age=0"
    assert unknown.status_code == 404


def _run(coroutine):
    import asyncio

    return asyncio.run(coroutine)
