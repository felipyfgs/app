## Purpose

Capability `client-detail-dctfweb-hub-wiring` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Declarações do cliente refletem consulta DCTFWeb local

Quando o hub lista projeções `DCTFWEB` (ou declarações locais do cliente), a resposta pública SHALL enriquecer com recibo/estado de `dctfweb_declarations` e documento de evidência quando existir — sem chamar SERPRO.

#### Scenario: Recibo consultado enriquece a linha

- **WHEN** existe `dctfweb_declarations` para o cliente/período com `receipt_number` ou estado conclusivo
- **THEN** o item em `GET /api/v1/fiscal/declarations?client_id=` expõe `declaration_number` (alias do recibo) e `delivery_status`/`situation` efetivos coerentes (`UP_TO_DATE` para CURRENT/NO_MOVEMENT_VALID)

#### Scenario: Declaração local sem projeção de hub

- **WHEN** o filtro inclui `client_id` e há declaração DCTFWeb local sem projeção correspondente na página
- **THEN** a lista inclui uma linha sintética `source=DCTFWEB_CONSULT` para esse período

#### Scenario: Sem inventar PDF

- **WHEN** não há evidência RECIBO versionada
- **THEN** `document` fica ausente/unavailable

### Requirement: Guias do cliente listam DARF emitido DCTFWeb

Com `client_id`, `GET /api/v1/fiscal/guides` SHALL incluir documentos de `dctfweb_darf_documents` além de `tax_guides` e DAS PGDAS, com dedupe por número.

#### Scenario: Cliente com DARF e sem tax_guide

- **WHEN** existe `dctfweb_darf_documents` para o cliente
- **THEN** a lista de guias inclui a linha com `identifier_code` do DARF e `source=DCTFWEB_DARF`

#### Scenario: Consulta só de recibo não inventa guia

- **WHEN** só há CONSRECIBO sem `EMITIR_DARF`
- **THEN** a lista de guias NÃO inventa DARF a partir do recibo

### Requirement: UI identifica recibo e DARF

O detalhe do cliente SHALL exibir rótulos de recibo DCTFWeb e DARF quando a API enviar esses identificadores/sources.

#### Scenario: Coluna guia com DARF

- **WHEN** a linha tem `source=DCTFWEB_DARF` ou `identifier_code` de DARF
- **THEN** a UI mostra `DARF {numero} · {PA}` (não “DAS”)
