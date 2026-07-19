#!/usr/bin/env python3
"""Smoke live sanitizado: identificacao PGMEI + dividaativa (sem logar CNPJ/token).

Uso (no container mei):
  MEI_SMOKE_CNPJ=############## python /app/scripts/smoke_live_identify.py
"""

from __future__ import annotations

import asyncio
import os
import re
import sys
from pathlib import Path

# Permite rodar com PYTHONPATH=src no host ou imagem /app.
ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "src"
if str(SRC) not in sys.path:
    sys.path.insert(0, str(SRC))

from mei.browser import chromium_launch_kwargs, launch_browser  # noqa: E402
from mei.config import Settings  # noqa: E402
from mei.models import JobCreate, JobRecord  # noqa: E402
from mei.operations.pgmei import PgmeiHandler  # noqa: E402
from mei.operations.base import OperationContext  # noqa: E402
from mei.artifacts import LocalArtifactStore  # noqa: E402
from playwright.async_api import async_playwright  # noqa: E402


def _cnpj() -> str:
    raw = os.environ.get("MEI_SMOKE_CNPJ", "").strip()
    digits = re.sub(r"\D", "", raw)
    if len(digits) != 14:
        raise SystemExit("MEI_SMOKE_CNPJ deve ter 14 digitos (nao sera logado).")
    return digits


async def main() -> None:
    cnpj = _cnpj()
    settings = Settings()
    print(
        {
            "live_egress": settings.live_egress_enabled,
            "captcha_driver": settings.captcha_driver,
            "nopecha_enabled": settings.nopecha_enabled,
            "launch": {
                k: v
                for k, v in chromium_launch_kwargs(settings).items()
                if k != "args"
            },
            "args": chromium_launch_kwargs(settings)["args"],
        }
    )
    if not settings.live_egress_enabled:
        raise SystemExit("MEI_AUTOMATION_LIVE_EGRESS_ENABLED=false")

    record = JobRecord.from_create(
        JobCreate(
            operation_key="pgmei.dividaativa",
            idempotency_key="smoke:dividaativa",
            request_fingerprint="b" * 64,
            client_ref="smoke-client",
            input={"cnpj": cnpj},
        )
    )
    store = LocalArtifactStore(settings.artifact_root / "smoke")
    async with async_playwright() as playwright:
        browser = await launch_browser(playwright, settings)
        context = await browser.new_context(
            viewport={"width": 1280, "height": 720},
            locale="pt-BR",
            timezone_id="America/Sao_Paulo",
        )
        try:
            page = await context.new_page()
            page.set_default_timeout(settings.browser_timeout_ms)
            outcome = await PgmeiHandler().execute(
                page,
                record,
                OperationContext(settings, store),
            )
        finally:
            await context.close()
            await browser.close()

    print(
        {
            "status": outcome.status.value,
            "error_code": outcome.error.code if outcome.error else None,
            "captcha_driver": outcome.captcha_driver,
            "captcha_cost_micros": outcome.captcha_cost_micros,
            "has_result": outcome.result is not None,
            "years": len((outcome.result or {}).get("years", []))
            if isinstance(outcome.result, dict)
            else 0,
        }
    )


if __name__ == "__main__":
    asyncio.run(main())
