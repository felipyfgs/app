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
./docker/ops/verify.sh          # preflight + filtro Fiscal|Serpro|…
./docker/ops/verify.sh --full
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
pnpm run test:e2e          # playwright funcional (pula *visual*; sobe preview se PLAYWRIGHT_BASE_URL vazio)
pnpm run test:e2e:visual   # regressão visual local (__screenshots__/ gitignored; use --update-snapshots)
pnpm run test:fidelity     # gate template (precisa matrix OpenSpec se referenciada)
```

CI frontend: `lint` → `typecheck` → `generate` (não roda vitest/e2e no workflow atual).

## Runtime (não adivinhar)

- **Produção/local “up”:** nginx serve SPA de `frontend/.output/public` e faz proxy de `/api`, `/sanctum`, Fortify (`/login` POST, `/logout`, `/user/…`) para PHP-FPM. Same-origin cookie Sanctum.
- **Produção pública:** `compose.prod.yml` gera imagens imutáveis e publica somente Traefik em `80/443` para `app.inovaicontabil.com.br`; usar `make prod-*` com `.env.prod` (modo `600`). Não versionar estado ACME.
- **Dev UI:** `make dev` — container `frontend-dev` com `NUXT_SANCTUM_PROXY=true` → proxy para `http://nginx`. Acesso remoto: setar `PUBLIC_HOST` (HMR) e incluir `host:porta` em `SANCTUM_STATEFUL_DOMAINS` no `backend/.env`.
- Filas: **Horizon** + Redis; cron: container `scheduler` (`schedule:work`).
- Vault de objetos: volume `/var/vault`, chave `VAULT_MASTER_KEY` (não versionar; fora de backups “comuns”).
- Bootstrap 1º tenant: `php artisan app:bootstrap-office` (falha se já existir office; senha só via prompt).

## Domínio / tenancy (fácil errar)

- Tenant = **`Office`**. Contexto via `CurrentOffice` (sessão → `users.selected_office_id` → 1ª membership). **Nunca** confiar `office_id` do client HTTP — `EnsureOfficeContext` remove do request; troca só em `POST /api/v1/tenants/switch`.
- Papéis office: `ADMIN` | `OPERATOR` | `VIEWER` (`OfficeRole`). Capacidades em métodos do enum (credenciais/mutações fiscais → ADMIN; sync/export/import → ADMIN|OPERATOR).
- Plataforma: `PLATFORM_ADMIN` **não** dá acesso fiscal implícito a tenants. Rotas `/api/v1/platform/*` sem office context; senha recente (15 min) nas ações sensíveis (TOTP descontinuado).
- API tenant sob `auth:sanctum` + `EnsureActiveUser` + `EnsureOfficeContext` (+ assinatura writable). Ações sensíveis: reconfirmação de senha.
- Work: leitura global privilegiada ok; mutação/export exige `OfficeMembership` real no Office.
- Feature flags hub fiscal (`FeatureFlags` / `config/features.php`): **default OFF**; kill switch global vence; mutações exigem flags mutantes + allowlist de office. Canais SEFAZ MA/SVRS/autXML também default off em env.
- Segredos (PFX, OAuth, XML canônico): `SecureObjectStore` / vault — **não** logar, não devolver em JSON de API, não commitar `.pfx`/`.pem`/vault.
- Controllers tenant-scoped não devem importar SERPRO global (há testes em `tests/Architecture/`).

## Frontend UI

Qualquer tela autenticada: skill **`panel-ui`** → **`ui-archetype`** (copiar de `.reference/nuxt-dashboard-template` @ `0f30c09`). Não compor dashboard “do zero” com Nuxt UI de memória. Prod = SPA estática + nginx, **não** SSR Node.

## Skills de agent (por engine)

Pastas de engine **inteiras** são locais (gitignored) — não versionar:

| Onde | Conteúdo |
|------|----------|
| **`.grok/`** | Grok: skills, commands, config |
| **`.opencode/`** | OpenCode: skills, commands, deps locais |
| **`.codex/`** | Codex: skills, config |
| **`~/.agents/skills/`** | Global do **usuário** (nuxt, nuxt-ui, git-commit, grill/*) — home |

Projeto (só em disco local): `panel-ui`, `ui-archetype`, `openspec-*`, `task-loop`, **`api-integra-contador`**, slash `/opsx-*`, etc.

### `api-integra-contador` (SERPRO)

Expert da [API Integra Contador](https://apicenter.estaleiro.serpro.gov.br/documentacao/api-integra-contador/).
Pastas locais: `.grok|opencode|codex/skills/api-integra-contador/` (e commands locais).
Use em OAuth/mTLS, envelope, `idSistema`/`idServico`, termo/procurador, bilhetagem, adapters `backend/app/Services/{Integra,Serpro}/**`.

## OpenSpec

Skills: `openspec-explore` / `propose` / `apply-change` / `archive-change` / `sync-specs` (slash `/opsx-*`).  
Artefatos em `openspec/` — **versionados no git** (specs + changes ativas + `changes/archive/`).  
Config e regras de escopo: `openspec/config.yaml`. Playbook: `openspec/README.md`.

Ciclo: propose (change pequena) → apply → verify → archive (sync main specs) → **commit no mesmo dia**.  
Não inventar escopo fora de change ativa; 1 capability principal (máx. 2); `[x]` só com evidência real.  
Live smoke SERPRO / ticket externo / jurídico = non-goal ou change ops separada.  
CI valida main specs e/ou change **ativa** — nunca path já em `archive/`.

Produto: confiar em **código + CI + `openspec/specs/`**. Ops SERPRO: `docs/ops/runbooks/` quando existir. Não inventar docs/runbooks ausentes.

## Não fazer

- Commitar `.env`, `secrets/`, vault, certificados, dumps SQL, `frontend/.output`.
- Habilitar feature flags / canais SEFAZ em testes sem isolamento (phpunit já força OFF + sqlite).
- Aceitar `office_id` do body/query para escopo de dados.
- `make restore` sem `CONFIRM_RESTORE=SIM` (destrutivo).
- Scaffold Nuxt novo; estender `frontend/`.
- Archive OpenSpec sem commit de `openspec/specs/` + `changes/archive/` (ou CI apontando change arquivada).
- Epic OpenSpec (dezenas de tasks / 5+ capabilities) sem fatiar; `[x]` em live ops sem evidence.
- Commitar pastas de engine de agente (`.grok/`, `.codex/`, `.opencode/`) — gitignored; só local.
