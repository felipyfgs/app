# AGENTS.md

## Idioma

- **Sempre pt-BR** com o usuário (respostas, perguntas, status).
- Artefatos OpenSpec (`proposal`, `design`, `specs`, `tasks`) e commits em **pt-BR**.
- Contexto e regras de artefato também em `openspec/config.yaml` — manter alinhado.

## State of the repo

- A implementação está em andamento em `backend/`, `frontend/` e `docker/`; a fonte de verdade continua sendo o OpenSpec em `openspec/`.
- Main specs em `openspec/specs/` (sincronizados em 2026-07-14 a partir das changes arquivadas).
- Active changes: nenhuma (`openspec list`). Arquivo recente: `openspec/changes/archive/2026-07-14-*` (`build-nfse-adn-capture-system`, `refactor-frontend-dashboard-ux`, `enforce-dashboard-template-fidelity`).
- OpenSpec skills/commands (oficial via `openspec init --tools …` / `openspec update` onde existir; Grok adaptado manualmente — CLI ainda não lista `grok`):
  - OpenCode: `.opencode/skills/openspec-*` + `.opencode/commands/opsx-*.md` (`/opsx-propose`, `/opsx-explore`, `/opsx-apply`, `/opsx-sync`, `/opsx-archive`)
  - Codex: `.codex/skills/openspec-*`
  - Grok: `.grok/skills/openspec-*` + `.grok/commands/opsx-*.md` (mesmos slash: `/opsx-propose`, `/opsx-explore`, `/opsx-apply`, `/opsx-sync`, `/opsx-archive`; também `/openspec-propose`, etc.)
  Preferir essas skills em vez de inventar workflow.

## OpenSpec workflow

Requires `openspec` CLI. Local root: nearest `openspec/` (this repo).

```bash
openspec list --json
openspec status --change "<name>" --json
openspec instructions <artifact-id|apply> --change "<name>" --json
openspec validate <name> --json   # when available / before archive
```

- Schema: `spec-driven`. Artifacts: `proposal` → `design` + `specs` → `tasks`. Apply needs `tasks`.
- Use paths from CLI JSON (`changeRoot`, `artifactPaths`, `contextFiles`); do not hardcode layout assumptions.
- Delta specs live in the change; main specs update on sync/archive only.
- Explore mode: think/read only — no application code. Capture decisions in artifacts when asked.
- Mark tasks `- [x]` immediately after each completed task when applying.

## Planned system (from design — do not invent alternatives)

When implementing the active change, honor these decisions:

| Area | Choice |
|------|--------|
| Layout | Monorepo: `backend/` (Laravel 13 / PHP 8.4), `frontend/` (Nuxt 4 / Nuxt UI 4 SPA) |
| Edge | Nginx same-origin: static SPA + PHP-FPM API/Sanctum (no CORS, no Node in prod) |
| Auth | Fortify + Sanctum cookie session, CSRF, TOTP; roles `ADMIN` / `OPERATOR` / `VIEWER` |
| Tenancy | Every business table has `office_id`; never trust client-supplied office id |
| Data | PostgreSQL truth; Redis/Horizon queues; Scheduler (not DB queues) |
| Secrets | Envelope crypto (`SecureObjectStore`); `VAULT_MASTER_KEY` outside DB/common backups |
| ADN | Own `AdnContributorClient` + mTLS; PFX only in memory (libcurl BLOB); TLS ≥1.2 + hostname verify |
| PFX helper | `nfephp-org/sped-common` only for PFX metadata — not a community ADN client as runtime dep |
| Ops | Docker Compose local; backup/restore before real fiscal data |

Non-goals (MVP): portal scraping, municipal APIs, emit/cancel NFS-e, DANFSe/PDF, client portal, cloud KMS, multi-office SaaS.

## Domain constraints agents miss

- Product is **internal for the accounting office**, not end clients.
- **One e-CNPJ A1 per client root**; establishments under that root share it. Full CNPJ (14 chars) as text — numeric **or** alphanumeric; store uppercase, unmasked, never as number.
- ADN distributes by **NSU**, not date. Cursor per establishment+environment; start at 0.
- Persist full page then advance NSU; unique constraints for idempotency. Do **not** advance on Base64/GZip failure; block after 5 consecutive decode failures (no silent NSU skip).
- Job: max 20 pages then requeue; lock per establishment; ~4 concurrent requests; global rate limit ~4 rps; hourly sync with deterministic spread across the hour.
- XML: keep original bytes (SHA-256); XSD/unknown version → parse alert, still keep well-formed XML. `dfe_documents` immutable; `document_interests` per NSU/role; `nfse_notes`/`nfse_events` are projections.
- **Never** expose PFX, password, private key, or PEM via API, logs, or export. No cert recovery route.
- No homologation certificate in CI; restricted prod smoke test required before release; no portal automation fallback.

## Implementation order (when coding starts)

Follow design migration plan: infra/schema/office+admin 2FA → vault backup/restore → mTLS smoke (emitente/tomador/intermediário) → pilot few roots → scale. Prefer interfaces (`SecureObjectStore`, `AdnContributorClient`) early.

Until `tasks.md` exists and apply instructions say otherwise, do not scaffold app code ad hoc.

## Frontend AI stack (Nuxt + Nuxt UI + template)

UI do painel em `frontend/` **sempre** passa por este encadeamento (não inventar layout):

| Ordem | Peça | Escopo | Função |
|------:|------|--------|--------|
| 1 | Domínio (`AGENTS.md` / OpenSpec) | repo | tenancy, papéis, SPA+Sanctum, segredos |
| 2 | Skill **`nuxt-dashboard-template`** | **projeto** | copiar arquétipo de `.reference/nuxt-dashboard-template` @ `0f30c09` |
| 3 | Skill + MCP **`nuxt-ui`** | global + MCP | API de componentes `U*`, ícones, theming |
| 4 | Skill + MCP **`nuxt`** | global + MCP | Nuxt 4 (`app/`, middleware, pages, config) |

- Orquestrador: **`/frontend-nuxt-stack`** (`.grok/skills/frontend-nuxt-stack/`).
- Template detalhe: **`/nuxt-dashboard-template`** + `references/stack.md`.
- MCPs: `nuxt-ui` → `https://ui.nuxt.com/mcp` · `nuxt` → `https://nuxt.com/mcp` (configurados no user agent).
- Conflito forma vs docs: **template vence** na estrutura; MCP só completa props.
- Não scaffoldar app Nuxt novo nem outro starter; estender `frontend/`.
