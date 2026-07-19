import asyncio
from datetime import UTC, datetime
from typing import Protocol
from uuid import UUID

from redis.asyncio import Redis

from mei.models import JobCreate, JobRecord


class JobNotFoundError(LookupError):
    pass


class IdempotencyConflictError(RuntimeError):
    pass


class JobStore(Protocol):
    async def create_or_get(self, payload: JobCreate) -> tuple[JobRecord, bool]: ...

    async def get(self, job_id: UUID) -> JobRecord: ...

    async def save(self, record: JobRecord) -> JobRecord: ...

    async def ping(self) -> bool: ...


class InMemoryJobStore:
    def __init__(self) -> None:
        self._records: dict[UUID, JobRecord] = {}
        self._idempotency: dict[str, UUID] = {}
        self._lock = asyncio.Lock()

    async def create_or_get(self, payload: JobCreate) -> tuple[JobRecord, bool]:
        async with self._lock:
            existing_id = self._idempotency.get(payload.idempotency_key)
            if existing_id is not None:
                existing = self._records[existing_id]
                if existing.request_fingerprint != payload.request_fingerprint:
                    raise IdempotencyConflictError
                return existing.model_copy(deep=True), False

            record = JobRecord.from_create(payload)
            self._records[record.id] = record
            self._idempotency[payload.idempotency_key] = record.id
            return record.model_copy(deep=True), True

    async def get(self, job_id: UUID) -> JobRecord:
        record = self._records.get(job_id)
        if record is None:
            raise JobNotFoundError
        return record.model_copy(deep=True)

    async def save(self, record: JobRecord) -> JobRecord:
        async with self._lock:
            if record.id not in self._records:
                raise JobNotFoundError
            record.updated_at = datetime.now(UTC)
            self._records[record.id] = record.model_copy(deep=True)
            return record.model_copy(deep=True)

    async def ping(self) -> bool:
        return True


class RedisJobStore:
    def __init__(self, redis: Redis, ttl_seconds: int) -> None:
        self._redis = redis
        self._ttl_seconds = ttl_seconds

    def _job_key(self, job_id: UUID) -> str:
        return f"mei:job:{job_id}"

    def _idempotency_key(self, value: str) -> str:
        return f"mei:idempotency:{value}"

    @staticmethod
    def _uuid(raw: bytes | str) -> UUID:
        return UUID(raw.decode() if isinstance(raw, bytes) else raw)

    async def create_or_get(self, payload: JobCreate) -> tuple[JobRecord, bool]:
        index_key = self._idempotency_key(payload.idempotency_key)
        existing_id = await self._redis.get(index_key)
        if existing_id is not None:
            record = await self.get(self._uuid(existing_id))
            if record.request_fingerprint != payload.request_fingerprint:
                raise IdempotencyConflictError
            return record, False

        record = JobRecord.from_create(payload)
        claimed = await self._redis.set(index_key, str(record.id), ex=self._ttl_seconds, nx=True)
        if not claimed:
            existing_id = await self._redis.get(index_key)
            if existing_id is None:
                return await self.create_or_get(payload)
            record = await self.get(self._uuid(existing_id))
            if record.request_fingerprint != payload.request_fingerprint:
                raise IdempotencyConflictError
            return record, False

        await self._redis.set(
            self._job_key(record.id),
            record.model_dump_json(),
            ex=self._ttl_seconds,
        )
        return record, True

    async def get(self, job_id: UUID) -> JobRecord:
        raw = await self._redis.get(self._job_key(job_id))
        if raw is None:
            raise JobNotFoundError
        return JobRecord.model_validate_json(raw)

    async def save(self, record: JobRecord) -> JobRecord:
        if not await self._redis.exists(self._job_key(record.id)):
            raise JobNotFoundError
        record.updated_at = datetime.now(UTC)
        await self._redis.set(
            self._job_key(record.id),
            record.model_dump_json(),
            ex=self._ttl_seconds,
        )
        await self._redis.expire(self._idempotency_key(record.idempotency_key), self._ttl_seconds)
        return record

    async def ping(self) -> bool:
        return bool(await self._redis.ping())
