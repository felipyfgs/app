# AGENTS.md

## Idioma

- **Sempre pt-BR** com o usuário (respostas, perguntas, status).
- Artefatos OpenSpec (`proposal`, `design`, `specs`, `tasks`) e commits em **pt-BR**.
- Contexto e regras de artefato também em `openspec/config.yaml` — manter alinhado.

## State of the repo

- A implementação está em andamento em `backend/`, `frontend/` e `docker/`; a fonte de verdade continua sendo o OpenSpec em `openspec/`.
- Main specs em `openspec/specs/` (sincronizados em **2026-07-16** a partir das changes arquivadas).
- **Active change:** nenhuma no momento. Changes recentes estão em `openspec/changes/archive/2026-07-16-*` (hub fiscal, modelo de dados, work operacional, CT-e, AutXML, SVRS NFE-55, fixtures, dashboard, etc.). Archives anteriores: `2026-07-14-*`, `2026-07-15-*`.
- Piloto/smoke de produção (SERPRO mTLS, DistDFe real, SVRS auto-queue, etc.) permanece **OPEN/PENDING_OPS** em `docs/ops/*` mesmo com implementação arquivada — não assumir gates de piloto como PASS só porque a change foi arquivada.
- OpenSpec skills/commands (oficial via `openspec init --tools …` / `openspec update` onde existir; Grok adaptado manualmente — CLI ainda não lista `grok`):
  - OpenCode: `.opencode/skills/openspec-*` + `.opencode/commands/opsx-*.md` (`/opsx-propose`, `/opsx-explore`, `/opsx-apply`, `/opsx-sync`, `/opsx-archive`)
  - Codex: `.codex/skills/openspec-*`
  - Grok: `.grok/skills/openspec-*` + `.grok/commands/opsx-*.md` (mesmos slash: `/opsx-propose`, `/opsx-explore`, `/opsx-apply`, `/opsx-sync`, `/opsx-archive`; também `/openspec-propose`, etc.)
  - Grok loop engineering: `.grok/skills/task-loop/` + `.grok/commands/task-loop.md` (`/task-loop`, `/loop`) — goal → implement → verify até PASS (subagentes); complementar a `/opsx-apply`, não substitui.
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
| Auth (tenant) | Fortify + Sanctum cookie session, CSRF, TOTP; papéis do escritório `ADMIN` / `OPERATOR` / `VIEWER` |
| Auth (plataforma) | `PLATFORM_ADMIN` separado (memberships de plataforma); **não** herda acesso a conteúdo fiscal de tenants |
| Modelo | **SaaS multi-escritório**: software house opera a plataforma; cada `Office` é tenant comercial/segurança |
| Tenancy | Dados de tenant: `office_id` obrigatório; nunca confiar em `office_id` fornecido pelo cliente |
| Controle vs dados | Plano de controle global (contrato SERPRO, catálogo/preços, fatura consolidada, flags, platform memberships) vs plano de dados com `office_id` — ver ADR `docs/adr/005-control-plane-vs-data-plane.md` |
| SERPRO | Contrato **global** da software house (e-CNPJ contratante, Consumer Key/Secret, mTLS); tenants **não** recebem credenciais SERPRO |
| Cadeia Integra | Contratante (API) → Autor do Pedido (escritório/procurador + Termo) → Contribuinte; validar antes de cada chamada |
| Data | PostgreSQL truth; Redis/Horizon queues; Scheduler (not DB queues) |
| Secrets | Envelope crypto (`SecureObjectStore`); `VAULT_MASTER_KEY` outside DB/common backups |
| ADN / SEFAZ | Canais documentais existentes permanecem; cliente próprio + mTLS; PFX só em memória; não alterar cursores NSU/nNF nesta change |
| PFX helper | `nfephp-org/sped-common` only for PFX metadata — not a community ADN client as runtime dep |
| Ops | Docker Compose local; backup/restore before real fiscal data |

### Não-objetivos (MVP / desta plataforma)

- **Portal ou login de contribuinte final** (clientes dos escritórios) — produto é para **escritórios contábeis**.
- Scraping de portais, CAPTCHA, Gov.br, cookies ou sessões de navegador.
- Cobertura integral de FGTS Digital (sem API pública oficial equivalente; só parcial via eSocial quando aplicável).
- Gateway de pagamento, cobrança bancária, emissão de NF da assinatura ou motor de precificação comercial completo no MVP.
- APIs municipais genéricas; emitir/cancelar NFS-e nacional; DANFSe/PDF como produto.
- Cloud KMS; sublicenciar/expor credenciais SERPRO, PFX, tokens ou Termo assinado a tenants.
- Assumir autorização comercial SERPRO para SaaS/reprecificação **sem** evidência formal (gate bloqueante — ver `docs/ops/serpro-integra-contador-commercial-legal-evidence.md`).

> **Nota de modelo:** multi-escritório SaaS **deixa de ser non-goal** e passa a ser o modelo oficial. Isolamento por `office_id` permanece obrigatório nos dados fiscais.

## Domain constraints agents miss

- Product is **for accounting offices** (tenants), **not** end-client portals.
- **One e-CNPJ A1 per client root** (canais SEFAZ/ADN do tenant); establishments under that root share it. Full CNPJ (14 chars) as text — numeric **or** alphanumeric; store uppercase, unmasked, never as number.
- ADN distributes by **NSU**, not date. Cursor per establishment+environment; start at 0.
- Persist full page then advance NSU; unique constraints for idempotency. Do **not** advance on Base64/GZip failure; block after 5 consecutive decode failures (no silent NSU skip).
- Job: max 20 pages then requeue; lock per establishment; ~4 concurrent requests; global rate limit ~4 rps; hourly sync with deterministic spread across the hour.
- XML: keep original bytes (SHA-256); XSD/unknown version → parse alert, still keep well-formed XML. `dfe_documents` immutable; `document_interests` per NSU/role; `nfse_notes`/`nfse_events` are projections.
- **Never** expose PFX, password, private key, PEM, Consumer Secret, tokens SERPRO or Termo XML via API, logs, or export. No cert recovery route.
- No homologation certificate in CI; restricted prod smoke test required before release; no portal automation fallback.
- Mesmo CNPJ pode existir em **dois escritórios** distintos; queries, jobs, locks e exports **nunca** misturam tenants.
- Usuário pode ter várias memberships; tenant ativo é escolhido explicitamente (não `office_id` livre no request). Até a troca explícita existir, `activeMembership()` pega a primeira ativa — tratar como limitação legada.

## Implementation order (when coding starts)

Seguir o migration plan do design da change ativa: domínio/gates → plano de controle + tenant lifecycle → cofre/contrato SERPRO → mTLS/OAuth trial → Autor/Termo/procurações → ledger shadow → núcleo fiscal read-only → piloto → mutações só com aprovação. Preferir interfaces (`SecureObjectStore`, `AdnContributorClient`, `IntegraContadorClient`) cedo.

Until `tasks.md` exists and apply instructions say otherwise, do not scaffold app code ad hoc.

## Frontend AI stack (Nuxt + Nuxt UI + template)

UI do painel em `frontend/` **sempre** passa por este encadeamento (não inventar layout):

| Ordem | Peça | Escopo | Função |
|------:|------|--------|--------|
| 1 | Domínio (`AGENTS.md` / OpenSpec) | repo | tenancy multi-office, papéis tenant vs platform, SPA+Sanctum, segredos |
| 2 | Skill **`nuxt-dashboard-template`** | **projeto** | copiar arquétipo de `.reference/nuxt-dashboard-template` @ `0f30c09` |
| 3 | Skill + MCP **`nuxt-ui`** | global + MCP | API de componentes `U*`, ícones, theming |
| 4 | Skill + MCP **`nuxt`** | global + MCP | Nuxt 4 (`app/`, middleware, pages, config) |

- Orquestrador: **`/frontend-nuxt-stack`** (`.grok/skills/frontend-nuxt-stack/`).
- Template detalhe: **`/nuxt-dashboard-template`** + `references/stack.md`.
- MCPs: `nuxt-ui` → `https://ui.nuxt.com/mcp` · `nuxt` → `https://nuxt.com/mcp` (configurados no user agent).
- Conflito forma vs docs: **template vence** na estrutura; MCP só completa props.
- Não scaffoldar app Nuxt novo nem outro starter; estender `frontend/`.
- Shell tenant-aware: escritório ativo visível; troca só entre memberships válidas; dados do contrato SERPRO global **não** expostos ao tenant (somente saúde sanitizada, consumo e limites do plano).
