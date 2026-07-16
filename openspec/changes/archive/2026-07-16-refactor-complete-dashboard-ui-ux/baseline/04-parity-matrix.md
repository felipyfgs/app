# 1.4 Matriz de paridade (destino → arquétipo → template `0f30c09`)

**Template:** `.reference/nuxt-dashboard-template` @ `0f30c09`  
**Evidência:** coluna preenchida ao concluir a tarefa da família; baseline = inventário 51 + design §10.

Legenda de status: `pending` · `in_progress` · `done` · `n/a` (redirect)

| Destino | Rota | Arquétipo | Fonte template (arquivo/bloco) | Divergência autorizada | Evidência / status |
|---------|------|-----------|--------------------------------|------------------------|--------------------|
| `layouts/default.vue` | shell | Shell | `app/layouts/default.vue` (`UDashboardGroup`, sidebar, search, 2 menus) | `OfficeIdentity` no lugar de `TeamsMenu`; nav por permissão; sem cookie toast | done · tarefa 2.3 |
| `layouts/auth.vue` | auth shell | Auth layout | (produto; sem clone idêntico) + `UPageCard` pattern | Branding escritórios contábeis; sem portal cliente | done · 3.1 |
| `pages/index.vue` | `/` | Home | `pages/index.vue` + `components/home/*` | Blocos Trabalho/Fiscal/Ops; progresso dept.; sem pizza Makro | done · 4.x |
| `pages/login.vue` | `/login` | Auth | `UAuthForm` + `UPageCard` (Nuxt UI, não página template) | Redirect por papel; pt-BR | done · 3.2 |
| `pages/two-factor-challenge.vue` | `/two-factor-challenge` | Auth | `UAuthForm` | OTP/recuperação | done · 3.3 |
| `pages/two-factor/setup.vue` | `/two-factor/setup` | Auth + Stepper | `UForm` + `UStepper` | 3 etapas; sem UAuthForm | done · 3.4 |
| `pages/clients.vue` | shell | Settings | `pages/settings.vue` toolbar/tabs | Tabs Lista/Dashboard; uma ação primária | done · 5.1 |
| `pages/clients/index.vue` | `/clients` | Customers | `pages/customers.vue` + `components/customers/*` | Estado tabular **local**; A1/captura | done · 5.2 |
| `pages/clients/dashboard.vue` | `/clients/dashboard` | Home | `pages/index.vue` stats/cards | Deep-links reais carteira | done · 5.3 |
| `pages/clients/[id].vue` | shell | Settings | `pages/settings.vue` | Identidade raiz; seções path | done · 5.4 |
| `pages/clients/[id]/index.vue` | resumo | Home/Settings | `settings/*` + home cards | Onboarding real | done · 5.5 |
| `pages/clients/[id]/cadastro.vue` | form | Settings form | `settings/index.vue` cards/form | 422/409 | done · 5.6 |
| `pages/clients/[id]/estabelecimentos.vue` | list | Members | `settings/members.vue` + `MembersList` | Matriz/filiais | done · 5.7 |
| `pages/clients/[id]/certificado.vue` | form | Settings form | settings cards + upload UI | Sem download/senha/recuperação | done · 5.8 |
| `pages/clients/[id]/sincronizacao.vue` | list | Settings + list | customers/settings | Cursor read-only | done · 5.9 |
| `pages/clients/[id]/saidas.vue` | list | Settings + list | customers | Gates captura | done · 5.10 |
| `pages/docs/index.vue` | `/docs` | Inbox/Customers | `inbox.vue` / `customers.vue` | Visão por cliente | done · 6.1 |
| `pages/docs/catalog.vue` | `/docs/catalog` | Customers + detail | `customers.vue` + slideover | Seleção/export | done · 6.2 |
| `pages/docs/[accessKey].vue` | detalhe | Detail | painel detalhe inbox-like | Resposta indistinguível outro tenant | done · 6.3 |
| `pages/docs/imports/index.vue` | lista | Customers | `customers.vue` | Histórico + primária | done · 6.4 |
| `pages/docs/imports/[id].vue` | detalhe | Settings/Detail | settings + progress | CSV sanitizado | done · 6.5 |
| `pages/docs/import-batches.vue` | alias | Redirect | n/a | Sem chrome | done · 6.7 |
| `pages/notes/index.vue` | alias | Redirect | n/a | → `/docs` | done · 6.8 |
| `pages/notes/[accessKey].vue` | alias | Redirect | n/a | → `/docs/:key` | done · 6.9 |
| `pages/monitoring/index.vue` | `/monitoring` | Home | `pages/index.vue` | Competência URL; blocos reais | done · 7.1 |
| `pages/monitoring/simples-mei.vue` | módulo | HomeStats + Customers | home stats + customers | Submódulos URL | done · 7.2 |
| `pages/monitoring/dctfweb.vue` | módulo | HomeStats + Customers | idem | Eixos independentes | done · 7.3 |
| `pages/monitoring/installments.vue` | módulo | HomeStats + Customers | idem | Parcelas | done · 7.4 |
| `pages/monitoring/sitfis.vue` | módulo | Customers + Slideover | customers + slideover | Findings normalizados | done · 7.5 |
| `pages/monitoring/mailbox.vue` | shell | Inbox | `pages/inbox.vue` | Triagem interna | done · 7.6 |
| `pages/monitoring/mailbox/index.vue` | vazio | Inbox empty | inbox empty | Neutro | done · 7.7 |
| `pages/monitoring/mailbox/[id].vue` | mail | InboxMail | `components/inbox/InboxMail.vue` | Anexos protegidos | done · 7.8 |
| `pages/monitoring/declarations.vue` | módulo | HomeStats + Customers | home+customers | Evidência | done · 7.9 |
| `pages/monitoring/guides.vue` | módulo | Customers + Modal | customers | Download efêmero | done · 7.10 |
| `pages/monitoring/fgts.vue` | módulo | HomeStats + Customers | home+customers | Banner parcial permanente | done · 7.11 |
| `pages/monitoring/clients/[clientId].vue` | detalhe | Settings | `settings.vue` | Seções lazy | done · 7.12 |
| `pages/work/index.vue` | `/work` | Inbox | `inbox.vue` | Filtros URL; fila | done · 8.1 |
| `pages/work/calendar.vue` | `/work/calendar` | Home + Inbox | index + inbox composition | Mês/Semana/Dia; `UCalendar` mini | done · 8.2–8.5 |
| `pages/work/processes/index.vue` | lista | Customers | `customers.vue` | Filtros URL | done · 8.6 |
| `pages/work/processes/[id].vue` | detalhe | Settings | `settings.vue` | Checklist/evidência | done · 8.7 |
| `pages/work/templates/index.vue` | lista+modal | Customers + AddModal | customers + `AddModal.vue` | Stepper geração | done · 8.8–8.9 |
| `pages/closing/index.vue` | `/closing` | Customers/HomeStats | customers + home stats | Competência URL | done · 9.1 |
| `pages/exports/index.vue` | `/exports` | Customers + flow | customers + stepper | Escopo explícito | done · 9.2–9.3 |
| `pages/syncs/index.vue` | `/syncs` | Customers + Slideover | customers | Cursor/posição | done · 9.4 |
| `pages/health/index.vue` | `/health` | Customers | customers | Severidade/origem | done · 9.5 |
| `pages/settings.vue` | shell | Settings | `pages/settings.vue` | Permissões seções | done · 10.1 |
| `pages/settings/index.vue` | Integra | Settings form | `settings/index.vue` | Sem contrato global | done · 10.2 |
| `pages/settings/cte.vue` | CT-e | Settings form/list | settings | Sem editar cursor | done · 10.3 |
| `pages/settings/proxies.vue` | procurações | Settings + table | settings + table | Evidência ref. | done · 10.4 |
| `pages/settings/usage.vue` | consumo | Settings + tables | settings | Tenant-only | done · 10.5 |
| `pages/settings/subscription.vue` | assinatura | Settings | settings | Sem gateway | done · 10.6 |
| `pages/admin/index.vue` | `/admin` | Settings | settings | A1 escritório; gates | done · 10.7 |
| `pages/admin/departments.vue` | `/admin/departments` | Members/list | `settings/members.vue` | Carga/progresso | done · 10.8 |

## Componentes shell

| Destino | Fonte template | Divergência | Status |
|---------|----------------|-------------|--------|
| `OfficeIdentity.vue` | **não** `TeamsMenu` | Memberships válidas only | done · 2.4 |
| `UserMenu.vue` | `UserMenu.vue` | Sem demos; 2FA/tema/logout | done · 2.5 |
| `NotificationsSlideover.vue` | `NotificationsSlideover.vue` | Deep-links reais; papéis | done · 2.6 |
| Presets `table-ui.ts` | `:ui` de `customers.vue` | 3 presets apenas | done · 2.9 |
