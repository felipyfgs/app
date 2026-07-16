# Tree reorganizada — lógica Nuxt UI Dashboard Template

Data: 2026-07-16  
Referência: `.reference/nuxt-dashboard-template` @ `0f30c09`

## Princípios (template)

1. **Shell** só em `layouts/default.vue` (+ chrome `UserMenu` / header / notifications na raiz de `components/`).
2. **Páginas** = arquétipos: home · lista (customers) · mestre–detalhe (inbox) · settings aninhado.
3. **Componentes por domínio** da feature (`customers/`, `inbox/`, `home/`, `settings/`) — não monólito genérico.
4. **Lista plana** preferida: `pages/exports.vue` (não `exports/index.vue` se for único arquivo).

## Tree atual do produto

```text
frontend/app/
├── layouts/
│   ├── default.vue          # shell autenticado
│   └── auth.vue             # login / 2FA
├── components/
│   ├── UserMenu.vue         # = template
│   ├── NotificationsSlideover.vue
│   ├── OfficeIdentity.vue   # = TeamsMenu adaptado
│   ├── shell/               # cascas de panel/lista (ListShell, TableFooter, …)
│   ├── home/
│   ├── clients/
│   ├── docs/                # era notes/ — serve /docs
│   ├── work/
│   ├── monitoring/          # ModuleTable, Mailbox*, KpiStrip de carteira
│   ├── fiscal/              # badges/cells/modals de domínio fiscal
│   └── office/
├── pages/
│   ├── index.vue            # home
│   ├── clients.vue + clients/*   # lista + settings detalhe
│   ├── docs/*               # catálogo / detalhe / imports
│   ├── work/*               # fila (inbox) + processos/templates
│   ├── monitoring/*         # home fiscal + listas + mailbox
│   ├── settings.vue + settings/*
│   ├── exports.vue          # lista plana (foi exports/index)
│   ├── syncs.vue · health.vue · closing.vue
│   ├── admin/*
│   ├── notes/*              # redirects legados → /docs
│   └── login · two-factor*
└── composables/ · utils/ · types/
```

## Renomes de componentes (auto-import Nuxt)

| Antes | Depois | Auto-import |
|-------|--------|-------------|
| `notes/NotesWorkspace` | `docs/Workspace` | `DocsWorkspace` |
| `notes/NotesCatalog` | `docs/Catalog` | `DocsCatalog` |
| … | … | `Docs*` |
| `DashboardListShell` | `shell/ListShell` | `ShellListShell` |
| `TemplateTableFooter` | `shell/TableFooter` | `ShellTableFooter` |
| `FiscalModuleTable` | `monitoring/ModuleTable` | `MonitoringModuleTable` |
| `FiscalMonitoringPortfolioActions` | `monitoring/PortfolioActions` | `MonitoringPortfolioActions` |
| `AppStatusBadge` | `shell/StatusBadge` | `ShellStatusBadge` |

## Rotas inalteradas (URLs)

- `/exports`, `/syncs`, `/health`, `/closing` (flatten de pasta)
- `/docs/*`, `/work/*`, `/monitoring/*`, `/clients/*`

## Próximos (opcional)

- Expandir `ShellListShell` inline nas pages (eliminar casca)
- Fundir `clients.vue` shell artificial com lista
- Remover pasta `notes/` após sunset de redirects

## Update: ShellListShell eliminado

`components/shell/ListShell.vue` removido. Todas as pages/cascas usam **`UDashboardPanel` + `UDashboardNavbar` + `UDashboardSidebarCollapse` inline**, como no template.

Cascas restantes (feature, não shell genérico):
- `docs/Workspace.vue` — posto documentos
- `monitoring/ModuleTable.vue` — carteiras fiscais
- `monitoring/MailboxMail.vue` — detalhe inbox
- `work/WorkTaskDetailPanel.vue` — detalhe tarefa
