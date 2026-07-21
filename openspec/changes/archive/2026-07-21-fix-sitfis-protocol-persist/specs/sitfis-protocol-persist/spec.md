## ADDED Requirements

### Requirement: Cursor curto e protocolo completo no progress
O fluxo SITFIS SHALL persistir o protocolo oficial completo em `fiscal_monitoring_runs.progress.protocol` e SHALL usar `progress_cursor` curto no formato `protocol:{sha256_16}` (ou `solicit` antes do protocolo). A coluna `progress_cursor` MUST comportar pelo menos 64 caracteres. O sistema MUST NOT gravar o token completo do protocolo em `progress_cursor`.

#### Scenario: Solicitação bem-sucedida persiste sem truncar
- **WHEN** a SERPRO responde HTTP 200 com `protocoloRelatorio` com mais de 120 caracteres
- **THEN** a run MUST avançar para fase de espera/requeue com `progress.protocol` igual ao token e `progress_cursor` começando com `protocol:` e comprimento ≤ 64
- **AND** a run MUST NOT falhar com erro de truncamento de coluna (`varchar`)

#### Scenario: Continuação usa progress.protocol
- **WHEN** uma run de continuação é enfileirada após a espera mínima
- **THEN** o fluxo MUST ler o protocolo de `progress.protocol` (não do cursor) para emitir o relatório

### Requirement: Attempt store preserva protocolo SITFIS
Para as operation keys `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio`, o attempt store SHALL preservar os campos `protocoloRelatorio` e `protocolo` (e aliases oficiais usados na extração) no ACK. Esses campos MUST NOT ser substituídos pelo descritor `omitted_from_attempt_store` apenas por parecerem blob base64. Valores MAY ser truncados a um limite seguro (ex.: 512) para armazenamento.

#### Scenario: ACK de solicitação mantém protocolo
- **WHEN** uma solicitação SITFIS bem-sucedida é acknowledged
- **THEN** `serpro_operation_attempts.dados` (ou body equivalente) MUST conter o `protocoloRelatorio` como string escalar utilizável (possivelmente truncada), não o descritor de omissão

### Requirement: Replay com protocolo omitido não é sticky definitivo
Quando um replay sticky de `sitfis.solicitar_protocolo` devolver sucesso cujo protocolo esteja omitido (`omitted_from_attempt_store` ou equivalente), o sistema SHALL reclaim o attempt e permitir novo `dispatch` HTTP em vez de falhar com `SITFIS_PROTOCOL_MISSING` por causa do replay sanitizado.

#### Scenario: Reclaim após protocolo omitido no replay
- **WHEN** `beginOrReplay` devolveria replay de sucesso de solicit SITFIS sem protocolo correlacionável por omissão no store
- **THEN** o store MUST reclaim e retornar `dispatch`
- **AND** uma nova chamada HTTP MAY obter protocolo fresco

### Requirement: Refresh SITFIS após erro e com force
`POST /api/v1/fiscal/sitfis/refresh` SHALL enfileirar nova run quando não houver snapshot atual, quando o snapshot atual for `ERROR`, `BLOCKED` ou `UNKNOWN`, ou quando `force` for true. TTL (`WITHIN_TTL`) MUST aplicar-se apenas a snapshots com situação útil (`UP_TO_DATE`, `PENDING`, `ATTENTION`, `PROCESSING`). O controller MUST passar o campo `force` do body para o serviço.

#### Scenario: Snapshot ERROR permite novo enqueue
- **WHEN** o cliente tem snapshot SITFIS atual `ERROR` ainda dentro do TTL configurado
- **AND** o operador solicita refresh sem force
- **THEN** a API MUST retornar `enqueued: true` (salvo `ALREADY_RUNNING` ou gate de módulo)

#### Scenario: Force bypassa TTL de snapshot saudável
- **WHEN** o snapshot atual é `UP_TO_DATE` dentro do TTL
- **AND** o body inclui `force: true`
- **THEN** a API MUST enfileirar nova run

### Requirement: Feedback honesto de enqueue na UI
Ações de “Buscar pendências” e bulk “Solicitar consulta” no módulo SITFIS SHALL contar como enfileiradas apenas respostas com `enqueued: true`. A UI MUST NOT apresentar sucesso de enqueue quando a API retornar `enqueued: false` com reason `WITHIN_TTL` ou `ALREADY_RUNNING`.

#### Scenario: TTL não conta como enfileirada
- **WHEN** o operador dispara Buscar pendências e a API responde `{ enqueued: false, reason: "WITHIN_TTL" }` para um cliente
- **THEN** o contador de enfileiradas MUST NÃO incrementar esse cliente
- **AND** o feedback MUST refletir que a atualização não foi enfileirada

### Requirement: Schedule SITFIS no refresh
Ao enfileirar um refresh SITFIS com sucesso, o sistema SHALL garantir (quando a categoria/serviço SITFIS existir para o cliente) um `fiscal_monitoring_schedules` habilitado via `ensureSchedule`, para que o scheduler possa cobrir clientes Desconhecido sem depender só do bulk manual.

#### Scenario: Refresh cria schedule ausente
- **WHEN** um refresh SITFIS é enfileirado para um cliente sem schedule SITFIS
- **AND** a categoria/serviço SITFIS está disponível no catálogo do escritório
- **THEN** um schedule SITFIS habilitado MUST existir após o refresh
