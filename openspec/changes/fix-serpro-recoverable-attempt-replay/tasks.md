## 1. N0 — Política e store

- [x] 1.1 Criar `SerproAttemptReplayPolicy` (lista de códigos não sticky)
- [x] 1.2 Em `SerproOperationAttemptStore::beginOrReplay`, reclaim de attempt terminal não sticky → `dispatch`
  - Depende de: 1.1

## 2. N1 — Evidência

- [x] 2.1 Teste unitário: `PROCURADOR_TOKEN_MISSING` reclaim; `REQUEST_FAILED` sticky; cross-tenant
  - Depende de: 1.2

## 3. N2 — Gates

- [x] 3.1 `vendor/bin/pint --test` + `php artisan test --filter=SerproOperationAttempt` + `openspec validate --specs --strict` (change ativa)
  - Depende de: 2.1
