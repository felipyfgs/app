## Purpose

Capability `sitfis-protocol-persist` — requisitos sincronizados das changes OpenSpec.
## Requirements
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
Para as operation keys `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio`, o attempt store SHALL preservar os campos `protocoloRelatorio` e `protocolo` (e aliases oficiais usados na extração) no ACK. Esses campos MUST NOT ser substituídos pelo descritor `omitted_from_attempt_store` apenas por parecerem blob base64. Valores MAY ser truncados a um limite seguro (ex.: 512) para armazenamento. Quando `sitfis.solicitar_protocolo` responder HTTP 304 com `protocoloRelatorio` somente no `ETag`, o store SHALL canonicalizar o protocolo em `dados.protocoloRelatorio`, e o replay idempotente SHALL devolver uma resposta da qual o fluxo consiga recuperar o mesmo protocolo.

#### Scenario: ACK de solicitação mantém protocolo
- **WHEN** uma solicitação SITFIS bem-sucedida é acknowledged
- **THEN** `serpro_operation_attempts.dados` (ou body equivalente) MUST conter o `protocoloRelatorio` como string escalar utilizável (possivelmente truncada), não o descritor de omissão

#### Scenario: ACK 304 canonicaliza protocolo do ETag
- **WHEN** `/Apoiar` responde HTTP 304 com body vazio e `ETag` contendo `protocoloRelatorio`
- **THEN** o attempt ACK MUST preservar o protocolo em `dados.protocoloRelatorio`
- **AND** um replay sticky MUST permitir que `SitfisFlowService` avance para espera e `/Emitir` sem novo `/Apoiar`

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

### Requirement: Evidência no publicView SITFIS
`GET /api/v1/fiscal/sitfis` (`SitfisSnapshotService::publicView`) SHALL incluir `evidence_artifact_id` do snapshot ativo quando existir, e SHALL incluir `links.evidence_download` com o path autenticado `/api/v1/fiscal/evidence/{id}/download`. Quando não houver artefato, esses campos MUST ser null ou omitidos. O endpoint MUST NOT embutir bytes do PDF no JSON.

#### Scenario: Snapshot com artefato expõe link
- **WHEN** o snapshot atual do cliente tem `evidence_artifact_id` preenchido
- **THEN** a resposta `data` MUST conter o mesmo id em `evidence_artifact_id`
- **AND** `links.evidence_download` MUST ser `/api/v1/fiscal/evidence/{id}/download`

#### Scenario: Snapshot sem artefato
- **WHEN** o snapshot atual não tem evidência (ou não há snapshot)
- **THEN** `evidence_artifact_id` MUST ser null
- **AND** `links.evidence_download` MUST ser null ou ausente

### Requirement: HTTP 304 no solicit SITFIS usa protocolo do ETag

Quando `sitfis.solicitar_protocolo` (`SOLICITARPROTOCOLO91` / `/Apoiar`) retornar HTTP 304 (Not Modified) com body vazio, o sistema SHALL tratar a resposta como sucesso de cache oficial SERPRO e SHALL extrair `protocoloRelatorio` do header `ETag` no formato `protocoloRelatorio:{token}` (aspas opcionais). O fluxo MUST avançar para a fase de espera mínima / emissão com esse protocolo. O sistema MUST NOT falhar com `SITFIS_NOT_MODIFIED_EMPTY` apenas porque o body está vazio quando o ETag contém protocolo parseável. Force-retry com novo idempotency MAY ocorrer somente se o 304 não trouxer ETag parseável.

#### Scenario: 304 com ETag de protocolo avança para espera

- **WHEN** a SERPRO responde HTTP 304 na solicitação SITFIS com `ETag` contendo `protocoloRelatorio:{token}`
- **THEN** a run MUST persistir o token em `progress.protocol` e avançar para espera/requeue de emissão
- **AND** a run MUST NOT terminar como Failed com `SITFIS_NOT_MODIFIED_EMPTY`

#### Scenario: 200 com protocolo no body permanece válido

- **WHEN** a SERPRO responde HTTP 200 com `protocoloRelatorio` em `dados`
- **THEN** o fluxo MUST continuar usando o protocolo do body (comportamento existente)

### Requirement: Snapshot sem evidência não promove is_current em falha

Ao persistir resultado `Failed`, `Blocked` ou `Skipped` de uma run fiscal **sem** `evidence_artifact_id` (sem bytes de evidência), o sistema SHALL criar o snapshot de auditoria com `is_current = false` e MUST NOT demover o snapshot corrente anterior do mesmo cliente/sistema/serviço. Somente resultados com evidência (ou sucesso/partial elegível com evidência, conforme regras existentes de provenance/verification) MAY tornar-se `is_current` nesse caminho.

#### Scenario: Failed SITFIS sem PDF mantém snapshot anterior

- **WHEN** uma run SITFIS termina Failed ou Blocked sem evidência PDF
- **AND** já existe um snapshot `is_current` com evidência
- **THEN** o novo snapshot MUST ter `is_current = false`
- **AND** o snapshot anterior MUST permanecer `is_current = true`

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
