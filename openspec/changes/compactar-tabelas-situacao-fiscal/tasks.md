## 1. N0 — Shell e células compartilhadas

- [x] 1.1 Adensar `TABLE_UI` em `ModuleDataTable.vue` (th sem `py-2`; `th`/`td` com `px-2 sm:px-3`) e cobrir com assert de source/unitário do shell
- [x] 1.2 Compactar `CommercialMetaCell.vue` para no máximo duas linhas densas (saldo/snapshot/próxima; “recente” via tooltip se necessário) e cobrir render básico

## 2. N1 — Grades SITFIS e Simples/MEI

- [x] 2.1 Em `sitfis.vue`: larguras `meta.class` por coluna, Achados curtos, Ações sem botão texto “Cliente”, `table-class` mínimo recalibrado; assert de source/unitário das colunas
  Depende de: 1.1, 1.2
- [x] 2.2 Em `pgdasd-table.ts` / `pgmei-table.ts` + `simples-mei/index.vue`: Cliente primeiro após seleção, `min-w` menores, ações `xs`, `initialHiddenColumns` para `consulted`/`history`, `table-class` ~1100px; ajustar testes que leem source dos builders
  Depende de: 1.1

## 3. N2 — Gates integrados

- [x] 3.1 Rodar gate frontend tocado (`pnpm run test:gate` ou subset unitário + typecheck/lint das grades) e validar visualmente SITFIS + Simples/MEI em ~1280px sem corte da visão padrão; registrar evidência
  Depende de: 2.1, 2.2
  Evidência: Vitest focado 33/33 (pgdasd/pgmei/monitoring-mobile/office-settings); asserts de densidade no shell (`px-2 sm:px-3`, sem `th py-2`); SITFIS `min-w-[880px]` + Achados curtos; Simples `min-w-[1100px]` + `initialHiddenColumns` consulted/history + Cliente primeiro. Typecheck: erros pré-existentes em `Filters.vue` / `ListFilterToolbar.vue` (fora do escopo). Sem erros de lint nos arquivos tocados.

