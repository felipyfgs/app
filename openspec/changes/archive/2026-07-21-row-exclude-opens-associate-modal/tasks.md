## 1. N0 — Wire linha → modal membership

- [x] 1.1 Em `Portfolio.vue`, fazer `onExclude` (PGDAS-D e PGMEI) abrir `membershipOpen = true`
- [x] 1.2 Remover `ShellConfirmModal` de exclusão e o estado/funções `excludeConfirmOpen`, `excludePendingIds`, `excludeBusy`, `requestExcludeFromMonitoring`, `confirmExcludeFromMonitoring`, `excludeFromMonitoring` se sem outros usos

## 2. N1 — Testes

- [x] 2.1 Atualizar `simples-mei-quick-consult.test.ts` para exigir abertura do modal de membership (não `requestExcludeFromMonitoring` / `exclude-confirm`)
  Depende de: 1.1, 1.2

## 3. N2 — Gates

- [x] 3.1 Rodar `pnpm run test` (filtro simples-mei-quick-consult) e `npx @fission-ai/openspec@1.6.0 validate --specs --strict` / validate da change
  Depende de: 2.1
