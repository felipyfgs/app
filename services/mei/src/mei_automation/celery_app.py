from celery import Celery  # type: ignore[import-untyped]

from mei_automation.config import get_settings

settings = get_settings()
celery_app = Celery("mei_automation", broker=settings.redis_url, backend=settings.redis_url)
celery_app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    task_acks_late=True,
    task_reject_on_worker_lost=True,
    worker_prefetch_multiplier=1,
    result_expires=settings.result_ttl_seconds,
    task_track_started=True,
)
celery_app.autodiscover_tasks(["mei_automation"])
