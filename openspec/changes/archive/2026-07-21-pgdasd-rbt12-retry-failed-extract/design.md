## Context

O fluxo atual reserva RBT12 por `source_reference_key` (office|client|period|DAS|decl|tx). Em falha de `CONSULTAR_EXTRATO` (`RATE_LIMIT_LOCAL`), `reconcileTerminalFailure` marca `FAILED` / `EXTRACT_QUERY_FAILED`. No MONITOR seguinte, `reserve()` encontra a linha e retorna `null` — sem job. A carteira só exibe valor com status `PARSED`.

## Goals / Non-Goals

**Goals:**

- Reabrir falhas recuperáveis de extrato no pós-MONITOR e redisparar um único job de extrato por reserva reaberta.
- Evitar rajada: no caminho automático, só o DAS mais recente do PA esperado (projection do período esperado da run MONITOR).
- Permitir re-enqueue quando a run correlacionada de extrato já está `FAILED`.

**Non-Goals:**

- Alterar parser PDF / UI do chip.
- Retry de `NOT_FOUND`/`AMBIGUOUS`/`PARSED`.
- Mudança de contrato HTTP da carteira.
- Ligar rate-limit SERPRO ou aumentar quotas.

## Decisions

1. **Lista fechada de razões recuperáveis** (`EXTRACT_QUERY_FAILED`, `EXTRACT_JOB_DISPATCH_FAILED`, `EXTRACT_QUERY_ENQUEUE_FAILED`, `EXTRACT_JOB_FAILED`, `PDF_TEXT_EXTRACTION_FAILED`): ao achar `existing` com status `FAILED` e razão na lista, resetar para `PENDING` (limpar `sanitized_error`, `attempted_at`, `extracted_at`, totais) e retornar a linha para disparo do job. Demais status (`PARSED`, `PENDING`, `NO_DAS`, falhas de parser) → no-op (`null`).
   - Alternativa: apagar e recriar — rejeitada (perde id/ponteiro/`source_reference_key` útil).

2. **Fan-out no MONITOR**: em `reserveFromOperations`, para cada projection do PA esperado (ou, se ausente, a projection mais recente das operações), selecionar apenas o DAS com maior `issued_at`/`das_number`. Projections históricas sem necessidade da carteira não disparam extrato automático.
   - Alternativa: retry de todas as 19 — rejeitada (reproduz rate-limit).

3. **`enqueueAutomaticRbt12Extract`**: se a run reutilizada por correlação está `FAILED`, resetar para `QUEUED`, limpar `error_code`/`error_message`/`result` relevantes e despachar `ExecuteFiscalMonitoringRunJob`. Não retornar cedo só porque status ≠ `QUEUED`.

4. **`reconcileTerminalFailure` permanece** para falhas terminais da run; o retry acontece no próximo MONITOR (e, se desejável, pode ser acionado no mesmo ciclo se ainda PENDING). Não mudar classificação global de `RATE_LIMIT_LOCAL` no adapter nesta change.

## Risks / Trade-offs

- [Bilhetagem SERPRO] → Mitigação: 1 extrato/PA esperado por MONITOR; fail-closed de módulos intacto.
- [Loop infinito de retry] → Mitigação: só razões recuperáveis; sucesso vira `PARSED`/`NOT_FOUND`/`AMBIGUOUS` e para; falha nova só reabre no próximo MONITOR (não busy-loop).
- [Cliente com vários PA] → Mitigação: carteira usa PA esperado; histórico continua com ponteiros locais sem forçar N extratos.

## Migration Plan

- Deploy de código apenas; sem migration de schema.
- Clientes stuck (ex.: Brito): próximo MONITOR reabre a reserva do DAS mais recente do PA esperado.
- Rollback: reverter o serviço/job; reservas já `PARSED` permanecem.

## Open Questions

- Nenhuma bloqueante.
