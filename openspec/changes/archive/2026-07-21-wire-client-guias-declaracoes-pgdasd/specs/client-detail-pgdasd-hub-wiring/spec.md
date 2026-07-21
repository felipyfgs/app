## ADDED Requirements

### Requirement: Declarações do cliente refletem consulta PGDAS-D local

Quando o hub de declarações lista projeções `PGDAS_D` de um cliente, a resposta pública SHALL enriquecer cada período com a declaração encontrada em `pgdasd_operations` (kind DECLARATION) do mesmo office/cliente/período, expondo número da declaração e situação efetiva derivada da consulta — sem chamar SERPRO e sem inventar status quando não houver operação.

#### Scenario: Declaração consultada deixa de parecer só Pendente

- **WHEN** existe operação DECLARATION em `pgdasd_operations` para o `client_id` e `period_key` da projeção
- **THEN** o item em `GET /api/v1/fiscal/declarations?client_id=` inclui `declaration_number` e `delivery_status`/`situation` efetivos `UP_TO_DATE` (ou equivalente de entregue), mesmo que as colunas de calendário no banco ainda sejam `PENDING`

#### Scenario: Período sem declaração consultada

- **WHEN** a projeção `PGDAS_D` não tem operação DECLARATION correspondente
- **THEN** o item permanece com situação de calendário (`PENDING` ou estado PGDAS de atraso/verificação) e NÃO inventa número de declaração

#### Scenario: Documento só com artefato real

- **WHEN** há artefato/evidência ligada à declaração/período
- **THEN** a resposta pode incluir `document` descritor downloadável; **WHEN** não há artefato
- **THEN** `document` fica ausente ou unavailable — a UI NÃO inventa PDF

### Requirement: Guias do cliente listam DAS da consulta PGDAS-D

Com filtro `client_id`, `GET /api/v1/fiscal/guides` SHALL incluir DAS projetados da consulta local (`pgdasd_operations` kind DAS) além de `tax_guides` emitidos, em shape compatível com a lista de guias (número DAS, competência, emissão, pagamento).

#### Scenario: Cliente com DAS consultados e sem tax_guides

- **WHEN** o cliente tem operações DAS em `pgdasd_operations` e zero `tax_guides`
- **THEN** a lista de guias do cliente NÃO retorna vazia; cada DAS aparece com `identifier_code`/`das_number`, `competence_period_key` e `payment_status` derivado de `payment_located`

#### Scenario: Dedupe com guia emitida

- **WHEN** já existe `tax_guide` com o mesmo número DAS
- **THEN** a lista NÃO duplica a linha virtual

#### Scenario: Sem chamada SERPRO

- **WHEN** o usuário abre a aba Guias do cliente
- **THEN** a API só lê dados locais (office-scoped) e NÃO dispara Integra Contador

### Requirement: UI do detalhe do cliente mostra identificadores reais

As seções Declarações e Guias em `/monitoring/clients/:id` SHALL exibir os identificadores retornados pela API enriquecida (nº declaração / nº DAS) nas colunas principais.

#### Scenario: Tabela Declarações com número

- **WHEN** a API devolve `declaration_number` para um período
- **THEN** a tabela de Declarações mostra esse número (além da obrigação/período)

#### Scenario: Tabela Guias com DAS

- **WHEN** a API devolve linhas DAS virtuais ou guias com `identifier_code`
- **THEN** a tabela de Guias identifica a linha pelo DAS/competência em vez de apenas um id opaco vazio
