# AGENTS.md

Hub fiscal multi-escritório (NFS-e ADN + canais SEFAZ/SERPRO). Monorepo sem `package.json` na raiz.

## Layout

| Path | Papel |
|------|--------|
| `backend/` | Laravel 13 / PHP 8.4 / Horizon — API + domínio |
| `frontend/` | Nuxt 4 SPA (`ssr: false`) + Nuxt UI — painel |
| `docker/` | PHP image, nginx, backup/restore, scripts ops |
| `openspec/` | Specs/changes (skills `openspec-*`) |
| `.reference/nuxt-dashboard-template` | Template UI fixado (`0f30c09`); **gitignored** — clone local |

`backend/package.json` é só Vite/assets Laravel. UI de produto = `frontend/`.

## Comandos

Orquestração na raiz via **Makefile + Docker Compose** (fonte de verdade de dev).

```bash
make init-env          # .env + backend/.env + APP_KEY/VAULT_MASTER_KEY
make setup             # build, composer, generate SPA, migrate, sobe stack
make up                # nginx php postgres redis horizon scheduler
make dev               # stack + frontend-dev (Nuxt HMR :3000)
make down
make shell-php         # shell no container PHP
make migrate
make frontend-generate # SPA estática → frontend/.output/public (nginx monta isso)
```

Portas padrão: app `8080`, Nuxt dev `3000`, Postgres/Redis só em `127.0.0.1`.

### Backend (CI e local)

```bash
cd backend
composer validate --strict --no-check-publish
vendor/bin/pint --test          # estilo (CI)
php artisan test                # suite (sqlite :memory: via phpunit.xml)
php artisan test --filter=Nome  # um teste/classe
```

Com stack up:

```bash
docker compose exec php php artisan test --filter=…
docker compose exec php vendor/bin/pint --test
bash ./docker/ops/fiscal-hub-verify-backend.sh          # preflight + filtro Fiscal|Serpro|…
bash ./docker/ops/fiscal-hub-verify-backend.sh --full
```

PHP **8.4**; extensões críticas: `curl`, `sodium`, `zip`, `dom`.

### Frontend

```bash
cd frontend
corepack enable && pnpm install --frozen-lockfile   # pnpm@11.9.0
pnpm run lint
pnpm run typecheck
pnpm run generate          # CI + produção (static)
pnpm run test              # vitest: tests/unit/**
pnpm run test:e2e          # playwright (sobe dev se PLAYWRIGHT_BASE_URL vazio)
pnpm run test:fidelity     # gate template (precisa matrix OpenSpec se referenciada)
```

CI frontend: `lint` → `typecheck` → `generate` (não roda vitest/e2e no workflow atual).

## Runtime (não adivinhar)

- **Produção/local “up”:** nginx serve SPA de `frontend/.output/public` e faz proxy de `/api`, `/sanctum`, Fortify (`/login` POST, `/logout`, `/user/…`) para PHP-FPM. Same-origin cookie Sanctum.
- **Dev UI:** `make dev` — container `frontend-dev` com `NUXT_SANCTUM_PROXY=true` → proxy para `http://nginx`. Acesso remoto: setar `PUBLIC_HOST` (HMR) e incluir `host:porta` em `SANCTUM_STATEFUL_DOMAINS` no `backend/.env`.
- Filas: **Horizon** + Redis; cron: container `scheduler` (`schedule:work`).
- Vault de objetos: volume `/var/vault`, chave `VAULT_MASTER_KEY` (não versionar; fora de backups “comuns”).
- Bootstrap 1º tenant: `php artisan app:bootstrap-office` (falha se já existir office; senha só via prompt).

## Domínio / tenancy (fácil errar)

- Tenant = **`Office`**. Contexto via `CurrentOffice` (sessão → `users.selected_office_id` → 1ª membership). **Nunca** confiar `office_id` do client HTTP — `EnsureOfficeContext` remove do request; troca só em `POST /api/v1/tenants/switch`.
- Papéis office: `ADMIN` | `OPERATOR` | `VIEWER` (`OfficeRole`). Capacidades em métodos do enum (credenciais/mutações fiscais → ADMIN; sync/export/import → ADMIN|OPERATOR).
- Plataforma: `PLATFORM_ADMIN` **não** dá acesso fiscal implícito a tenants. Rotas `/api/v1/platform/*` sem office context; TOTP obrigatório.
- API tenant sob `auth:sanctum` + `EnsureActiveUser` + `EnsureOfficeContext` (+ 2FA admin + assinatura writable).
- Feature flags hub fiscal (`FeatureFlags` / `config/features.php`): **default OFF**; kill switch global vence; mutações exigem flags mutantes + allowlist de office. Canais SEFAZ MA/SVRS/autXML também default off em env.
- Segredos (PFX, OAuth, XML canônico): `SecureObjectStore` / vault — **não** logar, não devolver em JSON de API, não commitar `.pfx`/`.pem`/vault.
- Controllers tenant-scoped não devem importar SERPRO global (há testes em `tests/Architecture/`).

## Frontend UI

Qualquer tela autenticada: skill **`panel-ui`** → **`ui-archetype`** (copiar de `.reference/nuxt-dashboard-template` @ `0f30c09`). Não compor dashboard “do zero” com Nuxt UI de memória. Prod = SPA estática + nginx, **não** SSR Node.

## Skills de agent (canônico)

| Onde | Conteúdo | Git |
|------|----------|-----|
| **`.agents/skills/`** | Fonte única do projeto | versionar |
| **`.grok/config.toml`** / **`.codex/config.toml`** | MCP Playwright | versionar |
| **`.opencode/skills`**, **`.codex/skills`**, **`.grok/skills`** | Symlinks → `.agents/skills` | **gitignore** |
| **`.opencode/commands`**, **`.grok/commands`** | Slash `/opsx-*` etc. | **gitignore** |
| **`~/.agents/skills/`** | Global: nuxt, nuxt-ui, git-commit, grill/* | home |

Regenerar espelhos locais (após clone):

```bash
bash scripts/link-agent-skills.sh
```

Projeto: `panel-ui`, `ui-archetype`, `openspec-*`, `task-loop`.

## OpenSpec

Skills: `openspec-explore` / `propose` / `apply-change` / `archive-change` / `sync-specs`. Artefatos em `openspec/`. Não inventar escopo fora de change ativo.

## Não fazer

- Commitar `.env`, `secrets/`, vault, certificados, dumps SQL, `frontend/.output`.
- Habilitar feature flags / canais SEFAZ em testes sem isolamento (phpunit já força OFF + sqlite).
- Aceitar `office_id` do body/query para escopo de dados.
- `make restore` sem `CONFIRM_RESTORE=SIM` (destrutivo).
- Scaffold Nuxt novo; estender `frontend/`.
- Assumir que `docs/` ou changes OpenSpec antigas existem — confiar em código, CI e skills versionadas.
