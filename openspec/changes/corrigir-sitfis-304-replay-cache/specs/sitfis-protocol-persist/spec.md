## MODIFIED Requirements

### Requirement: Attempt store preserva protocolo SITFIS
Para as operation keys `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio`, o attempt store SHALL preservar os campos `protocoloRelatorio` e `protocolo` (e aliases oficiais usados na extração) no ACK. Esses campos MUST NOT ser substituídos pelo descritor `omitted_from_attempt_store` apenas por parecerem blob base64. Valores MAY ser truncados a um limite seguro (ex.: 512) para armazenamento. Quando `sitfis.solicitar_protocolo` responder HTTP 304 com `protocoloRelatorio` somente no `ETag`, o store SHALL canonicalizar o protocolo em `dados.protocoloRelatorio`, e o replay idempotente SHALL devolver uma resposta da qual o fluxo consiga recuperar o mesmo protocolo.

#### Scenario: ACK de solicitação mantém protocolo
- **WHEN** uma solicitação SITFIS bem-sucedida é acknowledged
- **THEN** `serpro_operation_attempts.dados` (ou body equivalente) MUST conter o `protocoloRelatorio` como string escalar utilizável (possivelmente truncada), não o descritor de omissão

#### Scenario: ACK 304 canonicaliza protocolo do ETag
- **WHEN** `/Apoiar` responde HTTP 304 com body vazio e `ETag` contendo `protocoloRelatorio`
- **THEN** o attempt ACK MUST preservar o protocolo em `dados.protocoloRelatorio`
- **AND** um replay sticky MUST permitir que `SitfisFlowService` avance para espera e `/Emitir` sem novo `/Apoiar`

## ADDED Requirements

### Requirement: 304 SITFIS sem protocolo é transitório
Quando `/Apoiar` responder HTTP 304 sem `protocoloRelatorio` parseável, o monitoramento SITFIS SHALL tratar a resposta como cache transitório, MUST NOT concluir a run como `ERROR` por `SITFIS_NOT_MODIFIED_EMPTY` e SHALL reprogramar uma nova solicitação para depois de `expires` quando o header for válido, ou após fallback limitado quando não for.

#### Scenario: 304 sem ETag aguarda expiração
- **WHEN** `/Apoiar` responde HTTP 304 sem protocolo e com header `expires` válido
- **THEN** a run MUST permanecer `PROCESSING`
- **AND** MUST ser requeued para depois da expiração do cache
- **AND** MUST NOT criar snapshot atual `ERROR`

#### Scenario: Continuação após cache expirado solicita novo protocolo
- **WHEN** uma run em espera de cache é retomada após `not_before` sem protocolo persistido
- **THEN** o fluxo MUST executar novamente `sitfis.solicitar_protocolo`
- **AND** SHALL avançar normalmente se receber protocolo em body ou ETag

#### Scenario: 304 sem expires usa fallback seguro
- **WHEN** `/Apoiar` responde HTTP 304 sem protocolo e sem `expires` parseável
- **THEN** a run MUST permanecer `PROCESSING`
- **AND** MUST usar espera fallback limitada, sem force-retry imediato
