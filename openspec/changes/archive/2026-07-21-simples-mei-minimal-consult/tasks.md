## 1. N0 — Tabelas minimalistas

- [x] 1.1 Enxugar `pgdasd-table.ts`: tracking com 1 ícone; atalho Consultar na coluna Consulta
- [x] 1.2 Enxugar `pgmei-table.ts`: tracking com 1 ícone; atalho Consultar na coluna Consulta

## 2. N1 — Toolbar Consultar

- [x] 2.1 Botão Consultar em `SelectionActions` (PGDAS-D) via enqueueReadUpdate
  - Depende de: 1.1
- [x] 2.2 Garantir Consultar lote/linha PGMEI alinhado (BulkActions + coluna)
  - Depende de: 1.2
- [x] 2.3 Wire em `simples-mei/index.vue` (handlers, permissões, fix onPublicServices)
  - Depende de: 2.1, 2.2

## 3. N2 — Testes

- [x] 3.1 Atualizar/adicionar testes unit de layout e fidelity
  - Depende de: 2.3
  - Evidência: `pnpm exec vitest run tests/unit/list-table-layout.test.ts tests/unit/monitoring-communication-informational.test.ts`
- [x] 3.2 Lint da área tocada
  - Depende de: 3.1
