import os
from pathlib import Path
from uuid import uuid4

from mei.artifacts import ArtifactNotFoundError, LocalArtifactStore


def test_purges_only_expired_artifacts(tmp_path: Path) -> None:
    store = LocalArtifactStore(tmp_path)
    expired_job = uuid4()
    active_job = uuid4()
    expired_id = uuid4()
    expired = store.write(expired_job, expired_id, b"expired")
    active_id = uuid4()
    store.write(active_job, active_id, b"active")
    os.utime(expired, (1, 1))

    assert store.purge_expired(60) == 1
    assert store.resolve(active_job, active_id).read_bytes() == b"active"

    try:
        store.resolve(expired_job, expired_id)
    except ArtifactNotFoundError:
        pass
    else:
        raise AssertionError("Artefato expirado deveria ter sido removido")
