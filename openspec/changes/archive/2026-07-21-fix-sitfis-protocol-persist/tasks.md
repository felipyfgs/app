## 1. Persistência do protocolo

- [x] 1.1 Migration: ampliar `fiscal_monitoring_runs.progress_cursor` de 120 para 64
- [x] 1.2 `SitfisFlowService`: helper `cursorForProtocol` (`solicit` | `protocol:{sha256_16}`) e uso em processing/emit
  - Depende de: 1.1

## 2. Attempt store e replay

- [x] 2.1 Preservar `protocoloRelatorio`/`protocolo` no sanitizer para keys SITFIS (sem omit-as-blob; truncar ≤512)
- [x] 2.2 Reclaim quando replay sticky de solicit SITFIS tiver protocolo omitido
  - Depende de: 2.1

## 3. Refresh, force e schedule

- [x] 3.1 `SitfisSnapshotService::refresh`: TTL só para situações úteis; `force` bypassa; ERROR/BLOCKED/UNKNOWN enfileiram
- [x] 3.2 `SitfisSituationController`: passar `force` do request body
  - Depende de: 3.1
- [x] 3.3 Garantir `ensureSchedule` SITFIS no caminho de refresh enfileirado
  - Depende de: 3.1

## 4. UI enqueue honesto

- [x] 4.1 `useMonitoringActions` / PendingSearch / ModuleBulkActions: contar só `enqueued === true` e expor reason

## 5. Testes e gates

- [x] 5.1 Testes unit API: cursor curto, sanitizer SITFIS, refresh pós-ERROR, reclaim omitido
  - Depende de: 1.2, 2.2, 3.2
- [x] 5.2 Teste unit web: enqueue false não incrementa contador
  - Depende de: 4.1
- [x] 5.3 Gates: pint --test, php artisan test (filtros), lint/test web da área, openspec validate --strict
  - Depende de: 5.1, 5.2
