## 1. N0 — Modelo de linhas

- [x] 1.1 Estender `PgdasdArtifactDescriptor` com `period_key`, `declaration_number` e `das_number` em `fiscal-modules.ts`
- [x] 1.2 Criar helper `buildPgdasdHistoryOperationRows` (labels, associação de artefatos, “outros documentos”) + testes unitários

## 2. N1 — Grade oficial

- [x] 2.1 Extrair componente `PgdasdHistoryPeriodGrid` (desktop table + mobile cards + downloads autenticados)
  Depende de: 1.1, 1.2
- [x] 2.2 Refatorar `PgdasdHistoryView.vue` para usar o grid por PA (faixa PA, empty, outros documentos); preservar resumo/ano/coleta
  Depende de: 2.1
- [x] 2.3 Remover `PgdasdDeclarationsHistoryModal.vue` e seu acionamento no histórico DAS; manter a grade somente na página
  Depende de: 2.1

## 3. N2 — Evidência e gates

- [x] 3.1 Atualizar `company-first-monitoring.test.ts` para o novo contrato da superfície
  Depende de: 2.2
- [x] 3.2 Rodar gates web (`pnpm lint`, `typecheck`, `test` / fidelity se tocado) e `openspec validate --change alinhar-historico-pgdasd-portal-simples --strict`
  Depende de: 3.1, 2.3
  - Evidência verde da change: ESLint direcionado; 18 testes direcionados; suite completa com 208 testes; `pnpm run generate`; validação OpenSpec estrita.
  - Bloqueios globais fora da change: lint em arquivos MEI/monitoramento não tocados aqui; 5 erros preexistentes de typecheck; fidelity sem `tests/fixtures/template-parity-matrix.md`; artifacts sem `tests/security/scan-artifacts.mjs`.
