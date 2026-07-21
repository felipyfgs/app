## ADDED Requirements

### Requirement: Lista office-wide unifica guias emitidas e virtuais

`GET /api/v1/fiscal/guides` sem `client_id` SHALL retornar a união de `tax_guides`, DAS PGDAS (`pgdasd_operations` kind DAS) e DARF DCTFWeb (`dctfweb_darf_documents`), com dedupe por número, no mesmo shape público.

#### Scenario: Office sem tax_guides com DAS

- **WHEN** o office tem operações DAS e zero `tax_guides`
- **THEN** a lista office-wide retorna as linhas DAS com `source=PGDASD_CONSULT` e `total` > 0

#### Scenario: Filtro client_id preservado

- **WHEN** a request inclui `client_id`
- **THEN** a lista continua restrita àquele cliente (comportamento já existente)

### Requirement: Contadores de pagamento de guias na lista

A resposta da lista SHALL incluir contadores agregados de `payment_status` do universo unificado do office (ou cliente filtrado), independentes da página atual.

#### Scenario: Meta payment_counters

- **WHEN** a lista é carregada
- **THEN** o payload inclui totais por UNKNOWN / NOT_CONFIRMED / CONFIRMED / PARTIAL refletindo todas as guias unificadas no escopo (antes da paginação)

### Requirement: Central de Guias alinha KPI à lista

A página `/monitoring/guides` SHALL exibir Total e faixas a partir dos contadores de guias unificadas, MUST NOT usar `total_clients` da carteira genérica como Total da tabela de guias.

#### Scenario: Total reflete guias

- **WHEN** há 87 DAS e 0 tax_guides e 13 clientes na carteira
- **THEN** o Total do strip é 87 (ou o total de guias unificadas), não 13

#### Scenario: Linha virtual sem detalhe numérico

- **WHEN** a linha tem id virtual (`pgdasd-das-*` / `dctfweb-darf-*`)
- **THEN** a UI não chama `GET /guides/{id}` numérico; oferece link ao cliente e/ou download de documento quando disponível
