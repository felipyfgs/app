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
    artifact_root: Path = Path("/tmp/mei")  # noqa: S108 - volume efemero dedicado
    hmac_key_id: str = "laravel"
    hmac_secret: SecretStr = SecretStr("")
    hmac_max_clock_skew_seconds: int = Field(default=60, ge=10, le=300)
    hmac_nonce_ttl_seconds: int = Field(default=300, ge=60, le=900)
    live_egress_enabled: bool = False
    fixture_enabled: bool = False
    fixture_root: Path = Path("/app/fixtures")
    worker_concurrency: int = Field(default=1, ge=1, le=4)
    # docapi: Chrome headed (Xvfb no Linux). Headless puro dispara 13896 no PGMEI.
    browser_headless: bool = False
    browser_channel: str = "chrome"
    browser_timeout_ms: int = Field(default=45_000, ge=5_000, le=180_000)
    artifact_max_bytes: int = Field(default=10_485_760, ge=1_024, le=20_971_520)
    captcha_driver: str = "manual"
    captcha_budget_micros: int = Field(default=0, ge=0)
    nopecha_enabled: bool = False
    nopecha_api_key: SecretStr = SecretStr("")
    nopecha_operation_allowlist: str = ""
    nopecha_unit_cost_micros: int = Field(default=0, ge=0)
    nopecha_api_url: str = "https://api.nopecha.com/token"
    # Opcional: http://user:pass@host:port — NoPeCHA recomenda proxy = IP do submit.
    nopecha_proxy_url: str = ""
    nopecha_timeout_seconds: int = Field(default=180, ge=10, le=300)
    nopecha_poll_interval_seconds: float = Field(default=1.0, ge=0.25, le=5.0)
    captcha_resolution_ttl_seconds: int = Field(default=120, ge=30, le=300)
    pgmei_identification_url: str = (
        "https://www8.receita.fazenda.gov.br/SimplesNacional/Aplicacoes/ATSPO/"
        "pgmei.app/Identificacao"
    )
    dasn_identification_url: str = (
        "https://www8.receita.fazenda.gov.br/SimplesNacional/Aplicacoes/ATSPO/"
        "dasnsimei.app/Identificacao"
    )

    @property
    def hmac_ready(self) -> bool:
        return bool(self.hmac_key_id.strip() and self.hmac_secret.get_secret_value())

    @property
    def nopecha_allowed_operations(self) -> frozenset[str]:
        return frozenset(
            item.strip().casefold()
            for item in self.nopecha_operation_allowlist.split(",")
            if item.strip()
        )


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
