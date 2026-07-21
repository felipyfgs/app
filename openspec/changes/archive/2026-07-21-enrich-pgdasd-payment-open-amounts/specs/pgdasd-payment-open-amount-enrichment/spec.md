## ADDED Requirements

### Requirement: Resolução local de amount_cents nas competências unpaid

Ao montar `payment_open_competencies` do portfolio PGDAS-D, o sistema SHALL resolver `amount_cents` de cada competência unpaid com fontes locais do office autenticado, nesta ordem:

1. `tax_guides.amount_cents` associado ao `das_number` da operação (`identifier_code`).
2. Evidência ou snapshot normalizado de run GERAR_DAS bem-sucedida do mesmo office/cliente, casando `dados.numeroDocumento` (ou `document_number` normalizado) com o `das_number`, usando `dados.total` (ou `amount` normalizado) convertido para centavos inteiros.

O sistema MUST NOT chamar SERPRO/Integra Contador ao montar essa lista. O sistema MUST NOT inventar valor quando nenhuma fonte local resolver — `amount_cents` permanece `null`.

A agregação por `period_key` MUST continuar: somar cents só quando todos os DAS unpaid da competência tiverem valor; se algum faltar, `amount_cents` da competência MUST ser `null`.

#### Scenario: Prefere tax_guides

- **WHEN** existe `tax_guides.amount_cents` para o `das_number` unpaid
- **THEN** o valor da competência MUST vir dessa guia (não da evidência GERAR_DAS)

#### Scenario: Fallback GERAR_DAS local

- **WHEN** não há guia materializada e existe evidência/snapshot GERAR_DAS SUCCESS do office com `numeroDocumento` igual ao `das_number` e `total` numérico
- **THEN** `payment_open_competencies` MUST expor `amount_cents` correspondente a esse total em centavos

#### Scenario: Sem fonte local

- **WHEN** o DAS unpaid não tem guia nem evidência GERAR_DAS com total casável
- **THEN** `amount_cents` MUST ser `null` (UI continua mostrando "—")

#### Scenario: Sem egress

- **WHEN** o portfolio monta `payment_open_competencies`
- **THEN** o sistema MUST NOT realizar chamada HTTP a SERPRO ou provedor externo só para obter o valor

### Requirement: DasGuideDto reconhece campos oficiais GERAR_DAS

`DasGuideDto::fromIntegraBody` SHALL mapear os campos oficiais da resposta GERAR_DAS `numeroDocumento` → `document_number` e `total` (fallback `principal`) → `amount`, além dos aliases já aceitos (`document_number`, `numero_documento`, `amount`, `valor`).

#### Scenario: Body com total e numeroDocumento

- **WHEN** o body Integra traz `dados.numeroDocumento` e `dados.total` (ou equivalentes no envelope já normalizado pelo mapper)
- **THEN** o DTO MUST popular `documentNumber` e `amount` sem exigir as chaves em inglês
