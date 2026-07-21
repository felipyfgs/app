## ADDED Requirements

### Requirement: Menu de seleção PGDAS-D só com ações do domínio

Na carteira `/monitoring/simples-mei` (submódulo PGDAS-D), quando houver clientes selecionados, a toolbar SHALL exibir um dropdown rotulado **Ações** cujo conteúdo se limita a ações do domínio PGDAS-D e navegação básica do hub: preferências registradas, destinatários e documentos locais (quando disponível), histórico local de comunicação (quando disponível), histórico de busca PGDAS-D (quando disponível), abrir cliente e limpar seleção. O menu MUST NOT oferecer ações de Regime de apuração, DEFIS ou outros serviços Integra-SN fora de PGDAS-D.

#### Scenario: Menu sem Regime nem DEFIS

- **WHEN** o usuário abre **Ações** com um ou mais clientes selecionados na aba PGDAS-D
- **THEN** não há itens de atualizar/histórico de regimes, opção anual, resolução de caixa, declarações DEFIS, última DEFIS ou declaração DEFIS específica
- **AND** as ações básicas PGDAS aplicáveis à seleção estão listadas com labels curtos em pt-BR

#### Scenario: Seleção múltipla

- **WHEN** o usuário seleciona mais de um cliente
- **THEN** ações que exigem um único cliente ficam desabilitadas de forma honesta
- **AND** limpar seleção permanece disponível

### Requirement: Consulta PGDAS-D permanece no atalho primário

A consulta PGDAS-D da seleção SHALL continuar no botão primário **Consultar** da toolbar e MUST NOT depender do menu **Ações**. Abrir o menu MUST NOT enfileirar consulta SERPRO.

#### Scenario: Consultar fora do menu Ações

- **WHEN** há seleção na aba PGDAS-D
- **THEN** o usuário enfileira consulta PGDAS-D pelo botão **Consultar**
- **AND** o menu **Ações** não inclui item de consulta PGDAS-D em lote
