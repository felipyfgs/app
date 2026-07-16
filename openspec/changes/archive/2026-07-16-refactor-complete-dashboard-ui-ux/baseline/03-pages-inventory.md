# 1.3 Inventário de páginas

**Registrado em:** 2026-07-15  
**Comando:** `find frontend/app/pages -name '*.vue' | sort | wc -l`  
**Total:** **51** arquivos (confirmado; igual ao inventário inicial da proposal)

Arquivo bruto: `pages-inventory-2026-07-15.txt`

## Lista completa

| # | Arquivo | Rota | Família |
|--:|---------|------|---------|
| 1 | `pages/admin/departments.vue` | `/admin/departments` | Config/Admin |
| 2 | `pages/admin/index.vue` | `/admin` | Config/Admin |
| 3 | `pages/clients.vue` | shell `/clients*` | Clientes |
| 4 | `pages/clients/[id].vue` | shell `/clients/:id*` | Clientes |
| 5 | `pages/clients/[id]/cadastro.vue` | `/clients/:id/cadastro` | Clientes |
| 6 | `pages/clients/[id]/certificado.vue` | `/clients/:id/certificado` | Clientes |
| 7 | `pages/clients/[id]/estabelecimentos.vue` | `/clients/:id/estabelecimentos` | Clientes |
| 8 | `pages/clients/[id]/index.vue` | `/clients/:id` | Clientes |
| 9 | `pages/clients/[id]/saidas.vue` | `/clients/:id/saidas` | Clientes |
| 10 | `pages/clients/[id]/sincronizacao.vue` | `/clients/:id/sincronizacao` | Clientes |
| 11 | `pages/clients/dashboard.vue` | `/clients/dashboard` | Clientes |
| 12 | `pages/clients/index.vue` | `/clients` | Clientes |
| 13 | `pages/closing/index.vue` | `/closing` | Operações |
| 14 | `pages/docs/[accessKey].vue` | `/docs/:accessKey` | Documentos |
| 15 | `pages/docs/catalog.vue` | `/docs/catalog` | Documentos |
| 16 | `pages/docs/import-batches.vue` | `/docs/import-batches` (alias) | Documentos |
| 17 | `pages/docs/imports/[id].vue` | `/docs/imports/:id` | Documentos |
| 18 | `pages/docs/imports/index.vue` | `/docs/imports` | Documentos |
| 19 | `pages/docs/index.vue` | `/docs` | Documentos |
| 20 | `pages/exports/index.vue` | `/exports` | Operações |
| 21 | `pages/health/index.vue` | `/health` | Operações |
| 22 | `pages/index.vue` | `/` | Home |
| 23 | `pages/login.vue` | `/login` | Auth |
| 24 | `pages/monitoring/clients/[clientId].vue` | `/monitoring/clients/:clientId` | Monitoramento |
| 25 | `pages/monitoring/dctfweb.vue` | `/monitoring/dctfweb` | Monitoramento |
| 26 | `pages/monitoring/declarations.vue` | `/monitoring/declarations` | Monitoramento |
| 27 | `pages/monitoring/fgts.vue` | `/monitoring/fgts` | Monitoramento |
| 28 | `pages/monitoring/guides.vue` | `/monitoring/guides` | Monitoramento |
| 29 | `pages/monitoring/index.vue` | `/monitoring` | Monitoramento |
| 30 | `pages/monitoring/installments.vue` | `/monitoring/installments` | Monitoramento |
| 31 | `pages/monitoring/mailbox.vue` | shell mailbox | Monitoramento |
| 32 | `pages/monitoring/mailbox/[id].vue` | `/monitoring/mailbox/:id` | Monitoramento |
| 33 | `pages/monitoring/mailbox/index.vue` | `/monitoring/mailbox` | Monitoramento |
| 34 | `pages/monitoring/simples-mei.vue` | `/monitoring/simples-mei` | Monitoramento |
| 35 | `pages/monitoring/sitfis.vue` | `/monitoring/sitfis` | Monitoramento |
| 36 | `pages/notes/[accessKey].vue` | `/notes/:accessKey` (alias) | Documentos |
| 37 | `pages/notes/index.vue` | `/notes` (alias) | Documentos |
| 38 | `pages/settings.vue` | shell `/settings*` | Config |
| 39 | `pages/settings/cte.vue` | `/settings/cte` | Config |
| 40 | `pages/settings/index.vue` | `/settings` | Config |
| 41 | `pages/settings/proxies.vue` | `/settings/proxies` | Config |
| 42 | `pages/settings/subscription.vue` | `/settings/subscription` | Config |
| 43 | `pages/settings/usage.vue` | `/settings/usage` | Config |
| 44 | `pages/syncs/index.vue` | `/syncs` | Operações |
| 45 | `pages/two-factor-challenge.vue` | `/two-factor-challenge` | Auth |
| 46 | `pages/two-factor/setup.vue` | `/two-factor/setup` | Auth |
| 47 | `pages/work/calendar.vue` | `/work/calendar` | Trabalho |
| 48 | `pages/work/index.vue` | `/work` | Trabalho |
| 49 | `pages/work/processes/[id].vue` | `/work/processes/:id` | Trabalho |
| 50 | `pages/work/processes/index.vue` | `/work/processes` | Trabalho |
| 51 | `pages/work/templates/index.vue` | `/work/templates` | Trabalho |

## Rotas concorrentes a observar

- Work e departments ainda em evolução (`add-operational-process-management`).
- CTE/importações podem acrescentar seções em clients/settings (`complete-cte-*`, `add-office-autxml-*`).
- Ao surgir arquivo novo em `pages/**`, acrescentar linha aqui e na matriz `04-parity-matrix.md` antes do aceite.
