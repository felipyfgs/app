## MODIFIED Requirements

### Requirement: Lista office-wide unifica guias emitidas e virtuais

`GET /api/v1/fiscal/guides` sem `client_id` SHALL retornar a união de `tax_guides`, DAS PGDAS (`pgdasd_operations` kind DAS), DARF DCTFWeb (`dctfweb_darf_documents`) e guias consultadas/emitidas no FGTS Digital (`source=FGTS_DIGITAL_PORTAL`), com dedupe por número ou identidade documental, no mesmo shape público.

#### Scenario: Office sem tax_guides com DAS

- **WHEN** o office tem operações DAS e zero `tax_guides`
- **THEN** a lista office-wide retorna as linhas DAS com `source=PGDASD_CONSULT` e `total` > 0

#### Scenario: Filtro client_id preservado

- **WHEN** a request inclui `client_id`
- **THEN** a lista continua restrita àquele cliente (comportamento já existente)

#### Scenario: Guia FGTS Digital aparece uma única vez

- **WHEN** uma guia FGTS Digital foi consultada e também emitida pelo hub com o mesmo número oficial
- **THEN** a lista retorna uma única linha `FGTS_DIGITAL_PORTAL` com descriptor do documento autenticado quando disponível

### Requirement: Contadores de pagamento de guias na lista

A resposta da lista SHALL incluir contadores agregados de `payment_status` do universo unificado do office (ou cliente filtrado), incluindo os estados consultados no FGTS Digital, independentes da página atual.

#### Scenario: Meta payment_counters

- **WHEN** a lista é carregada
- **THEN** o payload inclui totais por UNKNOWN / NOT_CONFIRMED / CONFIRMED / PARTIAL refletindo todas as guias unificadas no escopo (antes da paginação)

#### Scenario: Status FGTS sem evidência não infla confirmados

- **WHEN** uma guia do FGTS Digital não possui comprovação atual de pagamento
- **THEN** ela participa do contador `UNKNOWN` ou `NOT_CONFIRMED`, nunca de `CONFIRMED`
