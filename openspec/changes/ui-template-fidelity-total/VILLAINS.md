# Vilões de fidelidade — inventário `pages/**/*.vue`

Template real só tem ~15 SFCs Vue. O produto tem **51 pages** + dezenas de cascas. Abaixo está o snapshot pré-purga do que **não existe** no template e desvia layout/CSS/TS de chrome. Renomes posteriores não mudam a classificação: o gate usa caminho e responsabilidade, não o nome autoimportado.

## Regra

| Pode ficar (domínio) | É vilão (remover/alinhar ao template) |
|----------------------|----------------------------------------|
| `useApi`, types, permissions, toasts de negócio | `ShellListShell`, sticky/infinite loaders, KPI strips custom |
| Labels pt-BR, rotas, papéis | `table-ui.ts` com `100dvh` / presets inventados |
| Zod de formulário de domínio | `class` com `calc(100dvh…)`, densidades paralelas |
| Dados Sanctum | Wrappers que escondem `UDashboardPanel` |

Template de referência: `.reference/nuxt-dashboard-template` @ `0f30c09`  
Arquivos Vue do template: `layouts/default`, `pages/{index,customers,inbox,settings*}`, `components/{UserMenu,TeamsMenu,Notifications,home/*,inbox/*,customers/*,settings/MembersList}`.

---

## 1. Cascas / wrappers de layout (não existem no template)

| Arquivo | Usos em pages | Crime |
|---------|---------------|--------|
| `components/shell/ListShell.vue` | muitas pages | Reimplementa Panel+Navbar+Collapse |
| `components/shell/StickyTableFilters.vue` | clients, closing, health, processes, usage… | Teleport/sticky fora do template |
| `components/shell/InfiniteTableLoader.vue` | listas antigas | Loader de infinite scroll (template usa `UPagination`) |
| `components/shell/TableFooter.vue` | listas paginadas | Encapsula chrome que deve estar diretamente na page |
| `components/shell/KpiStrip.vue` | exports/home | KPI strip produto |
| `components/monitoring/ModuleTable.vue` | carteiras monitoring | Casca customers “gorda” |
| `components/monitoring/KpiStrip.vue` | guides | KPI custom |
| `components/monitoring/ModuleToolbar.vue` | via ModuleTable | Toolbar custom |
| `components/docs/Workspace.vue` | docs/* | tabela+modal ≠ inbox split |
| `components/shell/OperationalContext.vue` | home | Contexto extra no toolbar |
| `components/OfficeIdentity.vue` | shell | OK de domínio se copiar geometria de TeamsMenu |

---

## 2. Utils / composables de chrome tabular (vilões)

| Módulo | Crime |
|--------|--------|
| `utils/table-ui.ts` | Presets `DASHBOARD_*` + `max-h-[calc(100dvh…)]` — template usa `:ui` **inline** em `customers.vue` |
| `utils/table-sort.ts` | Helper de sort header (template faz no `columns` da page) |
| `utils/kpi-ui.ts` | Tokens de KPI inventados |
| `composables/useInfiniteTable.ts` | Paginação infinita — template: `getPaginationRowModel` + `UPagination` |
| `composables/useServerPage.ts` | Paginação server (ok domínio, mas UI deve parecer customers) |
| `composables/useFiscalModulePortfolio.ts` | Lógica de carteira + UI acoplada |
| `utils/overlay-ui.ts` / `async-ui.ts` | Padrões overlay não-template |

**Não são vilões de layout** (domínio necessário): `permissions`, `api-error`, `navigation` (rotas), `format`, types fiscais/work.

---

## 3. Todas as 51 pages — imports e vilões

Legenda flags: `SHELL`=ShellListShell · `INF`=infinite · `TUI`=table-ui · `STICKY`=StickyFilters · `WRAP`=workspace/MonitoringModuleTable · `AUTH` · `REDIR`

| Page | L | Flags | Imports custom (vilões em negrito) | Componentes vilões |
|------|---|-------|-------------------------------------|--------------------|
| `pages/index.vue` | 314 | SHELL | navigation | **ShellListShell**, OperationalContext, HomeWorkKpisBlock, HomeOperations, HomeTotals |
| `pages/login.vue` | 153 | AUTH | permissions | (layout auth — fora do dashboard template) |
| `pages/two-factor-challenge.vue` | 164 | AUTH | permissions | — |
| `pages/two-factor/setup.vue` | 288 | AUTH | — | — |
| `pages/clients.vue` | 82 | SHELL | — | **ShellListShell** |
| `pages/clients/index.vue` | 971 | INF TUI STICKY | **useInfiniteTable, table-ui, table-sort** | **StickyFilters, InfiniteLoader**, modais clients |
| `pages/clients/dashboard.vue` | 97 | — | types | ClientListDashboard (chart custom) |
| `pages/clients/[id].vue` | 308 | SHELL | useClientDetail | **ShellListShell**, DetailAside/Header |
| `pages/clients/[id]/index.vue` | 29 | — | — | ClientDashboard |
| `pages/clients/[id]/cadastro.vue` | 19 | — | — | ClientRegistration |
| `pages/clients/[id]/certificado.vue` | 20 | — | — | ClientCredentialPanel |
| `pages/clients/[id]/estabelecimentos.vue` | 33 | — | — | ClientBranchesPanel |
| `pages/clients/[id]/saidas.vue` | 29 | — | — | ClientOutboundCapturePanel |
| `pages/clients/[id]/sincronizacao.vue` | 52 | — | types | ClientSyncPanel, SvrsNfcePanel |
| `pages/settings.vue` | 64 | SHELL | — | **ShellListShell** |
| `pages/settings/index.vue` | 562 | — | types | forms settings (parcialmente ok) |
| `pages/settings/proxies.vue` | 317 | INF TUI | **useInfiniteTable, table-ui, table-sort** | **InfiniteLoader** |
| `pages/settings/subscription.vue` | 139 | — | types | — |
| `pages/settings/usage.vue` | 288 | INF TUI STICKY | **useInfiniteTable, table-ui, table-sort** | **Sticky, Infinite** |
| `pages/settings/cte.vue` | 44 | REDIR | — | — |
| `pages/exports/index.vue` | 952 | SHELL INF TUI | **useInfiniteTable, table-ui** | **Shell, KpiStrip, Infinite**, `h-[calc(100dvh…)]` |
| `pages/syncs/index.vue` | 592 | SHELL INF TUI | **table-ui** | **Shell, Infinite** |
| `pages/health/index.vue` | 425 | SHELL INF TUI STICKY | **table-ui** | **Shell, Sticky, Infinite** |
| `pages/closing/index.vue` | 757 | SHELL INF TUI STICKY | **useInfiniteTable, table-ui, table-sort** | **Shell, Sticky, Infinite**, 100dvh |
| `pages/docs/index.vue` | 4 | WRAP | — | **DocsWorkspace** |
| `pages/docs/catalog.vue` | 4 | WRAP | — | **DocsWorkspace** |
| `pages/docs/[accessKey].vue` | 4 | WRAP | — | **DocsWorkspace** |
| `pages/docs/imports/index.vue` | 216 | SHELL INF TUI | **useInfiniteTable, table-ui, table-sort** | **Shell, Infinite** |
| `pages/docs/imports/[id].vue` | 593 | SHELL INF TUI | **useInfiniteTable, table-ui, table-sort** | **Shell, Infinite** |
| `pages/docs/import-batches.vue` | 11 | REDIR | — | — |
| `pages/notes/index.vue` | 9 | REDIR | — | — |
| `pages/notes/[accessKey].vue` | 14 | REDIR | — | — |
| `pages/admin/index.vue` | 391 | SHELL | types | **ShellListShell** |
| `pages/admin/departments.vue` | 124 | SHELL | permissions, api-error | **ShellListShell** |
| `pages/monitoring/index.vue` | 489 | SHELL | fiscal types | **Shell**, MonitoringModuleNav, badges |
| `pages/monitoring/simples-mei.vue` | 223 | WRAP | fiscal | **MonitoringModuleTable** |
| `pages/monitoring/dctfweb.vue` | 314 | WRAP | fiscal-high-risk | **MonitoringModuleTable** |
| `pages/monitoring/fgts.vue` | 566 | WRAP | fiscal | **MonitoringModuleTable** |
| `pages/monitoring/installments.vue` | 365 | WRAP | fiscal | **MonitoringModuleTable** |
| `pages/monitoring/sitfis.vue` | 510 | WRAP | fiscal | **MonitoringModuleTable** |
| `pages/monitoring/declarations.vue` | 620 | WRAP | fiscal | **MonitoringModuleTable** |
| `pages/monitoring/guides.vue` | 777 | SHELL INF TUI | **useInfiniteTable, table-ui, table-sort** | **Shell, MonitoringKpiStrip, Infinite** |
| `pages/monitoring/mailbox.vue` | 277 | SHELL INF | **useInfiniteTable**, mailbox-triage | **Shell, Infinite**, MailboxList |
| `pages/monitoring/mailbox/index.vue` | 23 | — | — | empty detail |
| `pages/monitoring/mailbox/[id].vue` | 42 | — | — | MailboxMail |
| `pages/monitoring/clients/[clientId].vue` | 1056 | SHELL INF TUI | **useInfiniteTable, table-ui** | **Shell, Infinite**, 100dvh |
| `pages/work/index.vue` | 321 | SHELL INF | **useInfiniteTable**, work filters | **Shell, Infinite**, WorkTaskDetailPanel |
| `pages/work/calendar.vue` | 486 | SHELL | work calendar range | **Shell** + grade custom |
| `pages/work/processes/index.vue` | 404 | SHELL INF TUI STICKY | **useInfiniteTable, table-ui, table-sort** | **Shell, Sticky, Infinite** |
| `pages/work/processes/[id].vue` | 285 | SHELL | work-labels | **Shell** |
| `pages/work/templates/index.vue` | 508 | SHELL INF TUI | **useInfiniteTable, table-ui, table-sort** | **Shell, Infinite** |

---

## 4. Ranking de vilões (impacto)

1. **`ShellListShell`** — 21 pages (esconde o arquétipo literal)
2. **`useInfiniteTable` + InfiniteLoader`** — 13–15 pages (mata footer `UPagination` do customers)
3. **`utils/table-ui.ts`** — densidades + `100dvh` em massa
4. **`ShellStickyTableFilters`** — sticky não-template
5. **`MonitoringModuleTable` / `DocsWorkspace`** — monólitos de layout
6. **`ShellKpiStrip` / `MonitoringKpiStrip` / `OperationalContext`** — chrome extra
7. **Pages monstro** (>500L com layout+dados): clients/index, exports, closing, guides, monitoring client, DocsWorkspace

---

## 5. Plano de purga (100% chrome = template)

Ordem segura:

1. **Matar `table-ui` max-h / root custom** → copiar literal `:ui` de `customers.vue` / `HomeSales.vue` em cada `UTable`.
2. **Trocar infinite por `UPagination` visual** (dados ainda server-side se API exigir — ou page+per_page como customers visual).
3. **Expandir `ShellListShell`** → cada page com `UDashboardPanel` + `UDashboardNavbar` + `#leading` collapse como o template (sem casca).
4. **Remover StickyFilters / InfiniteLoader / KpiStrip** de chrome.
5. **MonitoringModuleTable / DocsWorkspace** → reescrever a partir de `customers.vue` / `inbox.vue` copiados, só dados reais.
6. **Home** → `index.vue` do template + HomeStats/Chart/Sales adaptados, sem OperationalContext/WorkKpisBlock como chrome adicional.

Domínio (API, tenancy, permissões) **permanece** no `<script>` — some só layout/CSS/TS de apresentação paralela.
