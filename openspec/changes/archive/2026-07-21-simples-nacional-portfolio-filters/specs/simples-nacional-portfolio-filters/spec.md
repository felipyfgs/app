## ADDED Requirements

### Requirement: Popover Filtro da carteira Simples Nacional

A carteira Simples Nacional (PGDAS-D) SHALL expor no recurso Filtro (popover) os eixos Situação, Cliente, Competência e Envio, além da busca textual existente. A carteira MEI MUST NÃO receber estes chips adicionais nesta change.

#### Scenario: Chips no popover PGDASD

- **WHEN** o usuário abre Filtro em `/monitoring/simples-mei` (submodule PGDASD)
- **THEN** o popover MUST oferecer Situação, Cliente, Competência e Envio (Enviado / Não enviado)

#### Scenario: MEI inalterado

- **WHEN** o usuário abre Filtro na carteira MEI
- **THEN** o popover MUST permanecer sem os chips Situação e Envio introduzidos para o Simples Nacional (comportamento atual do ramo PGMEI)

### Requirement: Filtro Situação sincronizado com KPI

O chip Situação SHALL usar o mesmo estado `filters.situation` das abas KPI e o param de API `situation` já suportado pelo portfolio.

#### Scenario: Chip e KPI compartilham situação

- **WHEN** o usuário seleciona uma situação no popover ou numa aba KPI
- **THEN** a lista de clientes MUST filtrar por essa situação e o outro controle MUST refletir o mesmo valor

### Requirement: Filtro Envio (Enviado / Não enviado)

A API de portfolio `simples_mei` com submodule PGDASD SHALL aceitar `send_status` (`sent`, `not_sent`, CSV) e restringir a lista conforme o status de comunicação agregado do cliente. **Enviado** inclui qualquer tracking distinto de `NO_HISTORY` e `NOT_CONFIGURED` (inclui `QUEUED`, `FAILED`, `PARTIAL`, `SENT`, `DELIVERED`, `READ`). **Não enviado** inclui `NO_HISTORY` e `NOT_CONFIGURED`.

#### Scenario: Filtrar só enviados

- **WHEN** o usuário aplica Envio = Enviado no popover do Simples Nacional
- **THEN** a lista MUST conter apenas clientes cujo tracking agregado de comunicação PGDAS-D não seja `NO_HISTORY` nem `NOT_CONFIGURED`

#### Scenario: Filtrar só não enviados

- **WHEN** o usuário aplica Envio = Não enviado
- **THEN** a lista MUST conter apenas clientes com tracking `NO_HISTORY` ou `NOT_CONFIGURED` (ou equivalente sem histórico configurado)
