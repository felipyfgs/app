## ADDED Requirements

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
