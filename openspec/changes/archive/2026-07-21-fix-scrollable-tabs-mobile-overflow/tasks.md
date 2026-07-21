## 1. N0 — Tokens e hosts de overflow

- [x] 1.1 Atualizar `TOUCH_SCROLL_X` em `apps/web/app/utils/list-filter-layout.ts` com `w-full max-w-full` (manter `min-w-0` + `overflow-x-auto`)
- [x] 1.2 Reforçar `w-full min-w-0 max-w-full` em `MonitoringKpiStrip` e no bloco KPI de `ModuleTable.vue`

## 2. N1 — Regressão e gates

- [x] 2.1 Estender teste unitário (navigation / list-filter-layout) para exigir bound de largura em `TOUCH_SCROLL_X` e host do KPI strip
  - Depende de: 1.1, 1.2
- [x] 2.2 Rodar gates web da área: `pnpm run test -- tests/unit/navigation.test.ts` (e teste novo se separado) + `npx @fission-ai/openspec@1.6.0 validate --specs --strict` na change
  - Depende de: 2.1
  - Evidência: `pnpm exec vitest run tests/unit/navigation.test.ts` → 15 passed; `openspec validate fix-scrollable-tabs-mobile-overflow --strict` → valid
