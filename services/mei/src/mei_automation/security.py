import hashlib
import hmac
import time
from collections.abc import Awaitable, Callable
from typing import Protocol

from fastapi import HTTPException, Request, status
from redis.asyncio import Redis

from mei_automation.config import Settings

KEY_ID_HEADER = "X-MEI-Key-Id"
TIMESTAMP_HEADER = "X-MEI-Timestamp"
NONCE_HEADER = "X-MEI-Nonce"
SIGNATURE_HEADER = "X-MEI-Signature"


class ReplayStore(Protocol):
    async def claim(self, key_id: str, nonce: str, ttl_seconds: int) -> bool: ...


class RedisReplayStore:
    def __init__(self, redis: Redis) -> None:
        self._redis = redis

    async def claim(self, key_id: str, nonce: str, ttl_seconds: int) -> bool:
        claimed = await self._redis.set(
            f"mei:replay:{key_id}:{nonce}",
            "1",
            ex=ttl_seconds,
            nx=True,
        )
        return bool(claimed)


class InMemoryReplayStore:
    def __init__(self, clock: Callable[[], float] = time.time) -> None:
        self._clock = clock
        self._nonces: dict[str, float] = {}

    async def claim(self, key_id: str, nonce: str, ttl_seconds: int) -> bool:
        now = self._clock()
        self._nonces = {key: expiry for key, expiry in self._nonces.items() if expiry > now}
        key = f"{key_id}:{nonce}"
        if key in self._nonces:
            return False
        self._nonces[key] = now + ttl_seconds
        return True


def canonical_message(
    method: str,
    path: str,
    body: bytes,
    timestamp: str,
    nonce: str,
) -> bytes:
    body_hash = hashlib.sha256(body).hexdigest()
    return f"{method.upper()}\n{path}\n{body_hash}\n{timestamp}\n{nonce}".encode()


def calculate_signature(
    secret: str,
    method: str,
    path: str,
    body: bytes,
    timestamp: str,
    nonce: str,
) -> str:
    return hmac.new(
        secret.encode(),
        canonical_message(method, path, body, timestamp, nonce),
        hashlib.sha256,
    ).hexdigest()


class HmacAuthenticator:
    def __init__(
        self,
        settings: Settings,
        replay_store: ReplayStore,
        clock: Callable[[], float] = time.time,
    ) -> None:
        self._settings = settings
        self._replay_store = replay_store
        self._clock = clock

    async def authenticate(self, request: Request) -> None:
        if not self._settings.hmac_ready:
            raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "HMAC nao configurado")

        key_id = request.headers.get(KEY_ID_HEADER, "")
        timestamp = request.headers.get(TIMESTAMP_HEADER, "")
        nonce = request.headers.get(NONCE_HEADER, "")
        supplied = request.headers.get(SIGNATURE_HEADER, "")

        if key_id != self._settings.hmac_key_id or not timestamp.isdigit():
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Assinatura invalida")
        if len(nonce) < 16 or len(nonce) > 120 or len(supplied) != 64:
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Assinatura invalida")

        if abs(self._clock() - int(timestamp)) > self._settings.hmac_max_clock_skew_seconds:
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Assinatura expirada")

        body = await request.body()
        expected = calculate_signature(
            self._settings.hmac_secret.get_secret_value(),
            request.method,
            request.url.path,
            body,
            timestamp,
            nonce,
        )
        if not hmac.compare_digest(expected, supplied):
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Assinatura invalida")

        if not await self._replay_store.claim(
            key_id,
            nonce,
            self._settings.hmac_nonce_ttl_seconds,
        ):
            raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Nonce reutilizado")


async def require_hmac(request: Request) -> None:
    authenticator: HmacAuthenticator = request.app.state.authenticator
    await authenticator.authenticate(request)


HmacDependency = Callable[[Request], Awaitable[None]]
