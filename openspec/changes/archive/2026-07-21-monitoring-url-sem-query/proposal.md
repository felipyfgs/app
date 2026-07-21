## Why

Superfícies de monitoramento ainda espelham filtros/sort/paginação na query Nuxt (ex.: `/monitoring/simples?sort=rbt12`), enquanto o contrato do hub já é path-only (`monitoringCanonicalQuery`, `useServerPage.syncUrl`, detalhe de cliente). Há **dois writers** reais; o portfolio sozinho contamina seis rotas.

## What Changes

- Inventário fechado dos writers de query sob `/monitoring/*` (abaixo) passa a path-only.
- `useFiscalModulePortfolio`: estado local + `syncUrl` que **limpa** query (não serializa); remove `useListFilterQuery` / `MONITORING_LIST_QUERY_SCHEMA` do fluxo do portfolio.
- `guides.vue`: idem (`syncGuidesUrl` = strip).
- Bookmarks legados com query → replace para o path canônico.
- Specs/testes alinhados; listas fora de `/monitoring/*` **permanecem** com sync de query.

### Inventário — writers (em escopo)

| Writer | Consumidores / rotas |
|---|---|
| `useFiscalModulePortfolio.ts` | `simples-mei/Portfolio.vue` → `/monitoring/simples`, `/monitoring/mei`; `dctfweb/index.vue`; `fgts.vue`; `installments.vue`; `sitfis.vue`; `declarations.vue` |
| `pages/monitoring/guides.vue` | `/monitoring/guides` |
| Helper | `MONITORING_LIST_QUERY_SCHEMA` em `useListFilterQuery.ts` (só portfolio; descontinuar no composable) |

### Inventário — já corretos (não mudar comportamento)

- `useServerPage.syncUrl` (strip) → mailbox
- `monitoring-nav.ts` (`monitoringCanonicalQuery` / locations com `query: {}`)
- `monitoring/clients/[clientId].vue` (query legado → path)
- Redirects `simples-mei` → path
- `components/monitoring/*` (sem `router.replace` com query)

### Inventário — fora de escopo

`/clients`, docs/`ByClient`, `/work`, `/closing`, `/health`, login `redirect`, params HTTP da API (`?sort=` no client `$fetch`).

## Capabilities

### New Capabilities

- `monitoring-url-canonical`: URLs de monitoramento ficam só no path; filtros/tabs/sort/paginação não entram na query Nuxt.

### Modified Capabilities

- `shell-datatable-sort-contract`: Guias/carteiras de monitoramento ordenam via API sem sync de `sort` na query Nuxt.
- `simples-nacional-portfolio-e2e`: asserts passam a exigir URL limpa + estado local.

## Impact

- Web: `useFiscalModulePortfolio` (6 superfícies), `guides.vue`, `MONITORING_LIST_QUERY_SCHEMA` (uso), testes `simples-nacional-portfolio-e2e`, `shell-datatable-sort-contract` (e correlatos de filtros se assertarem URL).
- API: sem mudança de contrato HTTP.
- Non-goals: SERPRO live, parecer jurídico, mutações fiscais, flags ON, canais SEFAZ, mei no Compose, ops backup/restore, sync de query em `/clients` ou `/docs`.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `monitoring-simples-route`, `shell-datatable-sort-contract`, `simples-nacional-portfolio-e2e`
- Depende de: nenhuma
- Capability/contrato: `monitoring-url-canonical` (novo); deltas nas duas modificadas
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply path-only em todo o hub de monitoramento
- Paralelismo: ok com changes que não editem os writers listados / esses contratos
