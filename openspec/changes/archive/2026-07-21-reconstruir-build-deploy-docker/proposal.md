## Why

A limpeza do monorepo removeu `infra/`, `services/` e scripts de ops. O stack ainda sobe em imagens Docker antigas, mas **rebuild e deploy estão quebrados** (Compose aponta para Dockerfiles inexistentes). Precisamos recriar a infra de build/deploy do zero, no padrão atual do mercado, sem restaurar histórico git e sem sidecar MEI Python — o monitoramento MEI permanece via SERPRO na API Laravel.

## What Changes

- Recriar `infra/docker/{php,nginx,frontend,traefik}` com Dockerfiles multi-stage (`base|dev|prod`).
- Reescrever `docker-compose.yml` (dev: bind mounts + HMR) sem serviços `mei`/`mei-worker`.
- Reescrever `docker-compose.prod.yml` (prod: imagens imutáveis + Traefik TLS), sem bind de código e sem MEI.
- Slim do `Makefile`: `make dev` / `make up` / `make build` locais; `make prod-up` / `make prod-down` com confirmação explícita; stub de alvos ops ausentes.
- Ajustar CI para validar Compose (dev + prod) sem checar scripts/ops deletados.
- Aceitar deletes já feitos; não restaurar `services/mei` nem ops antigos.

## Capabilities

### New Capabilities

- `docker-build-deploy`: build e orquestração Docker do monorepo (PHP-FPM, Nginx+SPA Nuxt, Postgres, Redis, Horizon, Scheduler; Traefik só em prod; comandos Make separados para dev e prod).

### Modified Capabilities

- (nenhuma spec main ativa no repositório no momento; esta change introduz a capability de infra)

## Non-Goals

- Reescrever ou reintroduzir sidecar MEI Python/Celery/browser.
- Scripts de backup/restore/deploy/readiness (fase ops posterior).
- Push/registry remoto e CD completo.
- FrankenPHP/Octane.
- Refator profunda de código Laravel `MEI_AUTOMATION_*` (pode permanecer fail-closed no `.env`).

## Impact

- Arquivos: `infra/docker/**`, `docker-compose.yml`, `docker-compose.prod.yml`, `Makefile`, `.github/workflows/ci.yml`.
- Runtime local: rebuild de imagens; volumes Postgres/Redis preservados.
- Produção: novo Compose com projeto `fiscal-hub`; MEI deixa de ser serviço de container.
- Domínio fiscal: MEI continua monitorado via SERPRO/Integra Contador na API.
