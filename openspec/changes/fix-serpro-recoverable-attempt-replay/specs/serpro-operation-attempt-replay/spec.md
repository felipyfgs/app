## ADDED Requirements

### Requirement: Replay sticky apenas para resultados definitivos da operação
O attempt store do executor Integra Contador SHALL devolver `replay` de um attempt terminal somente quando o resultado persistido representar sucesso ou falha definitiva da operação (incluindo respostas remotas SERPRO e estados `uncertain`/`reconciled` com evidência de despacho). Falhas de pré-condição local recuperáveis MUST NOT bloquear um novo `dispatch` na mesma chave de idempotência.

#### Scenario: Token ausente não gruda após correção
- **WHEN** existe attempt terminal com `error_code` `PROCURADOR_TOKEN_MISSING` para a chave lógica
- **AND** uma nova execução com a mesma chave é iniciada após o token do procurador ter sido restabelecido
- **THEN** o store MUST retornar `action=dispatch` (reclaim) e MUST NOT devolver o corpo/erro do attempt anterior como replay

#### Scenario: Resposta SERPRO continua sticky
- **WHEN** existe attempt terminal com `error_code` `REQUEST_FAILED` (ou sucesso) para a chave lógica
- **AND** uma nova execução com a mesma chave é iniciada
- **THEN** o store MUST retornar `action=replay` com o resultado persistido

#### Scenario: Rate limit local é recuperável
- **WHEN** existe attempt terminal com `error_code` `RATE_LIMIT_LOCAL`
- **AND** uma nova execução com a mesma chave é iniciada
- **THEN** o store MUST permitir `dispatch` via reclaim em vez de replay sticky

### Requirement: Reclaim preserva isolamento de tenant e concorrência
O reclaim de attempt não sticky SHALL ocorrer apenas quando `office_id` da chave coincidir com o office da chamada. Attempts em voo (`dispatched`) MUST continuar bloqueando concorrentes com `wait`/`ATTEMPT_IN_FLIGHT`.

#### Scenario: Cross-tenant permanece bloqueado
- **WHEN** a chave de idempotência pertence a outro `office_id`
- **THEN** o store MUST retornar bloqueio `IDEMPOTENCY_CROSS_TENANT` e MUST NOT reclaim
