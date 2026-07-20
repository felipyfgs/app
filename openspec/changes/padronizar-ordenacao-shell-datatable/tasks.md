## 1. N0 — Sort quebrado / enganoso

- [x] 1.1 Wire ordenação em `guides.vue` (map coluna→API, default `due_at` desc, `watch(sorting)`, URL `sort`/`sort_direction`; remover sort de `id`)
- [x] 1.2 Remover `sortHeader` fantasma em `registrations.vue` e `tax-processes.vue` (`enableSorting: false`)
- [x] 1.3 Desabilitar sort da coluna `situation` em `installments.vue`
  - Verificado: `SORT_COLUMN_TO_API` já mapeia `situation` → mantido com `sortHeader` (sem regressão)

## 2. N1 — Sort parcial + empty

- [x] 2.1 Sync URL `sort`/`sort_direction` em `ByClient.vue`
  Depende de: 1.1
- [x] 2.2 Dropar `cnpj` da whitelist de sort em clientes (`ClientCatalogList` / schema)
  Depende de: 1.1
- [x] 2.3 Mover empty para `#empty` em `syncs.vue`, `health.vue`, `admin/serpro/contracts.vue` (e ByClient/Catalog se aplicável)
  Depende de: 1.2

## 3. N2 — Testes e gates

- [x] 3.1 Testes unitários de contrato (`sortHeader` ↔ API; guides sem sort `id`; registrations/tax-processes sem `sortHeader`)
  Depende de: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3
  - Evidência: `pnpm exec vitest run tests/unit/shell-datatable-sort-contract.test.ts …` — 21 passed
- [x] 3.2 Gates web: `pnpm run test` (filtros relevantes) + `pnpm run typecheck`; `openspec validate --specs --strict` na change
  Depende de: 3.1
  - Evidência: vitest OK; `openspec validate padronizar-ordenacao-shell-datatable --type change --strict` PASS; typecheck com erros pré-existentes fora do escopo
