import asyncio
from uuid import UUID

from redis.asyncio import Redis

from mei.celery_app import celery_app
from mei.config import get_settings
from mei.executor import JobRunner, PlaywrightOperationExecutor
from mei.store import RedisJobStore


async def _run(job_id: UUID) -> None:
    settings = get_settings()
    redis = Redis.from_url(settings.redis_url, decode_responses=False)
    try:
        store = RedisJobStore(redis, settings.result_ttl_seconds)
        record = await store.get(job_id)
        await JobRunner(store, PlaywrightOperationExecutor(settings)).run(record)
    finally:
        await redis.aclose()


@celery_app.task(name="mei.execute_job", bind=True, max_retries=0)  # type: ignore[untyped-decorator]
def execute_job(_task: object, job_id: str) -> None:
    asyncio.run(_run(UUID(job_id)))
