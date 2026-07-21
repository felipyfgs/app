## 1. N0 — Contrato e célula

- [x] 1.1 Adicionar `MONITORING_CLIENT_COLUMN_META` (`w-full max-w-0` / `overflow-hidden`) em `monitoring-table-columns.ts` e documentar em `table-ui.ts`
- [x] 1.2 `FiscalClientCell` com ellipsis CSS (`w-full overflow-hidden` + `truncate`); sem corte por caracteres

## 2. N1 — Aplicar nas carteiras

- [x] 2.1 Trocar meta da coluna Cliente em `pgdasd-table`, `pgmei-table`, `dctfweb-table`, `sitfis-table`, `declarations-table`
  - Depende de: 1.1
- [x] 2.2 Trocar meta em `fgts.vue` e `installments.vue`; `horizontalScroll=false` no Portfolio Simples/MEI
  - Depende de: 1.1

## 3. N2 — Testes e gates

- [x] 3.1 Atualizar `list-table-layout.test.ts` para `w-full max-w-0` + Portfolio sem scroll horizontal
  - Depende de: 2.1
- [x] 3.2 Gates: `openspec validate` da change; vitest `list-table-layout`
  - Depende de: 3.1
