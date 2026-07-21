## 1. N0 — Menu só PGDAS

- [x] 1.1 Reescrever `buildPgdasdSelectionMenu` / tipos em `pgdasd-action-items.ts`: apenas ações PGDAS básicas + abrir cliente + limpar; remover Regime, DEFIS e `onBatchConsult`
- [x] 1.2 Simplificar `SelectionActions.vue`: botão **Ações**; remover confirmações e loops de batch regime/DEFIS
- [x] 1.3 Em `simples-mei/index.vue`, remover handlers/modais/confirmações de Regime e DEFIS que só serviam esse menu

## 2. N1 — Evidência

- [x] 2.1 Teste unitário: menu sem Regime/DEFIS/códigos SERPRO; contém ações PGDAS básicas
  Depende de: 1.1, 1.2, 1.3

## 3. N2 — Gates

- [x] 3.1 Gates web (`lint`, `typecheck`, `test`) + `openspec validate` da change
  Depende de: 2.1
