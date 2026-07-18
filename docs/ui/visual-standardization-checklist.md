# Checklist de padronização visual da UI

Inventário inicial de rotas em `frontend/app/pages`, agrupadas por famílias de produto.
Arquétipos conforme skill **ui-archetype** (`.codex/skills/ui-archetype` / `.grok/skills/ui-archetype`) e referência fixada em `.reference/nuxt-dashboard-template` (`0f30c09`).

## Legenda

| Campo | Valores iniciais |
|-------|------------------|
| **Arquétipo** | Shell · Home · Lista · Mestre–detalhe · Settings · Modal form · Auth (fora do template) · Detalhe · Híbrido (com nota) |
| **Status desktop / mobile** | `A validar` |
| **Componentes** | Fidelidade de `U*` / slots / classes vs origem do template |
| **Screenshots** | Captura desktop e mobile (ou viewport estreito) |
| **Testes** | Unitários / fidelity / artifacts / smoke manual |
| **Pendências** | Gaps vs arquétipo, largura (`DashboardContent`), tenancy, a11y |
| **Conclusão** | `A validar` até revisão humana |

**Fonte de arquétipos (template):**

| Arquétipo | Origem |
|-----------|--------|
| Shell | `layouts/default.vue` + menus |
| Home | `pages/index.vue`, `components/home/*` |
| Lista | `pages/customers.vue` |
| Mestre–detalhe | `pages/inbox.vue`, `inbox/*` |
| Settings | `pages/settings.vue` + subpáginas |
| Modal form | `customers/AddModal.vue` / `DeleteModal.vue` |
| Auth | `layouts/auth.vue` (produto; fora do demo dashboard) |

**Largura do produto (lembrete):** settings/admin/detalhe textual → `DashboardContent` `comfortable` (`max-w-5xl`); detalhe denso → `wide` (`max-w-6xl`); home/lista/mestre–detalhe → largura disponível.

**Segurança na validação:** não capturar nem anotar PFX, senhas, PEM, tokens, XML completo, `office_id` de client ou segredos de vault.

---

## Como preencher (por rota)

Para cada linha, após revisão visual e de código:

1. Confirmar arquétipo e arquivo(s) de origem no template.
2. Marcar desktop e mobile: `OK` · `Parcial` · `Fora do padrão` · `N/A`.
3. Listar componentes-chave validados (ou divergências).
4. Anexar/referenciar screenshots (paths locais, sem dados sensíveis).
5. Registrar testes executados ou lacunas.
6. Registrar pendências acionáveis.
7. Conclusão: `OK` ou `Reabrir` + resumo de uma linha.

Status inicial de **todas** as células de validação: **A validar**.

---

## 1. Auth e primeiro acesso

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/login` | Auth | OK | OK | UPageCard + UAuthForm + UAlert | Local, regenerável e não versionada | lint auth OK · `auth-redirect` 10/10 · generate OK | — | OK |
| `/activate` | Auth | OK | OK | UPageCard + USkeleton / invalid + UForm | Local, regenerável e não versionada (link inválido) | idem | captura com token válido exige fixture; não bloqueia | OK |
| `/first-access` | Auth | OK | OK | UPageCard + UForm + link login | Local, regenerável e não versionada | idem | — | OK |
| `/onboarding` | Auth / Settings (form) | OK | OK | UPageCard + USkeleton / UAlert bloqueado + UForm | Local, regenerável e não versionada (indisponível) | idem | form completo só com `available`+token deploy; não bloqueia | OK |
| `/two-factor-challenge` | Auth (redirect) | OK | OK | status “Redirecionando…” → login/home | Local, regenerável e não versionada (pós-redirect) | idem | TOTP descontinuado — redirect intencional | OK |
| `/two-factor/setup` | Auth (redirect) | OK | OK | layout auth + status redirect | Local, regenerável e não versionada | idem | TOTP descontinuado — redirect intencional | OK |

**Arquivos:** `login.vue`, `activate.vue`, `first-access.vue`, `onboarding.vue`, `two-factor-challenge.vue`, `two-factor/setup.vue`, `layouts/auth.vue`
**Referência:** layout auth do produto (não copiar chrome do dashboard demo); recipe Nuxt UI Auth (`UPageCard` + form).
**Ciclo 2026-07-18:** família Auth fechada visualmente; dados sintéticos apenas; sem alteração de rotas/APIs fora do escopo.
**Evidência visual:** artefatos locais regeneráveis, não versionados; repetir a
captura desktop 1440×900 e mobile 390×844 na revisão do código vigente.
**Gates (escopo auth, comprovados):**
- `eslint` nos 7 arquivos auth: **PASS** (0 erros)
- `vitest` `auth-redirect` + `activation-public` + `onboarding-public`: **17/17 PASS**
- `pnpm run generate`: **PASS**
**Gates globais (fora do escopo, pré-existentes):** `pnpm lint` com erros em monitoring/pg*; `typecheck` falha em `ModuleMobileCards` / `dctfweb-table` / `pgdasd*`; não introduzidos por esta família.

---

## 2. Home e saúde

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/` | Home | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/health` | Home (KPI / status) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `index.vue`, `health.vue`
**Referência template:** `pages/index.vue`, `components/home/*` (quando houver stats/chart).

---

## 3. Clientes

Shell de catálogo (`clients.vue`) usa chrome Settings + `NuxtPage` para lista e dashboard; detalhe (`clients/[id].vue`) é shell Settings de seções próprio.

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/clients` (shell) | Settings (chrome de seções) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients` (lista) | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/dashboard` | Home de clientes | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]` (shell) | Settings (seções) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]` / index | Settings (resumo) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]/cadastro` | Settings (form) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]/certificado` | Settings (form) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]/estabelecimentos` | Lista em detalhe / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]/saidas` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/clients/[id]/sincronizacao` | Settings + status | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| Modal criar cliente | Modal form | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `clients.vue`, `clients/index.vue`, `clients/dashboard.vue`, `clients/[id].vue`, `clients/[id]/index.vue`, `clients/[id]/cadastro.vue`, `clients/[id]/certificado.vue`, `clients/[id]/estabelecimentos.vue`, `clients/[id]/saidas.vue`, `clients/[id]/sincronizacao.vue`
**Referência template:** `pages/customers.vue`, `pages/settings.vue`, `customers/AddModal.vue`, `components/home/*` (dashboard).
**Produto:** `ClientCreateModal`, painéis de credencial/sync/estabelecimentos — sem PFX/senha em UI.

---

## 4. Notas (ADN / NFS-e workspace)

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/notes` | Mestre–detalhe | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/notes/[accessKey]` | Detalhe (mail / página full) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `notes/index.vue`, `notes/[accessKey].vue`
**Referência template:** `pages/inbox.vue`, `inbox/InboxList.vue`, `inbox/InboxMail.vue`
**Produto:** `NotesWorkspace`, `NotesCatalog`, `NotesDetail`, `NotesFilters` — sem XML completo sensível em screenshot.

---

## 5. Documentos e importações

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/docs` | Híbrido (workspace / lista) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/docs/catalog` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/docs/[accessKey]` | Detalhe | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/docs/import-batches` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/docs/imports` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/docs/imports/[id]` | Detalhe / Lista em detalhe | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `docs/index.vue`, `docs/catalog.vue`, `docs/[accessKey].vue`, `docs/import-batches.vue`, `docs/imports/index.vue`, `docs/imports/[id].vue`
**Referência template:** `pages/customers.vue` (listas); detalhe alinhado a painel/mail ou settings conforme densidade.

---

## 6. Trabalho (work)

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/work` | Home / hub operacional | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/work/calendar` | Home / workspace (calendário) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/work/processes` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/work/processes/[id]` | Detalhe / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/work/tasks/[id]` | Detalhe / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/work/templates` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `work/index.vue`, `work/calendar.vue`, `work/processes/index.vue`, `work/processes/[id].vue`, `work/tasks/[id].vue`, `work/templates/index.vue`
**Referência template:** `pages/customers.vue` (listas); `pages/index.vue` / workspace sem teto de largura indevido.

---

## 7. Monitoramento fiscal

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/monitoring` | Lista / hub | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/clients/[clientId]` | Detalhe / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/declarations` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/guides` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/installments` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/registrations` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/tax-processes` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/fgts` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/sitfis` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/dctfweb` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/dctfweb/[submodule]` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/simples-mei` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/simples-mei/[submodule]` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/mailbox` (shell lista) | Mestre–detalhe | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/mailbox` (index vazio) | Mestre–detalhe (empty) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/monitoring/mailbox/[id]` | Mestre–detalhe (detalhe / slideover mobile) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `monitoring/index.vue`, `monitoring/clients/[clientId].vue`, `monitoring/declarations.vue`, `monitoring/guides.vue`, `monitoring/installments.vue`, `monitoring/registrations.vue`, `monitoring/tax-processes.vue`, `monitoring/fgts.vue`, `monitoring/sitfis.vue`, `monitoring/dctfweb/index.vue`, `monitoring/dctfweb/[submodule].vue`, `monitoring/simples-mei/index.vue`, `monitoring/simples-mei/[submodule].vue`, `monitoring/mailbox.vue`, `monitoring/mailbox/index.vue`, `monitoring/mailbox/[id].vue`
**Referência template:** `pages/customers.vue` (listas); mailbox → `pages/inbox.vue` (split desktop + `USlideover` mobile).

---

## 8. Conta (perfil e escritório)

Shell unificado Settings (`conta.vue` + `NuxtPage`).

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/conta` (shell) | Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta` / index | Settings (form) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta/escritorio` | Settings (form) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta/equipe` | Settings (lista members) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta/departamentos` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta/assinatura` | Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/conta/consumo` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `conta.vue`, `conta/index.vue`, `conta/escritorio.vue`, `conta/equipe.vue`, `conta/departamentos.vue`, `conta/assinatura.vue`, `conta/consumo.vue`
**Referência template:** `pages/settings.vue`, `settings/*`, `settings/MembersList.vue`
**Largura:** `DashboardContent` `comfortable`.

---

## 9. Settings legadas (aliases / rotas remanescentes)

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/settings` | Redirect → Conta | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings` / index | Settings (legado) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/team` | Settings (lista) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/departments` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/subscription` | Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/usage` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/proxies` | Lista + formulário | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/settings/cte` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `settings.vue`, `settings/index.vue`, `settings/team.vue`, `settings/departments.vue`, `settings/subscription.vue`, `settings/usage.vue`, `settings/proxies.vue`, `settings/cte.vue`
**Referência template:** `pages/settings.vue`, `pages/customers.vue` (listas/proxies).
**Nota:** `/settings` redireciona para `/conta/escritorio` — validar UX do redirect e se subrotas ainda são canônicas.

---

## 10. Admin de plataforma

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/admin` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/departments` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/offices` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/offices/new` | Settings (form) / Modal form | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/offices/[id]` | Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/owner` | Settings / Home | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro` (shell) | Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro` (operação) | Settings / Home | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/configuration` | Settings (form) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/dte-canary` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/catalog` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/contracts` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/rollout` | Settings / Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/admin/serpro/usage` | Lista / Settings | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `admin/index.vue`, `admin/departments.vue`, `admin/offices/index.vue`, `admin/offices/new.vue`, `admin/offices/[id].vue`, `admin/owner/index.vue`, `admin/serpro.vue`, `admin/serpro/index.vue`, `admin/serpro/configuration.vue`, `admin/serpro/dte-canary.vue`, `admin/serpro/catalog.vue`, `admin/serpro/contracts.vue`, `admin/serpro/rollout.vue`, `admin/serpro/usage.vue`
**Referência template:** `pages/customers.vue`, `pages/settings.vue`
**Escopo:** apenas papéis ADMIN / PLATFORM_ADMIN; não misturar contexto privilegiado com tenancy fiscal implícito.

---

## 11. Operações (fechamento, exportações, sincronizações)

| Rota | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| `/closing` | Lista + KPIs | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/exports` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `/syncs` | Lista | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Arquivos:** `closing.vue`, `exports.vue`, `syncs.vue`
**Referência template:** `pages/customers.vue` + `components/home/*` (KPIs de closing).

---

## 12. Shell autenticado (fora de `pages/`, obrigatório no pacote visual)

Não é rota de `pages/`, mas ancora a padronização de todas as páginas autenticadas.

| Superfície | Arquétipo | Desktop | Mobile | Componentes | Screenshots | Testes | Pendências | Conclusão |
|------------|-----------|---------|--------|-------------|-------------|--------|------------|-----------|
| Layout default (sidebar, search, user menu) | Shell | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `OfficeIdentity` (header escritório) | Shell (não TeamsMenu) | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `NotificationsSlideover` | Shell / overlay | A validar | A validar | A validar | A validar | A validar | A validar | A validar |
| `UserMenu` | Shell | A validar | A validar | A validar | A validar | A validar | A validar | A validar |

**Referência template:** `layouts/default.vue`, menus; produto substitui `TeamsMenu` por `OfficeIdentity`.

---

## Resumo de cobertura (páginas Vue inventariadas)

| Família | Qtd. de arquivos `pages/**/*.vue` (aprox.) | Status global |
|---------|--------------------------------------------|---------------|
| Auth e primeiro acesso | 6 (+ `layouts/auth.vue`) | **OK** (2026-07-18) |
| Home e saúde | 2 | A validar |
| Clientes | 10 | A validar |
| Notas | 2 | A validar |
| Documentos e importações | 6 | A validar |
| Trabalho | 6 | A validar |
| Monitoramento | 16 | A validar |
| Conta | 7 | A validar |
| Settings legadas | 8 | A validar |
| Admin | 14 | A validar |
| Operações | 3 | A validar |
| **Total páginas** | **~80** | **A validar** |

Contagem alinhada ao inventário de `frontend/app/pages` no momento da criação deste checklist; shells com `NuxtPage` e rotas filhas aparecem como linhas separadas de validação visual quando o chrome ou o body diferem.

---

## Critérios mínimos por arquétipo (checklist de revisão)

### Shell

- [ ] `UDashboardGroup` + sidebar + search + footer user
- [ ] Collapse mobile; sidebar `open` coerente
- [ ] Sem `TeamsMenu` de multi-tenant do demo
- [ ] Nav/shortcuts via produto (`useDashboard` / navigation)

### Home

- [ ] `UDashboardPanel` + navbar (+ toolbar se filtro temporal real)
- [ ] Stats/chart/listas na ordem reconhecível vs template
- [ ] Sem teto `max-w-*` arbitrário comprimindo dados

### Lista

- [ ] Navbar + ação primária à direita quando aplicável
- [ ] Toolbar: busca / filtros / colunas
- [ ] `UTable` com `:ui` elevado (border-separate, thead elevated)
- [ ] Paginação ou feed incremental alinhado à API (sem footer decorativo indevido)
- [ ] Empty / loading / erro em pt-BR

### Mestre–detalhe

- [ ] Painel lista + detalhe adjacente no desktop
- [ ] Detalhe em `USlideover` (ou equivalente) no mobile
- [ ] Empty state quando nada selecionado
- [ ] Foco/seleção restauráveis ao fechar detalhe

### Settings

- [ ] Navbar + toolbar `UNavigationMenu` highlight
- [ ] Body com `DashboardContent` comfortable (ou wide se grid denso)
- [ ] Cards `UPageCard` + `UForm` / `UFormField` em ritmo do template
- [ ] Save/actions no padrão horizontal do card

### Modal form

- [ ] `UModal` + form validado; Cancel / submit
- [ ] Não expor segredos no corpo do modal

### Auth

- [x] Fora do shell dashboard; largura típica `max-w-md` quando aplicável (`layouts/auth.vue`)
- [x] Fluxos 2FA / activate sem vazar tokens em UI ou screenshot (fragmento consumido; stubs 2FA só redirect)
- [x] Desktop/mobile revisados no ciclo histórico; regenerar artefatos locais na revisão final (família 1)

---

## Próximos passos sugeridos

1. ~~Validar família Auth~~ — **feito** (seção 1).
2. Validar shell + `/` + `/clients` + `/notes` (âncoras de cada arquétipo principal).
3. Fechar famílias Conta e Admin (Settings + listas).
4. Percorrer Monitoring (volume alto de listas) e Mailbox (mestre–detalhe).
5. Confirmar redirects legados de `/settings/*` vs `/conta/*`.
6. Corrigir typecheck/lint globais pré-existentes (monitoring/pgdasd) antes de `test:gate` completo.
7. Rodar gates frontend relevantes (`pnpm run test:gate`, `test:fidelity`, `test:artifacts`) após correções — sem versionar artefatos gerados.

---

*Última atualização factual da família Auth: 2026-07-18. Demais famílias permanecem **A validar**. Não registrar dados sensíveis neste arquivo.*
