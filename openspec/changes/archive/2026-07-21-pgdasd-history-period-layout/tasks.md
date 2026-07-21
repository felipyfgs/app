## 1. N0 — Layout por PA

- [x] 1.1 Substituir a tabela com `rowspan` e o template mobile divergente em `PgdasdHistoryView.vue` por lista de blocos por PA (ordem decrescente), com cabeçalho do período e ação de buscar documentos
- [x] 1.2 Dentro de cada PA, renderizar seção Declarações (operação, nº, transmissão, malha) e seção DAS (nº, emissão, pago), sem grade cruzada esparsa; empty explícito quando o PA não tiver registros
- [x] 1.3 Preservar resumo (situação / PA esperado / última consulta), modal de coleta com confirmação, downloads autenticados e empty/loading/error globais; atualizar `data-testid` (`pgdasd-history-periods` / por-PA) mantendo `pgdasd-history-view`

## 2. N1 — Evidência

- [x] 2.1 Ajustar ou adicionar teste unit/fidelity da superfície para o novo layout (agrupamento por PA + seções)
  Depende de: 1.1, 1.2, 1.3

## 3. N2 — Gates

- [x] 3.1 Rodar gates web da área (`pnpm run lint`, `pnpm run typecheck`, `pnpm run test` e fidelity se tocado) e `openspec validate --specs --strict` + validate da change
  Depende de: 2.1
