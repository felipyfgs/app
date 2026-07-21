## 1. N0 — Resolução do token

- [x] 1.1 Refatorar `loadProcuradorToken` → resolução com códigos distintos + `withoutGlobalScopes` + autor normalizado
- [x] 1.2 Expandir `SerproAttemptReplayPolicy` com os novos códigos
  - Depende de: 1.1

## 2. N1 — Attempt store + refresh

- [x] 2.1 `abandonLocalPrecondition` + `purgeNonStickyTokenFailures`
- [x] 2.2 `SerproOperationService` não ACK falhas não sticky
  - Depende de: 2.1, 1.2
- [x] 2.3 Chamar purge após refresh bem-sucedido do token
  - Depende de: 2.1

## 3. N2 — Evidência e ops

- [x] 3.1 Testes unitários resolução + abandon/purge
  - Depende de: 2.2, 2.3
- [x] 3.2 Limpar rate limit / attempts do office 2 e consultar cliente 3 (Coelho Psicológicos)
  - Depende de: 3.1
- [x] 3.3 `pint` + `openspec validate` da change
  - Depende de: 3.1
