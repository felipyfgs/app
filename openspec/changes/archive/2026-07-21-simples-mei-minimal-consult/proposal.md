## Why

A carteira `/monitoring/simples-mei` está visualmente carregada (várias colunas só de ícones de comunicação/histórico) e não oferece um atalho claro para enfileirar consulta PGDAS-D/PGMEI nos selecionados ou na linha — o usuário precisa caçar ações no menu denso.

## What Changes

- Enxugar colunas de ícones PGDAS-D/PGMEI: manter contrato Ações · Hist. comunicação · Consulta, com menos ícones redundantes por célula.
- Botão rápido **Consultar** na toolbar ao selecionar (lote) e atalho na coluna Consulta (linha).
- Reusar enfileiramento já existente (`enqueueReadUpdate` / consult PGMEI); confirmação explícita; fail-closed de permissão.

## Capabilities

### New Capabilities

- `simples-mei-portfolio-ux`: densidade minimalista da carteira Simples MEI e consulta rápida seleção/linha.

### Modified Capabilities

- (nenhuma)

## Impact

- Web: `pgdasd-table.ts`, `pgmei-table.ts`, `SelectionActions.vue`, `simples-mei/index.vue`, testes de layout/fidelity.
- Sem novas rotas API; sem sublimite/SPED; SERPRO só via enqueue já existente.

### Dependências entre changes

- Nível: `C0`
- Depende de: nenhuma
- Desbloqueia: apply desta change
- Paralelismo: coordenada com `monitoring-insights-dashboard` e `declarations-obligation-tabs` (ownership distinto)
