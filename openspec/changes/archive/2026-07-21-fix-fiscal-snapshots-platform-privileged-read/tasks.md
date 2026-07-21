## 1. N0 — Autorização do controller

- [x] 1.1 Ajustar `FiscalSnapshotController::assertCanRead` para usar só `TenantAuthorization::allows(FiscalMonitoringView[, target])`
- [x] 1.2 Feature test: platform-privileged sem membership dual → `GET /api/v1/fiscal/snapshots` 2xx
  - Depende de: 1.1
- [x] 1.3 Feature test: ator sem permissão → 403
  - Depende de: 1.1

## 2. N1 — Gates

- [x] 2.1 `vendor/bin/pint --test` nos arquivos tocados + `php artisan test` do feature novo
  - Depende de: 1.2, 1.3
- [x] 2.2 `openspec validate` da change (strict)
  - Depende de: 1.1
