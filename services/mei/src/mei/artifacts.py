from datetime import UTC, datetime, timedelta
from pathlib import Path
from typing import Protocol
from uuid import UUID


class ArtifactNotFoundError(LookupError):
    pass


class ArtifactStore(Protocol):
    def write(self, job_id: UUID, artifact_id: UUID, content: bytes) -> Path: ...

    def resolve(self, job_id: UUID, artifact_id: UUID) -> Path: ...

    def delete_job(self, job_id: UUID) -> None: ...

    def purge_expired(self, ttl_seconds: int) -> int: ...


class LocalArtifactStore:
    def __init__(self, root: Path) -> None:
        self._root = root

    def write(self, job_id: UUID, artifact_id: UUID, content: bytes) -> Path:
        directory = self._root / str(job_id)
        directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        path = directory / str(artifact_id)
        path.write_bytes(content)
        path.chmod(0o600)
        return path

    def resolve(self, job_id: UUID, artifact_id: UUID) -> Path:
        path = self._root / str(job_id) / str(artifact_id)
        if not path.is_file():
            raise ArtifactNotFoundError
        return path

    def delete_job(self, job_id: UUID) -> None:
        directory = self._root / str(job_id)
        if not directory.is_dir():
            return
        for path in directory.iterdir():
            if path.is_file():
                path.unlink(missing_ok=True)
        directory.rmdir()

    def purge_expired(self, ttl_seconds: int) -> int:
        if not self._root.is_dir():
            return 0
        cutoff = datetime.now(UTC) - timedelta(seconds=ttl_seconds)
        removed = 0
        for directory in self._root.iterdir():
            if not directory.is_dir():
                continue
            for path in directory.iterdir():
                if not path.is_file():
                    continue
                modified_at = datetime.fromtimestamp(path.stat().st_mtime, UTC)
                if modified_at <= cutoff:
                    path.unlink(missing_ok=True)
                    removed += 1
            if not any(directory.iterdir()):
                directory.rmdir()
        return removed
