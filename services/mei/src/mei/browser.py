"""Launch Chromium/Chrome alinhado ao docapi (felipyfgs/docapi).

O portal PGMEI/DASN rejeita Playwright headless puro com 13896
("Comportamento de Robô"). O docapi passa com Chrome real, headed
(via Xvfb no Linux) e --disable-blink-features=AutomationControlled.
"""

from __future__ import annotations

from typing import Any

from playwright.async_api import Browser, Playwright

from mei.config import Settings

# Mesmos args do docapi/app/browser.py
LAUNCH_ARGS = (
    "--disable-blink-features=AutomationControlled",
    "--no-sandbox",
    "--disable-dev-shm-usage",
)


def chromium_launch_kwargs(settings: Settings) -> dict[str, Any]:
    kwargs: dict[str, Any] = {
        "headless": settings.browser_headless,
        "args": list(LAUNCH_ARGS),
    }
    channel = settings.browser_channel.strip()
    if channel:
        kwargs["channel"] = channel
    return kwargs


async def launch_browser(playwright: Playwright, settings: Settings) -> Browser:
    return await playwright.chromium.launch(**chromium_launch_kwargs(settings))
