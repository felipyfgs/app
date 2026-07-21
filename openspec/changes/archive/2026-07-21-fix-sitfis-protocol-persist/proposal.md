## Why

A carteira SITFIS não consulta de fato: o protocolo oficial da SERPRO é longo demais para `fiscal_monitoring_runs.progress_cursor` (`varchar(120)`), a run cai em `JOB_UNHANDLED_EXCEPTION`, o attempt store omite `protocoloRelatorio` e o TTL de snapshot `ERROR` impede novo enqueue — deixando 10 clientes em Desconhecido e 3 em Erro.

## What Changes

- Persistir protocolo completo só em `progress.protocol` e usar `progress_cursor` curto (`protocol:{sha256_16}`).
- Ampliar coluna `progress_cursor` para 64 caracteres.
- Preservar `protocoloRelatorio`/`protocolo` no attempt store para operações SITFIS (não omitir como blob).
- Reclaim/redispatch quando replay sticky de solicit vier com protocolo omitido.
- Refresh SITFIS: snapshots `ERROR`/`BLOCKED`/`UNKNOWN` não bloqueiam por TTL; `force=true` bypassa TTL.
- Garantir schedule SITFIS no caminho de refresh (`ensureSchedule`).
- UI: contar/enfileirar só quando `enqueued === true` e expor `reason` real.

## Capabilities

### New Capabilities

- `sitfis-protocol-persist`: contrato do fluxo assíncrono SITFIS — cursor curto, preservação do protocolo no attempt store, refresh pós-erro e feedback de enqueue.

### Modified Capabilities

- (nenhuma — main specs vazias para este contrato; nasce nesta change)

## Impact

- API: `SitfisFlowService`, `SerproOperationAttemptStore`, `SerproAttemptReplayPolicy`, `SitfisSnapshotService`, `SitfisSituationController`, migration `progress_cursor`, `FiscalCategoryService::ensureSchedule` no refresh
- Web: `useMonitoringActions`, `PendingSearchButton`, `ModuleBulkActions`
- Testes unit/feature API + unit web da área
- Sem mudança de contrato SERPRO; sem abrir kill switches; sem `mei` no Compose

### Non-goals

- Alterar bilhetagem Integra ou chave lógica de idempotência além do reclaim de protocolo omitido.
- Reescrever o parser do relatório SITFIS ou o layout da carteira.
- Scheduler global / ops backup-restore.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `fix-serpro-recoverable-attempt-replay` (já no comportamento do store)
- Depende de: nenhuma change ativa
- Desbloqueia: consultas SITFIS manuais e em lote que hoje morrem no persist ou no TTL de erro
- Paralelismo: ownership = SITFIS + attempt store; não conflita com PGDASD/MEI UI
