## 1. N0 — Contrato FE e helpers

- [x] 1.1 Atualizar `monitoring-table-columns.ts` (spine, meta envio, builder célula status+Send+Switch, sem history na grade)
- [x] 1.2 Helper `monitoringAssociateClientListFilters(module, submodule)` para o modal Associar
- [x] 1.3 Testes unitários dos helpers de coluna/filtro

## 2. N1 — Backend comunicação

- [x] 2.1 Preferências graváveis + `can_send`/`automatic_effective` reais + kill-switch fail-closed no core
- [x] 2.2 Endpoint send manual + job de dispatch (provider gated)
- [x] 2.3 Hook pós-consulta agendada para `automatic_requested`
- [x] 2.4 Wrappers/rotas/enrichment SITFIS, FGTS e MIT (`mit` submodule)
- [x] 2.5 Feature tests API (prefs, send, include SN/MEI, kill-switch)

## 3. N1 — Convergir grades existentes

- [x] 3.1 DCTFWeb: reordenar spine; remover Histórico; célula envio; histórico no ⋮
- [x] 3.2 PGDAS: Situação · Últ. Declaração · RBT12 · Cliente · Ações · Hist. comunicação · Consulta + Send/Switch
- [x] 3.3 PGMEI: spine default + Send/Switch; Histórico no ⋮
- [x] 3.4 Evoluir modais de comunicação (remover somente-leitura; Enviar na prévia)

## 4. N2 — Demais carteiras + Associar

- [x] 4.1 MIT, SITFIS, FGTS, hub Declarações na spine + coluna de envio
- [x] 4.2 Modal Associar clientes com filtro SN/MEI e copy de escopo
- [x] 4.3 Testes FE de layout/ordem/associate filters

## 5. N3 — Gates

- [x] 5.1 `openspec validate --specs --strict` + change
- [x] 5.2 Gates web (lint/typecheck/test relevantes) e API (pint + testes afetados)

## 6. N1 — Refino spine (Envio ≠ Hist. · Editar cliente)

- [x] 6.1 Separar `buildMonitoringEnvioColumn` + `buildMonitoringTrackingColumn` no contrato FE
- [x] 6.2 Reordenar DCTFWeb (Situação · Últ. Declaração · Cliente); limpar Ações só ⋮ em PGDAS/PGMEI/DCTFWeb/MIT/SITFIS/FGTS/Declarações
- [x] 6.3 Item Editar cliente no ⋮ via `useMonitoringClientEdit` + `ClientsClientFormModal`
- [x] 6.4 Atualizar OpenSpec (proposal/design/spec) e testes de ordem/colunas
- [x] 6.5 Gates web (lint/typecheck/test relevantes) + `openspec validate`
