import asyncio
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import cast
from uuid import UUID

from fastapi import Depends, FastAPI, HTTPException, Response, status
from fastapi.responses import FileResponse
from redis.asyncio import Redis

from mei.artifacts import (
    ArtifactNotFoundError,
    ArtifactStore,
    LocalArtifactStore,
)
from mei.celery_app import celery_app
from mei.config import Settings, get_settings
from mei.dispatcher import CeleryJobDispatcher, JobDispatcher
from mei.models import HealthResponse, JobCreate, JobRecord, JobResponse, JobStatus
from mei.security import HmacAuthenticator, RedisReplayStore, require_hmac
from mei.store import (
    IdempotencyConflictError,
    JobNotFoundError,
    JobStore,
    RedisJobStore,
)


def create_app(
    settings: Settings | None = None,
    store: JobStore | None = None,
    authenticator: HmacAuthenticator | None = None,
    dispatcher: JobDispatcher | None = None,
    artifact_store: ArtifactStore | None = None,
) -> FastAPI:
    resolved_settings = settings or get_settings()
    injected = store is not None and authenticator is not None and dispatcher is not None

    @asynccontextmanager
    async def lifespan(app: FastAPI) -> AsyncIterator[None]:
        redis: Redis | None = None
        cleanup_task: asyncio.Task[None] | None = None
        if injected:
            app.state.store = store
            app.state.authenticator = authenticator
            app.state.dispatcher = dispatcher
        else:
            redis = Redis.from_url(resolved_settings.redis_url, decode_responses=False)
            app.state.store = RedisJobStore(redis, resolved_settings.result_ttl_seconds)
            app.state.authenticator = HmacAuthenticator(
                resolved_settings,
                RedisReplayStore(redis),
            )
            app.state.dispatcher = CeleryJobDispatcher(celery_app)
        app.state.artifact_store = artifact_store or LocalArtifactStore(
            resolved_settings.artifact_root
        )
        cleanup_task = asyncio.create_task(
            _artifact_cleanup_loop(app, resolved_settings.artifact_ttl_seconds)
        )
        try:
            yield
        finally:
            cleanup_task.cancel()
            await asyncio.gather(cleanup_task, return_exceptions=True)
            if redis is not None:
                await redis.aclose()

    app = FastAPI(
        title="MEI Service",
        version="0.1.0",
        docs_url=None,
        redoc_url=None,
        lifespan=lifespan,
    )
    app.state.settings = resolved_settings

    @app.get("/health/live", response_model=HealthResponse)
    async def live() -> HealthResponse:
        return HealthResponse(status="ok")

    @app.get("/health/ready", response_model=HealthResponse)
    async def ready() -> HealthResponse:
        redis_ready = False
        try:
            redis_ready = await _store(app).ping()
        except Exception:  # noqa: BLE001 - readiness nao expoe detalhe de infraestrutura
            redis_ready = False
        ready_now = redis_ready and resolved_settings.hmac_ready
        if not ready_now:
            raise HTTPException(
                status.HTTP_503_SERVICE_UNAVAILABLE,
                detail={
                    "status": "not_ready",
                    "redis": redis_ready,
                    "hmac_ready": resolved_settings.hmac_ready,
                },
            )
        return HealthResponse(status="ok", redis=True, hmac_ready=True)

    @app.post(
        "/v1/jobs",
        response_model=JobResponse,
        status_code=status.HTTP_202_ACCEPTED,
        dependencies=[Depends(require_hmac)],
    )
    async def create_job(payload: JobCreate, response: Response) -> JobResponse:
        try:
            record, created = await _store(app).create_or_get(payload)
        except IdempotencyConflictError as error:
            raise HTTPException(status.HTTP_409_CONFLICT, "Idempotency-Key em conflito") from error
        if created:
            _dispatcher(app).dispatch(record.id)
        else:
            response.status_code = status.HTTP_200_OK
        return record.public()

    @app.get(
        "/v1/jobs/{job_id}",
        response_model=JobResponse,
        dependencies=[Depends(require_hmac)],
    )
    async def get_job(job_id: UUID) -> JobResponse:
        return (await _get_job(app, job_id)).public()

    @app.delete(
        "/v1/jobs/{job_id}",
        response_model=JobResponse,
        dependencies=[Depends(require_hmac)],
    )
    async def cancel_job(job_id: UUID) -> JobResponse:
        record = await _get_job(app, job_id)
        if not record.status.terminal:
            record.status = JobStatus.CANCELLED
            record = await _store(app).save(record)
            _dispatcher(app).cancel(job_id)
        return record.public()

    @app.post(
        "/v1/jobs/{job_id}/resume",
        response_model=JobResponse,
        status_code=status.HTTP_202_ACCEPTED,
        dependencies=[Depends(require_hmac)],
    )
    async def resume_job(job_id: UUID) -> JobResponse:
        record = await _get_job(app, job_id)
        if record.status != JobStatus.WAITING_USER_ACTION:
            raise HTTPException(status.HTTP_409_CONFLICT, "Job nao aguarda acao humana")
        record.status = JobStatus.QUEUED
        record.action_type = None
        record = await _store(app).save(record)
        _dispatcher(app).dispatch(job_id)
        return record.public()

    @app.get(
        "/v1/jobs/{job_id}/artifacts/{artifact_id}",
        response_class=FileResponse,
        dependencies=[Depends(require_hmac)],
    )
    async def download_artifact(job_id: UUID, artifact_id: UUID) -> FileResponse:
        record = await _get_job(app, job_id)
        descriptor = next(
            (artifact for artifact in record.artifacts if artifact.id == artifact_id),
            None,
        )
        if descriptor is None:
            raise HTTPException(status.HTTP_404_NOT_FOUND, "Artefato nao encontrado")
        try:
            path = _artifact_store(app).resolve(job_id, artifact_id)
        except ArtifactNotFoundError as error:
            raise HTTPException(status.HTTP_410_GONE, "Artefato expirado") from error
        return FileResponse(
            path,
            media_type=descriptor.content_type,
            filename=descriptor.name,
            headers={"Cache-Control": "private, no-store, max-age=0"},
        )

    return app


def _store(app: FastAPI) -> JobStore:
    return cast(JobStore, app.state.store)


def _dispatcher(app: FastAPI) -> JobDispatcher:
    return cast(JobDispatcher, app.state.dispatcher)


def _artifact_store(app: FastAPI) -> ArtifactStore:
    return cast(ArtifactStore, app.state.artifact_store)


async def _get_job(app: FastAPI, job_id: UUID) -> JobRecord:
    try:
        return await _store(app).get(job_id)
    except JobNotFoundError as error:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Job nao encontrado") from error


async def _artifact_cleanup_loop(app: FastAPI, ttl_seconds: int) -> None:
    interval_seconds = max(30, min(300, ttl_seconds // 2))
    while True:
        _artifact_store(app).purge_expired(ttl_seconds)
        await asyncio.sleep(interval_seconds)


app = create_app()
