## 1. N0 — Writers path-only

- [x] 1.1 Em `useFiscalModulePortfolio`: remover hydrate/serialize via `useListFilterQuery` / `MONITORING_LIST_QUERY_SCHEMA`; `syncUrl` limpa query (padrão `useServerPage`). Cobre Simples, MEI, DCTFWeb, FGTS, Parcelamentos, Sitfis, Declarações
- [x] 1.2 Remover ou orphan-check de `MONITORING_LIST_QUERY_SCHEMA` em `useListFilterQuery.ts` se não houver outro consumer
  - Depende de: 1.1
- [x] 1.3 Em `pages/monitoring/guides.vue`: remover hydrate de sort na query; `syncGuidesUrl` só strip; sort/payment/page nos refs + API

## 2. N1 — Testes

- [x] 2.1 Atualizar `simples-nacional-portfolio-e2e.test.ts` para URL limpa / sem `serializeListFilterQuery` no portfolio
  - Depende de: 1.1
- [x] 2.2 Ajustar `shell-datatable-sort-contract.test.ts` (e correlatos) se assertarem query Nuxt em Guias; manter assert de sort via API
  - Depende de: 1.3

## 3. N2 — Gates

- [x] 3.1 Gates web: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (filtros relevantes) em `apps/web`
  - Depende de: 2.1, 2.2
  - Evidência: vitest 14/14 nos 3 arquivos tocados; eslint limpo nos composables/guides/e2e; lint/typecheck globais com falhas pré-existentes (quotes em testes antigos; TS em ClientForm/Portfolio handlers)
- [x] 3.2 `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validate da change `monitoring-url-sem-query`
  - Depende de: 2.1, 2.2
  - Evidência: specs 49 passed; `validate monitoring-url-sem-query --strict` ok
