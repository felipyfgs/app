## 1. N0 — Ensure 00002 e DI

- [x] 1.1 Injetar `EnsureClientProcuracaoForConsult` em `SitfisFlowService` e chamar `ensure(..., ['00002'], ...)` antes do primeiro `call`; em falha retornar `blocked`
- [x] 1.2 Atualizar binding/construção em `AppServiceProvider` e testes de construção do FlowService

## 2. N1 — Protocolo 304 via ETag

- [x] 2.1 Em `extractProtocolFromResponse` / `solicit`, extrair `protocoloRelatorio` de `$response->etag` quando body vazio; 304 com ETag avança para espera
  - Depende de: 1.1
- [x] 2.2 Remover force-retry como caminho padrão no 304; restringir `SITFIS_NOT_MODIFIED_EMPTY` ao edge sem ETag parseável
  - Depende de: 2.1

## 3. N1 — Snapshot is_current

- [x] 3.1 Em `FiscalSnapshotPersistence::createSnapshot`, Failed/Blocked/Skipped sem evidence → `is_current=false` (não demover corrente)
  - Depende de: 1.1

## 4. N2 — Testes e gates

- [x] 4.1 Testes: ensure SITFIS; 304+ETag → protocol; Failed sem evidence mantém current
  - Depende de: 2.2, 3.1
- [x] 4.2 Gates: `vendor/bin/pint --test`; `php artisan test` (Sitfis + Persistence); `openspec validate --change corrigir-sitfis-integracao-real --strict`
  - Depende de: 4.1
