# AGENTS.md

## Project overview

Monorepo do hub fiscal (NFS-e / escritório): painel web, API Laravel e automação MEI/SERPRO.
Tudo roda via Docker Compose + Makefile na raiz — não trate `apps/api` ou `apps/web` como apps standalone fora do compose.

## Stack

- API: PHP 8.4, Laravel 13, Horizon, Sanctum, Fortify (`apps/api`)
- Web: Nuxt 4, Nuxt UI 4, pnpm 11.9, Node 24 (`apps/web`)
- Dados: Postgres + Redis
- Orquestração: Docker Compose; imagens em `infra/docker/`

## Layout

- `apps/api` — backend Laravel
- `apps/web` — painel Nuxt (SPA `generate` → nginx)
- `services/mei` — código de automação dos portais MEI (versionado; **não** é serviço do Compose)
- `infra/docker` — Dockerfiles e entrypoints
- `openspec/` — specs canônicas e changes

## Dev setup

- Install / primeira vez: `make setup` (env + build + composer + frontend generate + migrate + up)
- Run com HMR: `make dev` — API `:8080`, Nuxt `:3000`
- Run estático (SPA no nginx): `make up`
- Parar: `make down` | logs: `make logs` | shell PHP: `make shell`
- Migrate / seed: `make migrate` | `make seed`
- Env: copie `.env.example` → `.env` e `apps/api/.env.example` → `apps/api/.env` (`chmod 600`). Nunca versione os arquivos reais.

## Build / deploy

- Produção: `make prod-config` → `make prod-build` → `make prod-up` (`docker-compose.prod.yml`, projeto `fiscal-hub`, tag SHA)
- Parar prod: `make prod-down` (mantém volumes)
- Targets `backup`, `restore`, `prod-readiness`, `prod-release-manifest` e afins estão indisponíveis até a fase de ops — não invente uso.

## Test / lint

Espelhe o CI (`.github/workflows/ci.yml`):

**API** (`apps/api`):

- `composer validate --strict --no-check-publish`
- `vendor/bin/pint --test`
- `php artisan test`

**Web** (`apps/web`):

- `pnpm run lint`
- `pnpm run typecheck`
- `pnpm run generate`
- `pnpm run test`
- `pnpm run test:fidelity`
- `pnpm run test:artifacts`

Gate frontend no CI = lint + typecheck + generate + vitest + fidelity. Playwright E2E **não** faz parte do gate de CI.

**Infra / OpenSpec:**

- `docker compose -f docker-compose.yml config --quiet` (e equivalente prod)
- `npx @fission-ai/openspec@1.6.0 validate --specs --strict` (e changes ativas com delta)

Antes de PR: rode os gates da área que alterou (api e/ou web) e, se tocou specs/compose, a validação de infra/OpenSpec.

## Code conventions

- Locale e copy do produto: `pt_BR`
- Commits: Conventional Commits em pt-BR quando o time pedir commit
- **Antes de qualquer modificação de código/produto:** rode `/opsx-propose` (skill `openspec-propose`) e só implemente depois com `/opsx:apply` — nunca pule a change OpenSpec (proposal/design/tasks/delta)
- UI do painel: use as skills `/panel-ui` e arquétipos do dashboard — não reinvente o shell

## Architecture boundaries

- Do: orquestre MEI via API + código em `services/mei`; mantenha SERPRO/MEI fail-closed por default
- Never implemente feature, fix ou mudança de comportamento sem change OpenSpec via `/opsx-propose` (exceto pedido explícito do usuário para pular)
- Never adicione serviços `mei` ou `mei-worker` ao Compose (dev ou prod) — o CI falha se aparecerem
- Never coloque Consumer Secret, PFX ou outros segredos SERPRO em `.env.example` ou em commits
- Never commite `.env`, certs, vault, `auth.json`, nem artefatos em `apps/api/storage/app/private` / certs
- Never invente targets Make/ops marcados como indisponíveis no Makefile
- Never restaure ou reescreva histórico git de `infra/` sem pedido explícito

## Security

- Never commit secrets, certs ou `.env`
- Defaults sensíveis (SERPRO kill switch, flags MEI, SEFAZ) ficam fail-closed / off — não “abra” produção sem pedido explícito
- `VAULT_MASTER_KEY` e `APP_KEY` só em env local/prod com permissão 600

## Git / PR

- Não faça commit nem push a menos que o usuário peça
- CI: jobs Backend, Frontend e Compose/OpenSpec devem passar na área afetada

## Where else to look

- Specs: `openspec/specs/`
- Skills (workflows sob demanda; não duplicar aqui): `.codex/skills/` — openspec-*, panel-ui, ui-archetype, api-integra-contador, task-loop
- Cursor commands: `.cursor/commands/`
