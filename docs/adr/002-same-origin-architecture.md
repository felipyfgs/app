# ADR 002 — Arquitetura same-origin (Nginx + SPA + PHP-FPM)

## Status

Aceito

## Contexto

O painel precisa de sessão autenticada com cookies, CSRF e Sanctum. Separar frontend e API em origens distintas implica CORS, cookies cross-site e maior superfície operacional. SSR não agrega valor a um painel interno autenticado.

## Decisão

- Monorepo com `backend/` (Laravel 13) e `frontend/` (Nuxt 4 SPA estática).
- Nginx no mesmo domínio: serve a SPA e encaminha `/api/*`, `/sanctum/*` e health (`/up`) ao PHP-FPM.
- Sem processo Node em produção; build do frontend gera artefatos estáticos.
- Em desenvolvimento local, Nuxt dev pode rodar em porta própria apontando a API same-site/configurada.

## Consequências

- Cookies de sessão e CSRF funcionam de forma natural (same-origin).
- Deploy simplificado (Nginx + PHP-FPM + workers).
- O frontend não embute mocks de API do template; consome apenas a API Laravel.
