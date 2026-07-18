# AGENTS.md

Hub fiscal multi-escritório (NFS-e ADN, SEFAZ, SERPRO Integra Contador, monitoramento).
Monorepo orquestrado por **Makefile + Docker Compose** — sem `package.json` na raiz.

## Idioma

- **Sempre** comunicar com o usuário em **português do Brasil (pt-BR)** — chat, resumos, commits pedidos pelo user, PRs e explicações.
- Identificadores de código, paths, APIs e termos de domínio técnico permanecem como no código (ex.: `CurrentOffice`, `office_id`).
- Artefatos **OpenSpec** (proposal, design, specs, tasks, archive) **sempre em pt-BR** — ver `openspec/config.yaml`.

## Layout

| Path | Papel |
|------|--------|
| `backend/` | Laravel 13 / PHP 8.4 / Horizon / Sanctum / Fortify |
| `frontend/` | Nuxt 4 SPA (`ssr: false`) + Nuxt UI + pnpm 11.9 |
| `docker/` | Imagens, nginx, ops (backup/restore/deploy/readiness) |
| `docker-compose.yml` | Stack local (nginx, php, postgres, redis, horizon, scheduler) |
| `compose.prod.yml` | Produção (Traefik HTTPS, tags SHA) |
| `openspec/` | Specs permanentes + changes ativas (`config.yaml`) |
| `.grok/skills/` | Skills de projeto (OpenSpec, panel-ui, SERPRO, task-loop) |
| `.reference/nuxt-dashboard-template` | Arquétipo UI fixado em `0f30c09` |

## Comandos

```bash
# Bootstrap (uma vez)
make init-env          # .env + backend/.env + APP_KEY/VAULT_MASTER_KEY
make setup             # build, composer, SPA generate, migrate, sobe stack

# Dia a dia
make up                # stack sem HMR
make dev               # stack + Nuxt HMR em :3000 (perfil dev)
make down
make migrate
make seed / seed-dev   # exemplos sintéticos (DatabaseSeeder)
make seed-pilot        # piloto real de dados/ (PilotSeeder) — Felipe/Gustavo
make shell-php
make logs

# Backend (no host com vendor, ou via container)
cd backend && vendor/bin/pint --test
cd backend && php artisan test
# ou: docker compose exec php php artisan test

# Frontend
cd frontend && pnpm install --frozen-lockfile
cd frontend && pnpm run test:gate    # lint + typecheck + vitest
cd frontend && pnpm run generate
cd frontend && pnpm run test:fidelity
cd frontend && pnpm run test:artifacts

# OpenSpec (CLI via npx; CI usa @fission-ai/openspec@1.6.0)
npx openspec validate --specs --strict
```

Produção (destrutivo — exige `CONFIRM_*=SIM`): `make prod-config`, `prod-build`, `prod-up`, `prod-backup`, `prod-restore`, `prod-readiness PHASE=...`. Ver `make help`.

## Runtime

- **Ports:** app `APP_PORT` (default 8080), frontend dev `FRONTEND_DEV_PORT` (3000), Postgres/Redis em 127.0.0.1.
- **Env:** raiz `.env` (compose) + `backend/.env` (Laravel). Exemplos: `.env.example`, `backend/.env.example`, `.env.prod.example`.
- **Auth SPA:** Sanctum same-origin; `SANCTUM_STATEFUL_DOMAINS` deve incluir host:porta do browser (ex. IP:3000 remoto).
- **HMR remoto:** `PUBLIC_HOST` / `NUXT_DEV_HMR_HOST`; `CHOKIDAR_USEPOLLING=true` no compose (bind-mount).
- **Queues:** Redis + Horizon (`horizon` service) + `scheduler`.
- **Vault:** `VAULT_MASTER_KEY` (32 bytes base64) + `VAULT_DISK_ROOT`; fora do banco e de backups comuns.
- **Testes backend:** PHPUnit em sqlite `:memory:`; suites Unit / Feature / Architecture.

## Domínio / tenancy / segurança

- Tenant = **Office**. Autoridade: `App\Support\CurrentOffice` — **nunca** confiar `office_id` do client (`RejectClientOfficeId`).
- **PLATFORM_ADMIN** não tem acesso fiscal implícito a tenants. Contexto privilegiado: flag `features.platform_privileged_context` (default OFF) + sessão `platform_selected_office_id` (não reutilizar `current_office_id`).
- Feature flags (`FeatureFlags` / `config/features.php`): defaults **OFF**; kill switch global vence; allowlist vazia = ninguém (salvo `allow_all_offices`).
- **SERPRO:** fail-closed — `SERPRO_USE_FAKE_CLIENTS=true`, `SERPRO_CAPABILITY_*=disabled|simulated`, `SERPRO_KILL_SWITCH`. Nunca commitar Consumer Secret/PFX. Skill: `.grok/skills/api-integra-contador/`.
- Cofre: `SecureObjectStore` — sem PFX, tokens, XML ou segredos em JSON de API/logs.
- Canais SEFAZ outbound (MA/SVRS/AutXML): flags default off + kill switches — ver comentários em `backend/.env.example` e `docs/ops/` se existir.
- 2FA: `AUTH_TWO_FACTOR_REQUIRED=true` fora de dev; testes setam false.

## Backend / Frontend

**Backend**

- PHP 8.4, Laravel 13, Horizon, Fortify, Sanctum.
- Gate CI: `composer validate --strict` · `pint --test` · `php artisan test`.
- Domínio/serviços em `app/Services`, contratos em `app/Contracts`, jobs em `app/Jobs`.

**Frontend**

- Nuxt 4 SPA estática (`nitro.preset: static`), Nuxt UI v4, `nuxt-auth-sanctum`, pnpm.
- Gate CI: `lint` · `typecheck` · `generate` · `test` · `test:fidelity` · `test:artifacts`.
- E2E Playwright **removido** (`test:e2e` falha de propósito); cobertura em `tests/unit/**`.
- UI: skill `panel-ui` → `ui-archetype`; template `.reference/nuxt-dashboard-template` @ `0f30c09`.

## OpenSpec / skills locais

- Ciclo: **propose → apply → verify → archive** (+ commit no mesmo dia ao fechar software).
- Artefatos: `openspec/specs/<capability>/spec.md` · `openspec/changes/<change>/` · archive datado.
- **Idioma obrigatório pt-BR** em proposal, design, specs (delta e main), tasks e notas de archive — regras em `openspec/config.yaml`.
- Change pequena: 1 capability (máx. 2), non-goals explícitos, tasks verificáveis.
- Main specs ainda vazias — criar via propose/apply. CI valida main specs strict e changes com delta.
- Skills (link, não copiar): `openspec-*`, `panel-ui`, `ui-archetype`, `api-integra-contador`, `task-loop`, `agents-md` em `.grok/skills/`.

## Não faça

- Não responder ao usuário em inglês (exceto trechos de código/logs quando necessário).
- Não escrever changes/specs OpenSpec em inglês — pt-BR obrigatório.
- Não confiar `office_id` (query/body/JSON) — usar `CurrentOffice`.
- Não habilitar SERPRO live, mutações fiscais ou canais outbound sem flag/allowlist explícita.
- Não versionar `.env`, PFX, tokens, `VAULT_MASTER_KEY`, Consumer Secret.
- Não colocar segredos/XML completo em respostas API, logs ou manifesto de release.
- Não inventar targets Make ou scripts npm que não existam no `Makefile` / `package.json`.
- Não commitar artefatos gerados (`frontend/.nuxt`, `.output`, etc.) — `frontend-prepare-generated` recusa paths não ignorados.
- Não restaurar `AGENTS.md` de commit antigo por nostalgia — re-gerar a partir do repo atual (`/init` / skill agents-md).
