## 1. N0 — Copy do chip e filtros

- [x] 1.1 Trocar `chipLabel` de ausência em `apps/web/app/utils/clients-credential.ts` de `Sem A1` para `Sem certificado`; ajustar comentários da função para "certificado" (não "A1").
- [x] 1.2 Atualizar títulos/aria dos KPIs em `ClientCatalogList.vue` (`Com A1` / `Sem A1` / `A1 vencido` → linguagem certificado).
- [x] 1.3 Alinhar badges de filiais em `ClientBranchesPanel.vue` (`A1` / `Sem A1` → `Com certificado` / `Sem certificado`).

## 2. N1 — Testes e gates

- [x] 2.1 Atualizar expectativa em `apps/web/tests/unit/clients-table.test.ts` para `Sem certificado`.
  Depende de: 1.1
- [x] 2.2 Rodar gate web focado: `pnpm run test -- clients-table` (ou equivalente) e `npx @fission-ai/openspec@1.6.0 validate --specs --strict` / validate da change.
  Depende de: 1.1, 1.2, 1.3, 2.1
