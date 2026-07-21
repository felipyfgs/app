## MODIFIED Requirements

### Requirement: Quick consult for selection and row

A superfície SHALL permitir enfileirar consulta do submódulo ativo (PGDAS-D ou PGMEI) para os `client_id` selecionados via item **Solicitar consulta** no menu **Ações** da toolbar, com confirmação explícita e respeito a `canTriggerSync`. A toolbar MUST NOT exibir botão primário solto de consulta. Membership (associar/excluir) MUST NOT ser acionado por item desse menu sem salvaguarda: Associar usa botão + modal; Excluir na linha abre o mesmo modal de membership. A coluna Consulta SHALL oferecer atalho por linha para a mesma consulta (um cliente), sem inventar sucesso quando a API falhar.

#### Scenario: Bulk consult from selection menu

- **WHEN** o usuário seleciona N clientes, escolhe **Solicitar consulta** em **Ações** e confirma
- **THEN** o sistema enfileira até N consultas do submódulo ativo
- **AND** exibe feedback consolidado (ok/falhas)
- **AND** o menu Ações não inclui Associar nem Excluir

#### Scenario: Row consult shortcut

- **WHEN** o usuário aciona o atalho de consulta na linha
- **THEN** o fluxo de consulta daquele `client_id` é iniciado (confirmação quando aplicável)
- **AND** abrir histórico/comunicação NÃO dispara consulta SERPRO

#### Scenario: Row exclude opens membership modal

- **WHEN** o usuário escolhe excluir do monitoramento na linha
- **THEN** o modal de membership abre para manipular a carteira
- **AND** não há exclusão imediata nem modal só de confirmação
