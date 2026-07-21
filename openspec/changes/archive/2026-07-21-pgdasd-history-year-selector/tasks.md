## 1. N0 — Seletor na view

- [x] 1.1 Em `PgdasdHistoryView.vue`, adicionar `yearFilter` / `yearOptions`, passar `{ year }` em `loadHistory`, e `watch` no filtro (padrão `PgdasdDasHistoryModal`).
- [x] 1.2 Renderizar `UFormField` + `USelect` “Ano da busca” no card de resumo com `data-testid="pgdasd-history-year"`; empty state pode mencionar o ano quando filtrado.

## 2. N1 — Evidência e gates

- [x] 2.1 Cobrir o comportamento em teste unitário (helper ou assert de presença/param) se já houver harness; senão, smoke via teste existente de pgdasd / fidelity mínima do testid.
  Depende de: 1.1, 1.2
- [x] 2.2 Rodar `pnpm exec vitest` focado na área + `openspec validate` da change.
  Depende de: 2.1
