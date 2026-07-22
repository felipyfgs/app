## 1. N0 — Replay 304 preserva protocolo

- [x] 1.1 Canonicalizar `protocoloRelatorio` do `IntegraResponse::etag` em `serpro_operation_attempts.dados` no ACK de `sitfis.solicitar_protocolo`
- [x] 1.2 Restaurar campos dedicados `etag`/`expiresHeader` em `SerproOperationAttemptStore::toResponse` quando existirem em armazenamento sanitizado
- [x] 1.3 Testar ACK e replay de SITFIS 304 com protocolo somente no ETag

## 2. N0 — Cache 304 sem protocolo não vira erro

- [x] 2.1 Adicionar fase de espera de expiração de cache ao estado SITFIS e retomada em `solicit` quando não houver protocolo
- [x] 2.2 Substituir force-retry imediato/`SITFIS_NOT_MODIFIED_EMPTY` por resultado `Partial/Processing` com requeue após `expires` ou fallback limitado
- [x] 2.3 Testar 304 sem ETag com `expires`, sem `expires` e continuação após `not_before`

## 3. N1 — Gates integrados

- [x] 3.1 Rodar testes focados de `SitfisFlowService` e `SerproOperationAttemptStoreReplayTest`
  - Depende de: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3
- [x] 3.2 Rodar `vendor/bin/pint --test` nos arquivos alterados e `openspec validate --change corrigir-sitfis-304-replay-cache --strict`
  - Depende de: 3.1
