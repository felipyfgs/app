## Why

Consultas e sync de procurações continuam barrando com «Token do procurador ausente ou expirado» mesmo com autorização `TOKEN_ACTIVE` e vault legível. A mensagem é genérica (várias causas → mesmo texto), falhas locais ainda podem ser ACK’d, e attempts antigos/rate limit perpetuam o bloqueio após o token já ter sido renovado.

## What Changes

- Diferenciar códigos de falha no load do token (expirado vs ausente vs mismatch vs vault).
- Normalizar comparação de `author_identity` e carregar auth **sem** depender só do global scope.
- Falhas locais não sticky: **abandonar** o attempt (não ACK sticky).
- Após `refreshProcuradorToken` com sucesso: **purgar** attempts não sticky do office/ambiente.
- Expandir lista não sticky com os novos códigos.

## Capabilities

### New Capabilities

- `serpro-procurador-token-resolution`: resolução e diagnóstico do token do procurador no egress Integra.

### Modified Capabilities

- `serpro-operation-attempt-replay`: abandonar (não ACK) falhas locais; purge após refresh de token.

## Impact

- `HttpIntegraContadorClient`, `SerproOperationService`, `SerproOperationAttemptStore`, `SerproAttemptReplayPolicy`, `OfficeSerproAuthorizationService`
- Testes unitários da resolução + store

### Non-goals

- Não mudar bilhetagem Apoiar nem estratégia `SERPRO_TERM_REPRESENTATION_*`.
- Não UI admin nesta change.
- Não mei no Compose / flags ON.

### Dependências entre changes

- Nível: `C1`
- Depende de: `fix-serpro-recoverable-attempt-replay` (marco `apply`, relação `bloqueante` — reclaim já existe)
- Desbloqueia: sync/consulta de clientes sem limpeza manual de attempts
