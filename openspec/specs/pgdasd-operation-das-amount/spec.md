## Purpose

Capability `pgdasd-operation-das-amount` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Persistência de amount_cents na operação DAS

O sistema SHALL persistir o montante do DAS unpaid em `pgdasd_operations` com pelo menos `amount_cents` (inteiro, centavos) e `amount_source` discriminando a origem (`GERAR_DAS` ou `EXTRATO_PARSE`). Campos de auditoria (`amount_parser_version`, `amount_resolved_at`, referência ao artefato) MAY acompanhar a gravação.

A gravação MUST ocorrer no ingest Integra Contador (pós-consulta), office-scoped, e MUST NOT ocorrer durante o GET/listagem do portfolio.

#### Scenario: Extrato grava Total da seção 6

- **WHEN** um CONSEXTRATO16 produtivo armazena PDF de extrato e o texto contém seção 6 com `Número` igual ao `das_number` da operação e `Total` monetário inequívoco
- **THEN** a operação DAS correspondente MUST receber `amount_cents` do Total e `amount_source=EXTRATO_PARSE`

#### Scenario: GERAR_DAS estruturado preferido

- **WHEN** um GERAR_DAS SUCCESS traz `numeroDocumento` e `total` (ou equivalente já mapeado no DTO) casáveis a uma operação DAS local
- **THEN** a operação MUST receber `amount_cents` desse total e `amount_source=GERAR_DAS`

#### Scenario: Parse fail-closed

- **WHEN** o PDF não tem seção 6 legível, o número diverge ou o Total é ambíguo
- **THEN** o sistema MUST NOT inventar `amount_cents` nem sobrescrever um valor já confiado com lixo

### Requirement: Portfolio lê amount_cents local sem Integra

Ao montar `payment_open_competencies`, o sistema SHALL resolver `amount_cents` de cada DAS unpaid nesta ordem:

1. `tax_guides.amount_cents` associado ao `das_number`
2. `pgdasd_operations.amount_cents` da operação correspondente
3. Fallback opcional a evidência/snapshot GERAR_DAS já existente (transitório)

O sistema MUST NOT chamar Integra Contador/SERPRO nem executar `pdftotext` no path de portfolio. A agregação por `period_key` MUST permanecer fail-closed (null se algum DAS da competência não tiver valor).

O contrato público `payment_open_competencies: { period_key, amount_cents }[]` MUST permanecer estável para a SPA.

#### Scenario: Carteira usa operação persistida

- **WHEN** a operação unpaid tem `amount_cents` preenchido e não há `tax_guides` para o DAS
- **THEN** `payment_open_competencies` MUST expor esse valor em centavos na competência agregada

#### Scenario: Sem egress no portfolio

- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a montagem de `payment_open_competencies` MUST NOT disparar HTTP a SERPRO nem job síncrono de extrato

### Requirement: Cobertura de gap via job pós-MONITOR

Após MONITOR PGDAS-D produtivo, o sistema SHALL poder enfileirar CONSEXTRATO16 para `das_number` unpaid do cliente que ainda não tenham `amount_cents`, reutilizando controles de idempotência/rate-limit do pipeline documental existente. Esse enqueue MUST NOT ser feito no GET do portfolio.

#### Scenario: Gap sem valor agenda extrato

- **WHEN** o MONITOR conclui com sucesso e existe DAS unpaid com `das_number` sem `amount_cents`
- **THEN** o sistema MAY enfileirar CONSULTAR_EXTRATO para esse DAS (sujeito a rate-limit)
- **AND** a carteira continua servindo `—` até o ingest persistir o valor
