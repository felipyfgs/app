## Context

`MonitoringPgdasdHistoryView` carrega o histórico sem parâmetro de ano. A API (`PgdasdMonitoringController::history` + `PgdasdMonitoringQueryService::history`) já filtra por `year` em `period_key like YYYY-%`. O modal `PgdasdDasHistoryModal` já implementa `USelect` “Ano da busca” com `Todos` + anos e `watch` para recarregar.

## Goals / Non-Goals

**Goals:**

- Expor o mesmo padrão de seletor na página de histórico do cliente.
- Filtrar no servidor (`?year=`) ao selecionar um ano; `Todos` = sem query param.
- Default no **ano-calendário corrente** (mais próximo do portal); opção **Todos** para o histórico local completo.

**Non-Goals:**

- Layout agrupado por PA (outra change).
- Consulta SERPRO ao mudar ano.
- Persistência do ano na URL (pode ser follow-up).

## Decisions

1. **Reusar padrão do modal DAS** (`yearFilter: number | 'all'`, label “Ano da busca”) com helper `pgdasdHistoryCalendarYears` para opções estáveis entre trocas de filtro.
2. **Filtro server-side** via `fetchHistory(..., { year })`, não só client-side no array já carregado — respeita o contrato da API e reduz payload.
3. **Default = ano corrente** (portal-like); **Todos** permanece disponível.
4. **Posição do seletor**: no cabeçalho do `UPageCard` (ao lado do resumo), `data-testid="pgdasd-history-year"`.
5. **Coordenação com `pgdasd-history-period-layout`**: esta change só adiciona o seletor e o param de fetch; o layout futuro deve manter o controle.

## Risks / Trade-offs

- [Conflito de merge com layout por PA] → Mitigação: change coordenada; seletor isolado no topo do card.
- [yearOptions só após primeiro load “Todos”] → Aceitável (igual ao modal); anos sem dados locais podem ser escolhidos via digitar? Não — USelect só com opções conhecidas + corrente.
- [Empty state por ano sem registros] → Manter empty existente da view; copy pode mencionar o ano filtrado se trivial.

## Migration Plan

Só frontend. Rollback = reverter PR.
