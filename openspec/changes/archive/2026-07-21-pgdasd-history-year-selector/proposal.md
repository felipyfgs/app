## Why

A tela `/monitoring/clients/:id/pgdasd` (Histórico PGDAS-D) lista todos os períodos de uma vez. O portal oficial e o modal “Histórico DAS” já usam seletor de **ano-calendário**; sem isso a página do cliente fica menos familiar e mais densa.

## What Changes

- Incluir seletor de ano-calendário no card de Histórico PGDAS-D (`PgdasdHistoryView`), no espírito do portal / modal DAS (`Ano da busca`).
- Ao escolher um ano, recarregar o histórico local via `fetchHistory(clientId, { year })` (API já filtra `period_key` por ano).
- Opção **Todos** + anos derivados dos períodos (e ano corrente), com `data-testid` estável.
- Preservar resumo (situação / PA esperado / última consulta), coleta de documentos e downloads.

## Capabilities

### New Capabilities

- `pgdasd-client-history-year-filter`: seletor de ano-calendário no histórico local PGDAS-D do detalhe do cliente.

### Modified Capabilities

- (nenhuma — main specs vazias; change `pgdasd-history-period-layout` ainda ativa e fora do contrato main)

## Impact

- Web: `apps/web/app/components/monitoring/PgdasdHistoryView.vue` (+ testes se houver cobertura da view).
- API: sem mudança — `GET .../pgdasd/clients/{id}/history?year=` já existe.
- Non-goals: redesign por PA (`pgdasd-history-period-layout`); não disparar SERPRO ao trocar ano; sem flags ON; sem mei no Compose.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: endpoint de history com `year` (já em produção no código)
- Depende de: nenhuma
- Coordenada com: `pgdasd-history-period-layout` (mesmo arquivo `PgdasdHistoryView.vue`, capability de layout) — relação `coordenada`; ao aplicar o layout por PA, preservar o seletor de ano
- Capability/contrato: `pgdasd-client-history-year-filter`
- Marco exigido: n/a
- Desbloqueia: UX de filtro por ano na página do cliente
- Paralelismo: ok com changes que não editem `PgdasdHistoryView.vue`; com `pgdasd-history-period-layout`, merge cuidadoso no mesmo arquivo
