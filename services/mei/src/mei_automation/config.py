from functools import lru_cache
from pathlib import Path

from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_prefix="MEI_AUTOMATION_",
        env_file=None,
        extra="ignore",
    )

    environment: str = "production"
    redis_url: str = "redis://redis:6379/4"
    result_ttl_seconds: int = Field(default=900, ge=60, le=86_400)
    artifact_ttl_seconds: int = Field(default=300, ge=60, le=3_600)
    artifact_root: Path = Path("/tmp/mei-automation")  # noqa: S108 - volume efemero dedicado
    hmac_key_id: str = "laravel"
    hmac_secret: SecretStr = SecretStr("")
    hmac_max_clock_skew_seconds: int = Field(default=60, ge=10, le=300)
    hmac_nonce_ttl_seconds: int = Field(default=300, ge=60, le=900)
    live_egress_enabled: bool = False
    worker_concurrency: int = Field(default=1, ge=1, le=4)
    browser_headless: bool = True
    browser_timeout_ms: int = Field(default=45_000, ge=5_000, le=180_000)

    @property
    def hmac_ready(self) -> bool:
        return bool(self.hmac_key_id.strip() and self.hmac_secret.get_secret_value())


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
