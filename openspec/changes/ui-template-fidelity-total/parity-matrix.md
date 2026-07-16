# Matriz de paridade — ui-template-fidelity-total

Template: `.reference/nuxt-dashboard-template` @ `0f30c09d697160ef5dd0aaaec27fae8d7195d930`.

**Estado global: `PENDING`.** A matriz contém os 51 arquivos atuais, mas todas as evidências produzidas sob wrappers, híbridos ou carregamento incremental foram reabertas após a decisão de migração integral de 2026-07-16.

## Bundles canônicos

| Bundle | Arquivos exatos da referência |
|--------|-------------------------------|
| `SHELL` | `app/layouts/default.vue`; `app/components/TeamsMenu.vue`; `app/components/UserMenu.vue`; `app/components/NotificationsSlideover.vue`; `app/composables/useDashboard.ts` |
| `HOME` | `app/pages/index.vue`; `app/components/home/HomeStats.vue`; `HomeChart.client.vue`; `HomeChart.server.vue`; `HomeSales.vue`; `HomeDateRangePicker.vue`; `HomePeriodSelect.vue` |
| `LIST` | `app/pages/customers.vue`; `app/components/customers/AddModal.vue`; `app/components/customers/DeleteModal.vue` |
| `MASTER_DETAIL` | `app/pages/inbox.vue`; `app/components/inbox/InboxList.vue`; `app/components/inbox/InboxMail.vue` |
| `SETTINGS_FORM` | `app/pages/settings.vue`; `app/pages/settings/index.vue` |
| `SETTINGS_CARD_LIST` | `app/pages/settings.vue`; `app/pages/settings/members.vue`; `app/components/settings/MembersList.vue` |
| `AUTH` | Sem arquivo no template dashboard; contrato Nuxt UI separado e `template_dashboard=N/A` justificado |
| `REDIRECT` | Sem superfície visual; destino/query/histórico e ausência de chrome são obrigatórios |
| `ROUTE_PARENT` | Arquivo técnico sem chrome; deve ser pass-through ou removido se as rotas puderem ser preservadas |

Cada superfície visual escolhe um único bundle. `LIST+HOME`, `LIST+MASTER_DETAIL`, `SETTINGS+LIST` e outras somas entre bundles são inválidas. `LIST` já inclui seus modais canônicos; `HOME` e `SETTINGS_*` já incluem seus componentes internos.

## Shell e chrome global

| Destino atual | Bundle | Origem | Regra | Status |
|---------------|--------|--------|-------|--------|
| `frontend/app/layouts/default.vue` | `SHELL` | `app/layouts/default.vue` | cópia direta; `OfficeIdentity` ocupa a geometria de `TeamsMenu` | `PENDING` |
| `frontend/app/layouts/auth.vue` | `AUTH` | n/a | fora do dashboard por ausência de fonte auth | `PENDING` |
| `frontend/app/components/OfficeIdentity.vue` | `SHELL` | `app/components/TeamsMenu.vue` | só memberships válidas; sem `office_id` livre | `PENDING` |
| `frontend/app/components/UserMenu.vue` | `SHELL` | `app/components/UserMenu.vue` | remover demos; manter forma | `PENDING` |
| `frontend/app/components/NotificationsSlideover.vue` | `SHELL` | `app/components/NotificationsSlideover.vue` | dados reais sanitizados | `PENDING` |
| `frontend/app/components/shell/ListShell.vue` | remover | n/a | wrapper de panel/navbar proibido | `PENDING-REMOVE` |
| `frontend/app/components/monitoring/ModuleTable.vue` | remover | n/a | wrapper de lista proibido | `PENDING-REMOVE` |
| `frontend/app/components/docs/Workspace.vue` | remover | n/a | tabela+modal híbrido proibido | `PENDING-REMOVE` |
| `frontend/app/components/shell/StickyTableFilters.vue` | remover | n/a | sticky/teleport ausente no template | `PENDING-REMOVE` |
| `frontend/app/components/shell/InfiniteTableLoader.vue` | remover | n/a | auto-load/sentinel revogado | `PENDING-REMOVE` |
| `frontend/app/components/shell/TableFooter.vue` | remover | n/a | footer deve ficar diretamente na page `LIST` | `PENDING-REMOVE` |
| `frontend/app/components/shell/KpiStrip.vue` e `monitoring/KpiStrip.vue` | remover | n/a | métricas devem usar `HomeStats` | `PENDING-REMOVE` |

## Páginas atuais

| Arquivo | Rota | Bundle único | Renderização destino | Adaptações permitidas | Status |
|---------|------|--------------|--------------------|-----------------------|--------|
| `pages/admin/departments.vue` | `/admin/departments` | `SETTINGS_CARD_LIST` | direto | labels, API, ADMIN confirmado | `PENDING` |
| `pages/admin/index.vue` | `/admin` | `SETTINGS_FORM` | direto | labels, API, ADMIN confirmado | `PENDING` |
| `pages/clients/[id]/cadastro.vue` | `/clients/:id/cadastro` | `SETTINGS_FORM` | conteúdo do pai `pages/clients/[id].vue` | campos/API/permissões | `PENDING` |
| `pages/clients/[id]/certificado.vue` | `/clients/:id/certificado` | `SETTINGS_FORM` | conteúdo do pai `pages/clients/[id].vue` | upload sem eco de segredo | `PENDING` |
| `pages/clients/[id]/estabelecimentos.vue` | `/clients/:id/estabelecimentos` | `SETTINGS_CARD_LIST` | conteúdo do pai `pages/clients/[id].vue` | dados tenant-scoped | `PENDING` |
| `pages/clients/[id]/index.vue` | `/clients/:id` | `SETTINGS_FORM` | conteúdo do pai `pages/clients/[id].vue` | resumo em cards canônicos | `PENDING` |
| `pages/clients/[id]/saidas.vue` | `/clients/:id/saidas` | `SETTINGS_FORM` | conteúdo do pai `pages/clients/[id].vue` | dados/ações autorizadas | `PENDING` |
| `pages/clients/[id]/sincronizacao.vue` | `/clients/:id/sincronizacao` | `SETTINGS_FORM` | conteúdo do pai `pages/clients/[id].vue` | estados reais sanitizados | `PENDING` |
| `pages/clients/[id].vue` | `/clients/:id/*` | `SETTINGS_FORM` | direto + `NuxtPage` no slot canônico | identidade como conteúdo, sem aside custom | `PENDING` |
| `pages/clients/dashboard.vue` | `/clients/dashboard` | `HOME` | direto | métricas reais | `PENDING` |
| `pages/clients/index.vue` | `/clients` | `LIST` | direto | paginação server-side | `PENDING` |
| `pages/clients.vue` | nesting `/clients/*` | `ROUTE_PARENT` | pass-through ou remover preservando rotas | nenhuma apresentação | `PENDING-REMOVE` |
| `pages/closing.vue` | `/closing` | `LIST` | direto | dados/ações reais | `PENDING` |
| `pages/docs/[accessKey].vue` | `/docs/:accessKey` | `MASTER_DETAIL` | Inbox com seleção ativa | rota/dados fiscais sanitizados | `PENDING` |
| `pages/docs/catalog.vue` | `/docs/catalog` | `MASTER_DETAIL` | direto | filtro de visão por documento | `PENDING` |
| `pages/docs/import-batches.vue` | `/docs/import-batches` | `REDIRECT` | `/docs/imports` | preservar query/histórico | `PENDING` |
| `pages/docs/imports/[id].vue` | `/docs/imports/:id` | `SETTINGS_FORM` | direto | resumo/ações do lote em cards | `PENDING` |
| `pages/docs/imports/index.vue` | `/docs/imports` | `LIST` | direto | paginação server-side | `PENDING` |
| `pages/docs/index.vue` | `/docs` | `MASTER_DETAIL` | direto | visão por empresa | `PENDING` |
| `pages/exports.vue` | `/exports` | `LIST` | direto | polling e ações reais | `PENDING` |
| `pages/health.vue` | `/health` | `LIST` | direto | estados operacionais | `PENDING` |
| `pages/index.vue` | `/` | `HOME` | direto | KPIs, série e lista reais nos blocos canônicos | `PENDING` |
| `pages/login.vue` | `/login` | `AUTH` | layout auth | Sanctum e mensagens sanitizadas | `PENDING` |
| `pages/monitoring/clients/[clientId].vue` | `/monitoring/clients/:clientId` | `SETTINGS_FORM` | direto | seções lazy e tenant-safe | `PENDING` |
| `pages/monitoring/dctfweb.vue` | `/monitoring/dctfweb` | `LIST` | direto | filtros/ações fiscais autorizados | `PENDING` |
| `pages/monitoring/declarations.vue` | `/monitoring/declarations` | `MASTER_DETAIL` | direto | lista/detalhe reais | `PENDING` |
| `pages/monitoring/fgts.vue` | `/monitoring/fgts` | `MASTER_DETAIL` | direto | cobertura parcial explícita | `PENDING` |
| `pages/monitoring/guides.vue` | `/monitoring/guides` | `MASTER_DETAIL` | direto | estados independentes | `PENDING` |
| `pages/monitoring/index.vue` | `/monitoring` | `HOME` | direto | módulos/métricas reais nos blocos canônicos | `PENDING` |
| `pages/monitoring/installments.vue` | `/monitoring/installments` | `MASTER_DETAIL` | direto | lista/detalhe reais | `PENDING` |
| `pages/monitoring/mailbox/[id].vue` | `/monitoring/mailbox/:id` | `MASTER_DETAIL` | detalhe do pai `pages/monitoring/mailbox.vue` | mensagem sanitizada | `PENDING` |
| `pages/monitoring/mailbox/index.vue` | `/monitoring/mailbox` | `MASTER_DETAIL` | empty do pai `pages/monitoring/mailbox.vue` | nenhuma | `PENDING` |
| `pages/monitoring/mailbox.vue` | `/monitoring/mailbox/*` | `MASTER_DETAIL` | direto + `NuxtPage` no detalhe | API real | `PENDING` |
| `pages/monitoring/simples-mei.vue` | `/monitoring/simples-mei` | `LIST` | direto | filtros/ações fiscais autorizados | `PENDING` |
| `pages/monitoring/sitfis.vue` | `/monitoring/sitfis` | `MASTER_DETAIL` | direto | estado e próxima ação | `PENDING` |
| `pages/notes/[accessKey].vue` | `/notes/:accessKey` | `REDIRECT` | `/docs/:accessKey` | preservar query/histórico | `PENDING` |
| `pages/notes/index.vue` | `/notes` | `REDIRECT` | `/docs` | preservar query/histórico | `PENDING` |
| `pages/settings/cte.vue` | `/settings/cte` | `REDIRECT` | `/docs/catalog` com filtro CT-e | replace sem chrome | `PENDING` |
| `pages/settings/index.vue` | `/settings` | `SETTINGS_FORM` | conteúdo do pai `pages/settings.vue` | dados/formulários reais | `PENDING` |
| `pages/settings/proxies.vue` | `/settings/proxies` | `SETTINGS_CARD_LIST` | conteúdo do pai `pages/settings.vue` | procurações tenant-scoped | `PENDING` |
| `pages/settings/subscription.vue` | `/settings/subscription` | `SETTINGS_FORM` | conteúdo do pai `pages/settings.vue` | plano sanitizado | `PENDING` |
| `pages/settings/usage.vue` | `/settings/usage` | `SETTINGS_CARD_LIST` | conteúdo do pai `pages/settings.vue` | consumo sem custo global | `PENDING` |
| `pages/settings.vue` | `/settings/*` | `SETTINGS_FORM` | direto + `NuxtPage` | seções permitidas | `PENDING` |
| `pages/syncs.vue` | `/syncs` | `MASTER_DETAIL` | direto | canais/estados reais | `PENDING` |
| `pages/two-factor/setup.vue` | `/two-factor/setup` | `AUTH` | layout auth | TOTP sem segredo persistido/renderizado | `PENDING` |
| `pages/two-factor-challenge.vue` | `/two-factor-challenge` | `AUTH` | layout auth | confirmação segura | `PENDING` |
| `pages/work/calendar.vue` | `/work/calendar` | `HOME` | direto | calendário no conteúdo canônico, sem shell extra | `PENDING` |
| `pages/work/index.vue` | `/work` | `MASTER_DETAIL` | direto | fila/detalhe reais | `PENDING` |
| `pages/work/processes/[id].vue` | `/work/processes/:id` | `SETTINGS_FORM` | direto | seções/ações autorizadas | `PENDING` |
| `pages/work/processes/index.vue` | `/work/processes` | `LIST` | direto | paginação server-side | `PENDING` |
| `pages/work/templates/index.vue` | `/work/templates` | `LIST` | direto | modal canônico para mutação curta | `PENDING` |

Total atual: **51 páginas**.

## Contrato de aceite por linha

Cada linha será transposta para manifesto estruturado com:

`pai_nuxt`, `layout_auth`, `bundle`, `origens_exatas`, `cadeia_componentes`, `fixture`, `estrutura`, `funcional`, `estados`, `papeis_tenancy`, `a11y`, `visual_1440`, `visual_390`, `overflow_360`, `seguranca`, `evidencias` e `aceite`.

Regras:

- todos os campos aplicáveis começam em `PENDING`;
- não há exceção estrutural ou interação alternativa;
- página filha só passa com pai/componentes e smoke da URL própria;
- auth e redirect usam `N/A` somente onde a ausência de superfície dashboard é verificável;
- arquivo removido só sai do denominador depois de filesystem, rota e matriz serem atualizados juntos;
- o aceite global só pode ser `PASS` quando todas as linhas restantes estiverem integralmente verdes.

Estado desta revisão: **0/51 com aceite integral sob a regra nova; global `PENDING`**.

## Baseline lexical histórico

`pnpm --dir frontend test:fidelity` já conferiu 51 nomes contra 51 linhas, mas o script atual aceita wrappers e sinais textuais agora proibidos. Seu resultado é histórico e não promove nenhuma linha até o gate semântico/DOM ser reescrito.

## Únicas adaptações globais autorizadas

| Adaptação | Limite |
|-----------|--------|
| `OfficeIdentity` no lugar de `TeamsMenu` | mesma geometria; somente memberships autorizadas |
| Labels, rotas e ícones de domínio | não alteram posição, hierarquia ou densidade |
| API Laravel/Sanctum e estados reais | não alteram composição; paginação permanece server-side |
| Permissões e tenancy | podem ocultar ação proibida sem criar controle alternativo |
| Remoção de mocks, cookie marketing e view-source | conteúdo demo não pertence ao produto |
| Auth fora do dashboard | template não possui fonte auth; contrato separado obrigatório |

Infinite scroll, sentinel, sticky/virtualização, modal desktop no lugar de painel, largura alternativa, wrapper de chrome e footer ausente **não** são adaptações autorizadas.
