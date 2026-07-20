## 1. N0 — Fixes

- [x] 1.1 Refatorar chamada síncrona do `SignTermoWithManagedA1Job` para `dispatchSync`
- [x] 1.2 Truncar `skip_reason` em `FiscalSnapshotPersistence`
  - Depende de: 1.1

## 2. N1 — Gates

- [x] 2.1 Teste unitário / regressão + `openspec validate` + pint
  - Depende de: 1.2
