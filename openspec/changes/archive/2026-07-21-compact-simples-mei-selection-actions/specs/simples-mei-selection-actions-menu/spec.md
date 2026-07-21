## REMOVED Requirements

### Requirement: Consulta PGDAS-D permanece no atalho primário

**Reason**: O produto pediu padrão compacto (paridade com `/clients`): consulta em lote não deve ocupar botão primário solto na toolbar.

**Migration**: Usar o item **Solicitar consulta** dentro do menu **Ações**; atalho por linha permanece na coluna Consulta.

### Requirement: Menu de seleção PGDAS-D só com ações do domínio

**Reason**: O menu de seleção deve espelhar o padrão limpo de DCTFWeb para chrome, mas membership (associar/excluir) não cabe em item de menu sem salvaguarda — fica no modal dedicado / linha com confirmação.

**Migration**: Menu **Ações** só com Solicitar consulta + Limpar; Associar no botão que abre o modal; Excluir na linha com confirmação.

## ADDED Requirements

### Requirement: Menu Ações da seleção sem membership direto

Na carteira `/monitoring/simples-mei` (PGDAS-D e PGMEI), quando houver seleção, a toolbar SHALL exibir dropdown **Ações** (chrome DCTFWeb: subtle, `list-checks`, `UKbd`) contendo apenas **Solicitar consulta** (quando permitido), itens específicos seguros do submódulo (ex.: Serviços MEI) e **Limpar seleção**. O menu MUST NOT oferecer **Associar clientes** nem **Excluir do monitoramento**. Associar SHALL usar botão dedicado que abre o modal de membership. Excluir pela linha SHALL exigir confirmação explícita.

#### Scenario: Menu sem associar/excluir

- **WHEN** o usuário abre **Ações** com clientes selecionados
- **THEN** não há itens Associar clientes nem Excluir do monitoramento
- **AND** Solicitar consulta (se permitido) e Limpar seleção estão disponíveis

#### Scenario: Associar via modal

- **WHEN** o usuário com permissão clica **Associar clientes** na toolbar
- **THEN** abre o modal de associação à carteira (fluxo controlado)
- **AND** a ação não ocorre por item de menu sem revisão

#### Scenario: Excluir com confirmação

- **WHEN** o usuário escolhe excluir do monitoramento na linha
- **THEN** um modal de confirmação é exibido antes da exclusão
- **AND** a remoção só ocorre após confirmar

### Requirement: Solicitar consulta no menu Ações

A consulta do submódulo ativo (PGDAS-D ou PGMEI) da seleção SHALL ser acionada pelo item **Solicitar consulta** dentro do menu **Ações** (com confirmação explícita) e MUST NOT ocupar um botão primário solto na toolbar. Abrir o menu MUST NOT enfileirar consulta SERPRO; a enfileiragem ocorre somente após confirmar no modal.

#### Scenario: Consulta via menu

- **WHEN** há seleção e o usuário escolhe **Solicitar consulta** em **Ações** e confirma
- **THEN** o sistema enfileira as consultas do submódulo ativo
- **AND** a toolbar NÃO exibe botão solto de consulta

#### Scenario: Abrir menu não consulta

- **WHEN** o usuário apenas abre ou fecha o menu **Ações**
- **THEN** nenhuma consulta é enfileirada
