## 1. N0 — Tema e seletor alinhados

- [x] 1.1 Restaurar `app.config.ts` e `main.css` ao tema verde/zinc do arquétipo e cobrir o default/tokens no teste unitário de contrato (`pnpm exec vitest run tests/unit/dashboard-theme-selector.test.ts`).
- [x] 1.2 Portar para `UserMenu.vue` os seletores de cor primária e neutra do dashboard de referência, preservando aparência/conta/PWA/logout, e cobrir opções, chips e atualização de `useAppConfig()` no mesmo teste unitário (`pnpm exec vitest run tests/unit/dashboard-theme-selector.test.ts`).

## 2. N1 — Gates integrados e prontidão

- [x] 2.1 Executar `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts` e `npx @fission-ai/openspec@1.6.0 validate align-theme-with-dashboard-reference --strict`.
  Depende de: 1.1, 1.2
  Evidência parcial em 2026-07-21: `typecheck`, `generate` isolado, `test` (264 testes) e validação OpenSpec passaram. `lint` está bloqueado por erro de carregamento da configuração ESLint; `test:fidelity` e `test:artifacts` estão bloqueados por arquivos de gate ausentes no worktree.
