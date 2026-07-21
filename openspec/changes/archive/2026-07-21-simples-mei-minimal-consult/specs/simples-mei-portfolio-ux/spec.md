## ADDED Requirements

### Requirement: Simples MEI portfolio keeps compact informative columns

A carteira `/monitoring/simples-mei` (PGDAS-D e PGMEI) SHALL manter as colunas canônicas Cliente, Situação, Ações, Hist. comunicação e Consulta. A coluna Hist. comunicação SHALL exibir no máximo um atalho de ícone por linha (sem trio redundante status+download+busca). Ações informativas SHALL permanecer somente leitura (sem switch de envio automático).

#### Scenario: Tracking cell is single affordance

- **WHEN** o usuário vê uma linha PGDAS-D ou PGMEI
- **THEN** a célula Hist. comunicação tem um único controle principal de ícone
- **AND** não há trio de botões status + download + search na mesma célula

### Requirement: Quick consult for selection and row

A superfície SHALL expor botão **Consultar** na toolbar quando houver seleção (≥1), enfileirando a consulta do submódulo ativo (PGDAS-D ou PGMEI) para os `client_id` selecionados, com confirmação explícita e respeito a `canTriggerSync`. A coluna Consulta SHALL oferecer atalho por linha para a mesma consulta (um cliente), sem inventar sucesso quando a API falhar.

#### Scenario: Bulk consult from selection

- **WHEN** o usuário seleciona N clientes e confirma Consultar
- **THEN** o sistema enfileira até N consultas do submódulo ativo
- **AND** exibe feedback consolidado (ok/falhas)

#### Scenario: Row consult shortcut

- **WHEN** o usuário aciona o atalho de consulta na linha
- **THEN** o fluxo de consulta daquele `client_id` é iniciado (confirmação quando aplicável)
- **AND** abrir histórico/comunicação NÃO dispara consulta SERPRO
