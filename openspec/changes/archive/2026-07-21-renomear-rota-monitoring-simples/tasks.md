## 1. N0 — Rotas e nav

- [x] 1.1 Criar `pages/monitoring/simples/index.vue` (+ legacy `[submodule]` se útil) e redirect em `simples-mei/*`
- [x] 1.2 Atualizar `FISCAL_MODULE_PATHS`, `monitoring-nav.ts`, `monitoring-post-create.ts`, middleware e home

## 2. N1 — Testes

- [x] 2.1 Atualizar unit/e2e que hardcodam `/monitoring/simples-mei`
  Depende de: 1.2
- [x] 2.2 Rodar vitest navigation/monitoring afetados + validate OpenSpec
  Depende de: 2.1
