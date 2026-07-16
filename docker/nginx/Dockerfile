# syntax=docker/dockerfile:1

FROM nginx:1.27-alpine AS runtime

FROM node:24-bookworm AS web-build

WORKDIR /app

COPY --from=ui package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN corepack pnpm install --frozen-lockfile

COPY --from=ui . .

ENV NUXT_DEVTOOLS=false \
    NUXT_PUBLIC_API_BASE="" \
    NUXT_SANCTUM_PROXY=false

RUN corepack pnpm run generate

FROM runtime AS prod

COPY prod.conf /etc/nginx/conf.d/default.conf
COPY --from=web-build /app/.output/public /usr/share/nginx/html

FROM runtime AS dev

COPY app.conf /etc/nginx/conf.d/default.conf
