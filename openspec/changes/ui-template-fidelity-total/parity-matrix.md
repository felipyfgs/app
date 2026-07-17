# Matriz de paridade — frontend ↔ Nuxt UI Dashboard Template

**Template:** `.reference/nuxt-dashboard-template` @ `0f30c09`  
**App:** `frontend/app/pages/**`  
**Gate:** `frontend/scripts/template-fidelity-gate.mjs`

## Bundles (contrato do gate)

| Bundle | Significado | Chrome esperado | Template-fonte |
|--------|-------------|-----------------|----------------|
| `HOME` | KPIs / overview | `UDashboardPanel` self | `pages/index.vue` |
| `LIST` | Tabela admin | Panel self **ou** `MonitoringModuleTable` (casca customers) | `pages/customers.vue` |
| `INBOX` | Mestre–detalhe | Panel self **ou** `DocsWorkspace` / mailbox shell | `pages/inbox.vue` |
| `SETTINGS` | Shell com tabs + `NuxtPage` | `UDashboardPanel` + toolbar nav | `pages/settings.vue` |
| `SETTINGS_CHILD` | Conteúdo de settings (cards/form) | Chrome do **pai** ou reexport sob `/conta` | `pages/settings/*.vue` |
| `PANEL` | Painel próprio (detalhe/calendário) | `UDashboardPanel` self | settings ou customers |
| `WORKSPACE` | Fila work / split | `WorkQueueWorkspace` (embute panel) | inbox + customers |
| `AUTH` | Login / ativação / 2FA | `layouts/auth.vue` | fora do dashboard |
| `REDIRECT` | Alias / 301 / definePageMeta redirect | — | — |
| `CHILD` | Placeholder / redirect de submódulo | Chrome do pai ou middleware | — |

## Inventário de páginas (fonte de verdade)

| `arquivo` | arquétipo | `bundle` | template | notas |
|-----------|-----------|----------|----------|-------|
| `pages/activate.vue` | auth | `AUTH` | — | layout auth |
| `pages/admin/departments.vue` | redirect | `REDIRECT` | — | alias admin |
| `pages/admin/index.vue` | redirect | `REDIRECT` | — | → `/admin/offices` |
| `pages/admin/offices/[id].vue` | settings | `PANEL` | `pages/settings.vue` | detalhe office |
| `pages/admin/offices/index.vue` | lista | `LIST` | `pages/customers.vue` | tabela offices |
| `pages/admin/offices/new.vue` | settings | `PANEL` | `pages/settings.vue` | create office |
| `pages/admin/owner/index.vue` | redirect | `REDIRECT` | — | → offices |
| `pages/admin/serpro.vue` | settings-shell | `SETTINGS` | `pages/settings.vue` | console SERPRO |
| `pages/admin/serpro/catalog.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/configuration.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/contracts.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/dte-canary.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/index.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/rollout.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/admin/serpro/usage.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob serpro.vue |
| `pages/clients.vue` | settings-shell | `SETTINGS` | `pages/settings.vue` | tabs Lista/Dashboard |
| `pages/clients/dashboard.vue` | home-child | `SETTINGS_CHILD` | `pages/index.vue` | conteúdo home sob clients.vue |
| `pages/clients/index.vue` | lista | `LIST` | `pages/customers.vue` | lista sob clients.vue |
| `pages/clients/[id].vue` | settings-shell | `SETTINGS` | `pages/settings.vue` | seções do cliente |
| `pages/clients/[id]/cadastro.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/clients/[id]/certificado.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/clients/[id]/estabelecimentos.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/clients/[id]/index.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/clients/[id]/saidas.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/clients/[id]/sincronizacao.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | sob [id].vue |
| `pages/closing.vue` | lista | `LIST` | `pages/customers.vue` | fechamento |
| `pages/conta.vue` | settings-shell | `SETTINGS` | `pages/settings.vue` | Conta unificada |
| `pages/conta/assinatura.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport subscription |
| `pages/conta/consumo.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport usage |
| `pages/conta/departamentos.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport departments |
| `pages/conta/equipe.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport team |
| `pages/conta/escritorio.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport settings/index |
| `pages/conta/index.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/index.vue` | perfil usuário (UForm+UPageCard) |
| `pages/docs/catalog.vue` | mestre-detalhe | `INBOX` | `pages/inbox.vue` | DocsWorkspace |
| `pages/docs/index.vue` | mestre-detalhe | `INBOX` | `pages/inbox.vue` | DocsWorkspace |
| `pages/docs/[accessKey].vue` | mestre-detalhe | `INBOX` | `pages/inbox.vue` | DocsWorkspace |
| `pages/docs/import-batches.vue` | redirect | `REDIRECT` | — | alias imports |
| `pages/docs/imports/index.vue` | lista | `LIST` | `pages/customers.vue` | lotes |
| `pages/docs/imports/[id].vue` | lista | `LIST` | `pages/customers.vue` | itens do lote |
| `pages/exports.vue` | lista | `LIST` | `pages/customers.vue` | export jobs |
| `pages/first-access.vue` | auth | `AUTH` | — | layout auth |
| `pages/health.vue` | lista | `LIST` | `pages/customers.vue` | saúde ops |
| `pages/index.vue` | home | `HOME` | `pages/index.vue` | dashboard |
| `pages/login.vue` | auth | `AUTH` | — | layout auth |
| `pages/monitoring/index.vue` | home | `HOME` | `pages/index.vue` | overview fiscal |
| `pages/monitoring/clients/[clientId].vue` | lista | `LIST` | `pages/customers.vue` | detalhe cliente fiscal |
| `pages/monitoring/dctfweb/[submodule].vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/dctfweb/index.vue` | child | `CHILD` | — | redirect canônico |
| `pages/monitoring/declarations.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/fgts.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/guides.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/installments.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/mailbox.vue` | mestre-detalhe | `INBOX` | `pages/inbox.vue` | MailboxList + Mail |
| `pages/monitoring/mailbox/index.vue` | child | `CHILD` | — | empty selection |
| `pages/monitoring/mailbox/[id].vue` | child | `CHILD` | — | detalhe sob mailbox.vue |
| `pages/monitoring/registrations.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/simples-mei/[submodule].vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/simples-mei/index.vue` | child | `CHILD` | — | redirect canônico |
| `pages/monitoring/sitfis.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/monitoring/tax-processes.vue` | lista | `LIST` | `pages/customers.vue` | MonitoringModuleTable |
| `pages/notes/index.vue` | redirect | `REDIRECT` | — | legado → docs |
| `pages/notes/[accessKey].vue` | redirect | `REDIRECT` | — | legado → docs |
| `pages/onboarding.vue` | auth | `AUTH` | — | layout auth |
| `pages/settings.vue` | redirect | `REDIRECT` | — | → `/conta/escritorio` |
| `pages/settings/cte.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | conteúdo legado/reexport |
| `pages/settings/departments.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport em `/conta` |
| `pages/settings/index.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport escritório |
| `pages/settings/proxies.vue` | redirect | `REDIRECT` | — | → conta/escritorio |
| `pages/settings/subscription.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport assinatura |
| `pages/settings/team.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport equipe |
| `pages/settings/usage.vue` | settings-child | `SETTINGS_CHILD` | `pages/settings/*.vue` | reexport consumo |
| `pages/syncs.vue` | lista | `LIST` | `pages/customers.vue` | sincronizações |
| `pages/two-factor-challenge.vue` | auth | `AUTH` | — | layout auth |
| `pages/two-factor/setup.vue` | auth | `AUTH` | — | layout auth |
| `pages/work/calendar.vue` | panel | `PANEL` | `pages/customers.vue` | calendário |
| `pages/work/index.vue` | workspace | `WORKSPACE` | `pages/inbox.vue` | WorkQueueWorkspace |
| `pages/work/processes/index.vue` | lista | `LIST` | `pages/customers.vue` | processos |
| `pages/work/processes/[id].vue` | panel | `PANEL` | `pages/settings.vue` | detalhe processo |
| `pages/work/tasks/[id].vue` | workspace | `WORKSPACE` | `pages/inbox.vue` | WorkQueueWorkspace |
| `pages/work/templates/index.vue` | lista | `LIST` | `pages/customers.vue` | templates |

## Cascas de produto (adapters do template)

Estas peças **embutem** o arquétipo; páginas finas que as usam contam como alinhadas:

| Adapter | Arquétipo | Origem template |
|---------|-----------|-----------------|
| `components/monitoring/ModuleTable.vue` | LIST | `customers.vue` (`:ui` literal + panel/nav/footer) |
| `components/docs/Workspace.vue` | INBOX | `inbox.vue` |
| `components/work/WorkQueueWorkspace.vue` | INBOX/WORKSPACE | inbox + lista |
| `components/dashboard/DashboardContent.vue` | largura produto | substitui `max-w-2xl` do settings demo |
| `utils/table-ui.ts` → `DASHBOARD_TABLE_UI` | LIST | tokens literais de `customers.vue` |

## Shell global

| Template | Produto |
|----------|---------|
| `layouts/default.vue` | `layouts/default.vue` |
| `TeamsMenu.vue` | `OfficeIdentity.vue` |
| `UserMenu.vue` | `UserMenu.vue` |
| `NotificationsSlideover.vue` | `NotificationsSlideover.vue` |
| `useDashboard.ts` | `useDashboard.ts` |
| `layouts/auth` (não existe no demo) | `layouts/auth.vue` |

## Contagem

- **80** páginas inventariadas
- Bundles: AUTH, REDIRECT, HOME, LIST, INBOX, SETTINGS, SETTINGS_CHILD, PANEL, WORKSPACE, CHILD
- Gate amplo exige 1:1 com este inventário (sem página órfã nem fantasma)
