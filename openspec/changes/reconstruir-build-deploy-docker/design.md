## Context

O monorepo (Laravel API + Nuxt 4 SPA) perdeu `infra/` e `services/` na limpeza. Containers ainda rodam em imagens antigas; Compose não consegue rebuild. Esta change recria build/deploy do zero alinhado a práticas atuais (Docker multi-stage, Compose separado, SPA same-origin), com MEI só via SERPRO.

## Goals / Non-Goals

**Goals:**

- Dev com bind mounts + HMR (`make dev`) sem rebuild a cada edição.
- Prod com imagens imutáveis + Traefik TLS (`make prod-up`).
- Migration antes de `web`/`horizon`/`scheduler`.
- Uma imagem PHP reutilizada por php / horizon / scheduler.
- Nginx same-origin: SPA estática + `/api` `/sanctum` `/up` → PHP-FPM.
- Sem sidecar MEI Python no Compose.

**Non-Goals:**

- Sidecar MEI, ops de backup/restore, CD/registry, Octane/FrankenPHP.
- Limpeza de domínio `MEI_AUTOMATION_*` na API (só fail-closed).

## Decisions

### 1. Compose separado em vez de “um arquivo com APP_ENV”

Dev e prod diferem em volumes, TLS, portas e imutabilidade. Usar `docker-compose.yml` + `docker-compose.prod.yml` e projetos distintos (`app` vs `fiscal-hub`) evita misturar stacks.

### 2. PHP-FPM + Nginx (não FrankenPHP)

Padrão previsível com SPA estática e Sanctum same-origin. Uma imagem PHP, `command` diferente para Horizon/Scheduler.

### 3. SPA Nuxt estática baked no Nginx (prod); HMR em container separado (dev)

- Prod: stage `spa-builder` (`pnpm generate`) → cópia para `nginx:alpine`.
- Dev: profile `dev` com `frontend-dev` (Node/pnpm) + bind `./apps/web`; API em `:8080`, UI HMR em `:3000`.

### 4. MEI fora do Compose

Monitoramento MEI permanece via Integra Contador/SERPRO na API Laravel. Sem `mei`/`mei-worker` (nem profile).

### 5. Traefik só em produção

Let's Encrypt HTTP challenge; Docker socket via socket-proxy; Nginx (`web`) atrás do Traefik sem publicar 8080 na internet.

### 6. Makefile simples, gates no prod-check

`make prod-up` / `make prod-down` rodam direto (sem `CONFIRM_*=SIM`). Segurança operacional fica no `prod-check` (SMTP, contenção SERPRO/features, MEI automation off) e na ordem migrate-before-traffic. Alvos de ops deletados viram stub.

### 7. Não restaurar do git history

Dockerfiles e configs escritos novos sob `infra/docker/`, baseados em docs oficiais Docker/Nuxt — não recuperar o `infra/` apagado.

## Architecture

```text
Browser
  │
  ├─ make dev ──► :3000 frontend-dev (HMR) ──proxy Sanctum──► nginx:8080 ──► php:9000
  │                                                      └─ /api /sanctum /up
  │
  └─ make prod-up ──► Traefik :443 ──► web (nginx+SPA) ──► php / horizon / scheduler
                         │
                         └─ postgres + redis (volumes nomeados)
```

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Rebuild demorado na primeira vez | Multi-stage + cache de layers composer/pnpm |
| Imagens antigas MEI órfãs no host | Não sobem no Compose novo; prune manual opcional |
| CI sem build completo de imagens | Gate mínimo = `compose config`; build pesado pode voltar depois |
| Prod sem scripts de backup | Stub explícito; fase ops posterior |
| Código ainda referencia MEI sidecar | Env fail-closed; change de domínio separada |

## Migration Plan

1. Escrever Dockerfiles/confs novos.
2. Reescrever Compose + Makefile + CI.
3. `docker compose build` + `make down && make up` (preservar volumes DB).
4. Validar `/up`, SPA, `make dev`.
5. Prod: só após `prod-config` com `.env` válido; não migrar dados nesta change.

## Open Questions

- Nenhum bloqueante: domínio ACME e host prod já existem no Compose atual (`app.inovaicontabil.com.br`).
