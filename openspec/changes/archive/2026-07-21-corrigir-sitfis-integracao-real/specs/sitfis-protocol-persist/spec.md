## ADDED Requirements

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
