FROM python:3.12-slim AS runtime

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    DISPLAY=:99

WORKDIR /app

COPY pyproject.toml README.md ./
COPY src ./src
COPY tests/fixtures ./fixtures
COPY scripts ./scripts
COPY scripts/entrypoint.sh /entrypoint.sh

# Chrome real + Xvfb: mesmo runtime do docapi (headed sob display virtual).
RUN apt-get update && apt-get install -y --no-install-recommends \
    xvfb \
    xauth \
    wget \
    gnupg \
    ca-certificates \
    fonts-liberation \
    libnss3 \
    libxss1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libgtk-3-0 \
    libgbm1 \
    libx11-xcb1 \
    libxcomposite1 \
    libxdamage1 \
    libxrandr2 \
    libpango-1.0-0 \
    libcairo2 \
    libcups2 \
    libdrm2 \
    libdbus-1-3 \
    xdg-utils \
    && wget -q -O - https://dl.google.com/linux/linux_signing_key.pub \
        | gpg --dearmor -o /usr/share/keyrings/google.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google.gpg] http://dl.google.com/linux/chrome/deb/ stable main" \
        > /etc/apt/sources.list.d/google-chrome.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends google-chrome-stable \
    && rm -rf /var/lib/apt/lists/* \
    && pip install --no-cache-dir . \
    && playwright install --with-deps chromium \
    && useradd --create-home --uid 10001 --shell /usr/sbin/nologin mei \
    && mkdir -p /tmp/mei \
    && chmod +x /entrypoint.sh \
    && chown -R mei:mei /tmp/mei /ms-playwright

USER mei

ENTRYPOINT ["/entrypoint.sh"]
CMD ["uvicorn", "mei.api:app", "--host", "0.0.0.0", "--port", "8080"]
