import asyncio
from collections.abc import Sequence
from dataclasses import dataclass
from datetime import UTC, datetime, timedelta
from enum import StrEnum
from typing import Any, Protocol
from unicodedata import combining, normalize

import httpx
from playwright.async_api import Page

from mei.config import Settings

_PUBLIC_CAPTCHA_REJECTION_MARKERS = (
    "13896",
    "impedido por protecao captcha",
    "comportamento de robo",
)


@dataclass(frozen=True, slots=True)
class CaptchaResolution:
    solved: bool
    expires_at: datetime | None = None
    driver: str | None = None
    cost_micros: int = 0

    def valid(self, now: datetime | None = None) -> bool:
        instant = now or datetime.now(UTC)
        return self.solved and self.expires_at is not None and self.expires_at > instant


class CaptchaState(StrEnum):
    ABSENT = "absent"
    INTEGRATION_READY = "integration_ready"
    CHALLENGE_ACTIVE = "challenge_active"


class IdentificationStatus(StrEnum):
    AUTO_APPROVED = "auto_approved"
    VALIDATION_REJECTED = "validation_rejected"
    CAPTCHA_EXHAUSTED = "captcha_exhausted"
    PORTAL_DRIFT = "portal_drift"


@dataclass(frozen=True, slots=True)
class IdentificationOutcome:
    status: IdentificationStatus
    captcha: CaptchaResolution = CaptchaResolution(solved=False)

    @property
    def succeeded(self) -> bool:
        return self.status is IdentificationStatus.AUTO_APPROVED


class CaptchaSolver(Protocol):
    async def solve(self, page: Page) -> CaptchaResolution: ...


class ManualCaptchaSolver:
    async def solve(self, page: Page) -> CaptchaResolution:
        return CaptchaResolution(solved=False)


class NoPechaCaptchaSolver:
    def __init__(
        self,
        enabled: bool,
        budget_micros: int,
        ttl_seconds: int,
        *,
        api_key: str = "",
        operation_allowed: bool = False,
        unit_cost_micros: int = 0,
        api_url: str = "https://api.nopecha.com/token",
        timeout_seconds: int = 180,
        poll_interval_seconds: float = 1.0,
        proxy: dict[str, str] | None = None,
        transport: httpx.AsyncBaseTransport | None = None,
    ) -> None:
        self._enabled = enabled
        self._budget_micros = budget_micros
        self._ttl_seconds = ttl_seconds
        self._api_key = api_key
        self._operation_allowed = operation_allowed
        self._unit_cost_micros = unit_cost_micros
        self._api_url = api_url
        self._timeout_seconds = timeout_seconds
        self._poll_interval_seconds = poll_interval_seconds
        self._proxy = proxy
        self._transport = transport

    async def solve(self, page: Page) -> CaptchaResolution:
        if (
            not self._enabled
            or not self._operation_allowed
            or not self._api_key
            or self._unit_cost_micros <= 0
            or self._unit_cost_micros > self._budget_micros
        ):
            return CaptchaResolution(solved=False)

        site_key = await page.locator("[data-sitekey]").first.get_attribute("data-sitekey")
        if not site_key:
            return CaptchaResolution(solved=False)
        user_agent = await page.evaluate("() => navigator.userAgent")
        rqdata = await page.evaluate(
            """
            () => {
              const widget = document.querySelector('.h-captcha, [data-sitekey]')
              return widget?.getAttribute('data-rqdata')
                || widget?.dataset?.rqdata
                || null
            }
            """
        )

        try:
            async with httpx.AsyncClient(
                transport=self._transport,
                timeout=httpx.Timeout(30.0),
            ) as client:
                job_id = await self._submit(
                    client,
                    page.url,
                    site_key,
                    user_agent=user_agent if isinstance(user_agent, str) else None,
                    rqdata=rqdata if isinstance(rqdata, str) and rqdata else None,
                )
                token = await self._poll(client, job_id)
            if token is None or not await self._inject(page, token):
                return CaptchaResolution(solved=False)
        except (httpx.HTTPError, TypeError, ValueError):
            return CaptchaResolution(solved=False)

        return CaptchaResolution(
            solved=True,
            expires_at=datetime.now(UTC) + timedelta(seconds=self._ttl_seconds),
            driver="nopecha",
            cost_micros=self._unit_cost_micros,
        )

    async def _submit(
        self,
        client: httpx.AsyncClient,
        page_url: str,
        site_key: str,
        *,
        user_agent: str | None,
        rqdata: str | None,
    ) -> str:
        payload: dict[str, Any] = {
            "key": self._api_key,
            "type": "hcaptcha",
            "sitekey": site_key,
            "url": page_url,
        }
        if user_agent:
            payload["useragent"] = user_agent
        if self._proxy:
            payload["proxy"] = self._proxy
        if rqdata:
            payload["data"] = {"rqdata": rqdata}
        response = await client.post(self._api_url, json=payload)
        response.raise_for_status()
        body = self._object(response.json())
        job_id = body.get("data")
        if not isinstance(job_id, str) or not job_id:
            raise ValueError("NoPeCHA nao retornou identificador de job")
        return job_id

    async def _poll(self, client: httpx.AsyncClient, job_id: str) -> str | None:
        loop = asyncio.get_running_loop()
        deadline = loop.time() + self._timeout_seconds
        while loop.time() < deadline:
            response = await client.get(
                self._api_url,
                params={"key": self._api_key, "id": job_id},
            )
            payload = self._object(response.json())
            token = payload.get("data")
            if response.status_code == 200 and isinstance(token, str) and len(token) >= 64:
                return token
            if response.status_code != 409 and payload.get("error") != 14:
                return None
            await asyncio.sleep(self._poll_interval_seconds)
        return None

    @staticmethod
    async def _inject(page: Page, token: str) -> bool:
        # Fluxo canonico (NoneCap/docapi): campos + setResponse + data-callback.
        injected = await page.evaluate(
            """
            (captchaToken) => {
              const form = document.querySelector('form') || document.body
              for (const name of ['h-captcha-response', 'g-recaptcha-response']) {
                if (!document.querySelector(`[name="${name}"]`)) {
                  const field = document.createElement('textarea')
                  field.name = name
                  field.style.display = 'none'
                  form.appendChild(field)
                }
              }
              const fields = document.querySelectorAll(
                'textarea[name="h-captcha-response"], input[name="h-captcha-response"],'
                + ' textarea[name="g-recaptcha-response"], input[name="g-recaptcha-response"]'
              )
              for (const field of fields) {
                field.value = captchaToken
                field.textContent = captchaToken
                field.dispatchEvent(new Event('input', { bubbles: true }))
                field.dispatchEvent(new Event('change', { bubbles: true }))
              }
              if (window.hcaptcha) {
                try {
                  const ids = window.hcaptcha.getAllIds ? window.hcaptcha.getAllIds() : []
                  for (const id of ids) {
                    if (window.hcaptcha.setResponse) {
                      window.hcaptcha.setResponse(id, captchaToken)
                    }
                  }
                } catch (_) {}
              }
              const widget = document.querySelector('.h-captcha[data-callback], [data-callback]')
              const callbackName = widget && widget.getAttribute('data-callback')
              if (callbackName && typeof window[callbackName] === 'function') {
                window[callbackName](captchaToken)
              }
              const iframe = document.querySelector('iframe[data-hcaptcha-widget-id]')
              if (iframe) iframe.setAttribute('data-hcaptcha-response', captchaToken)
              const primary = document.querySelector(
                'textarea[name="h-captcha-response"], input[name="h-captcha-response"]'
              )
              return Boolean(primary && primary.value === captchaToken)
            }
            """,
            token,
        )
        return injected is True

    @staticmethod
    def _object(value: Any) -> dict[str, Any]:
        if not isinstance(value, dict):
            raise TypeError("Resposta NoPeCHA invalida")
        return value


def captcha_solver(settings: Settings, operation_key: str) -> CaptchaSolver:
    if settings.captcha_driver.casefold() == "nopecha":
        return NoPechaCaptchaSolver(
            settings.nopecha_enabled,
            settings.captcha_budget_micros,
            settings.captcha_resolution_ttl_seconds,
            api_key=settings.nopecha_api_key.get_secret_value(),
            operation_allowed=operation_key.casefold() in settings.nopecha_allowed_operations,
            unit_cost_micros=settings.nopecha_unit_cost_micros,
            api_url=settings.nopecha_api_url,
            timeout_seconds=settings.nopecha_timeout_seconds,
            poll_interval_seconds=settings.nopecha_poll_interval_seconds,
            proxy=_nopecha_proxy(settings.nopecha_proxy_url),
        )
    return ManualCaptchaSolver()


def _nopecha_proxy(raw: str) -> dict[str, str] | None:
    value = raw.strip()
    if not value:
        return None
    from urllib.parse import urlparse

    parsed = urlparse(value)
    if not parsed.hostname or not parsed.port or not parsed.scheme:
        return None
    proxy: dict[str, str] = {
        "scheme": parsed.scheme,
        "host": parsed.hostname,
        "port": str(parsed.port),
    }
    if parsed.username:
        proxy["username"] = parsed.username
    if parsed.password:
        proxy["password"] = parsed.password
    return proxy


async def captcha_state(page: Page) -> CaptchaState:
    challenge_selectors = [
        "iframe[title*='main content of the hcaptcha challenge' i]",
        "iframe[title*='desafio' i]",
        "iframe[src*='hcaptcha-challenge' i]",
        "[data-hcaptcha-challenge]",
        "[data-hcaptcha-error]",
        ".hcaptcha-challenge",
        ".h-captcha[aria-invalid='true']",
    ]
    for selector in challenge_selectors:
        candidates = page.locator(selector)
        for index in range(await candidates.count()):
            if await candidates.nth(index).is_visible():
                return CaptchaState.CHALLENGE_ACTIVE

    integration_selectors = [
        ".h-captcha",
        "textarea[name='h-captcha-response']",
        "input[name='h-captcha-response']",
        "iframe[src*='hcaptcha.com']",
        "iframe[src*='newassets.hcaptcha.com']",
        "iframe[title*='hCaptcha']",
    ]
    for selector in integration_selectors:
        if await page.locator(selector).count() > 0:
            return CaptchaState.INTEGRATION_READY
    return CaptchaState.ABSENT


async def submit_identification_once(
    page: Page,
    *,
    button_selector: str,
    success_selectors: Sequence[str],
    validation_selectors: Sequence[str],
    solver: CaptchaSolver,
    timeout_ms: int,
    poll_interval_ms: int = 50,
    presolve: bool = False,
) -> IdentificationOutcome:
    loop = asyncio.get_running_loop()
    deadline = loop.time() + (timeout_ms / 1_000)
    resolution = CaptchaResolution(solved=False)
    solver_called = False
    challenge_seen = False
    # Apos inject+re-clique, ignora o banner 13896 residual ate o portal responder.
    ignore_rejection_until = 0.0

    # NoneCap: token out-of-band ANTES do Continuar quando NoPeCHA esta autorizado.
    if presolve and await captcha_state(page) is not CaptchaState.ABSENT:
        resolution = await solver.solve(page)
        if resolution.valid():
            solver_called = True

    await page.locator(button_selector).first.click()

    while loop.time() < deadline:
        if await _any_visible(page, success_selectors):
            return IdentificationOutcome(IdentificationStatus.AUTO_APPROVED, resolution)

        if await _has_public_captcha_rejection(page):
            if loop.time() < ignore_rejection_until:
                await asyncio.sleep(poll_interval_ms / 1_000)
                continue
            challenge_seen = True
            if solver_called:
                return IdentificationOutcome(IdentificationStatus.CAPTCHA_EXHAUSTED, resolution)
            solver_called = True
            resolution = await solver.solve(page)
            if not resolution.valid():
                return IdentificationOutcome(
                    IdentificationStatus.CAPTCHA_EXHAUSTED,
                    resolution,
                )
            await _dismiss_public_captcha_rejection(page)
            # Token out-of-band: um unico re-clique apos inject (fluxo NoPeCHA/NoneCap).
            await page.locator(button_selector).first.click()
            ignore_rejection_until = loop.time() + min(8.0, timeout_ms / 1_000)
            continue

        if await _any_visible(page, validation_selectors):
            return IdentificationOutcome(IdentificationStatus.VALIDATION_REJECTED, resolution)

        if await captcha_state(page) is CaptchaState.CHALLENGE_ACTIVE:
            challenge_seen = True
            if not solver_called:
                solver_called = True
                resolution = await solver.solve(page)
                if not resolution.valid():
                    return IdentificationOutcome(
                        IdentificationStatus.CAPTCHA_EXHAUSTED,
                        resolution,
                    )
        await asyncio.sleep(poll_interval_ms / 1_000)

    status = (
        IdentificationStatus.CAPTCHA_EXHAUSTED
        if challenge_seen
        else IdentificationStatus.PORTAL_DRIFT
    )
    return IdentificationOutcome(status, resolution)


async def _has_public_captcha_rejection(page: Page) -> bool:
    body = page.locator("body")
    if await body.count() == 0:
        return False
    visible_text = await body.inner_text()
    return _normalized_contains_captcha_rejection(visible_text)


async def _dismiss_public_captcha_rejection(page: Page) -> None:
    """Remove banner 13896 residual para nao abortar o re-clique imediato."""
    await page.evaluate(
        """
        () => {
          const markers = ['13896', 'impedido por protecao captcha', 'comportamento de robo']
          const normalize = (text) => text
            .normalize('NFKD')
            .replace(/[\\u0300-\\u036f]/g, '')
            .toLowerCase()
            .replace(/\\s+/g, ' ')
            .trim()
          for (const el of document.querySelectorAll(
            '.alert, .alert-danger, .toast, .toast-message, [role="alert"], [role="alertdialog"]'
          )) {
            const normalized = normalize(el.innerText || '')
            if (markers.every((marker) => normalized.includes(marker))) {
              el.remove()
            }
          }
        }
        """
    )


def _normalized_contains_captcha_rejection(visible_text: str) -> bool:
    decomposed = normalize("NFKD", visible_text)
    normalized = " ".join(
        "".join(character for character in decomposed if not combining(character))
        .casefold()
        .split()
    )
    return all(marker in normalized for marker in _PUBLIC_CAPTCHA_REJECTION_MARKERS)


async def _any_visible(page: Page, selectors: Sequence[str]) -> bool:
    for selector in selectors:
        candidates = page.locator(selector)
        for index in range(await candidates.count()):
            if await candidates.nth(index).is_visible():
                return True
    return False
