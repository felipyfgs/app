## 1. N0 — Helper e meta da coluna Ações

- [x] 1.1 Atualizar `buildMonitoringActionsMenuCell` para botão com `label: 'Ações'` (trailing icon), sem `square`; ajustar `MONITORING_ACTIONS_META` e comentários da spine em `monitoring-table-columns.ts`
- [x] 1.2 Reordenar coluna `actions` para o fim nos builders: `pgdasd-table`, `pgmei-table`, `dctfweb-table`, `sitfis-table`, `declarations-table`, `fgts.vue` (após Comunicação e Consulta)

## 2. N1 — Testes e gates

- [x] 2.1 Atualizar testes de contrato/ordem (`monitoring-portfolio-columns.test.ts` e correlatos) para `Comunicação · Consulta · Ações` e botão rotulado
  Depende de: 1.1, 1.2
- [x] 2.2 Rodar `pnpm run test -- monitoring-portfolio-columns` (ou filtro equivalente) e `npx @fission-ai/openspec@1.6.0 validate --specs --strict` / validate da change
  Depende de: 2.1
