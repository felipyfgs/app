## Context

`/monitoring/simples-mei` usa `buildPgdasdColumns` / `buildPgmeiColumns` com células de 2–3 ícones em Ações e Hist. comunicação. PGMEI já tem “Consultar dívida” em lote; PGDAS-D só menu “Ações” (regime/DEFIS), sem Consultar PGDASD destacado.

## Goals / Non-Goals

**Goals:**

- Reduzir ruído visual nas colunas de ícones (1 ícone de rastreio; ações informativas compactas).
- Botão Consultar na seleção (PGDAS-D e PGMEI) e atalho por linha na coluna Consulta.
- Confirmação explícita para lote; permissão `canTriggerSync`.

**Non-Goals:**

- Remover colunas canônicas do contrato monitoring (Ações / Hist. comunicação / Consulta).
- Novos endpoints SERPRO; mutações fiscais; automação de envio.

## Decisions

1. **PGDAS lote** → `useMonitoringActions('simples_mei').enqueueReadUpdate` (INTEGRA_SN/PGDASD/MONITOR) em loop silencioso + toast consolidado.
2. **PGMEI** → manter `requestConsult` existente; expor também na linha.
3. **Tracking** → um único botão de status/histórico local (sem trio status+download+search).
4. **Ações** → manter `communication-info` e `communication-preferences` (gate de fidelidade); PGMEI public services permanece.

## Risks / Trade-offs

- **[Download sumiu da célula]** → Mitigação: histórico/modal e detalhe do cliente.
- **[Bilhetagem SERPRO]** → Mitigação: confirm modal + permissão + sem auto-dispatch ao abrir página.

## Open Questions

- (nenhuma)
