## MODIFIED Requirements

### Requirement: Menu Ações da seleção sem membership direto

Na carteira `/monitoring/simples-mei` (PGDAS-D e PGMEI), quando houver seleção, a toolbar SHALL exibir dropdown **Ações** (chrome DCTFWeb: subtle, `list-checks`, `UKbd`) contendo apenas **Solicitar consulta** (quando permitido), itens específicos seguros do submódulo (ex.: Serviços MEI) e **Limpar seleção**. O menu MUST NOT oferecer **Associar clientes** nem **Excluir do monitoramento**. Associar SHALL usar botão dedicado que abre o modal de membership. Excluir pela linha SHALL abrir o mesmo modal de membership (associar/excluir), MUST NOT abrir modal de confirmação destrutiva nem excluir imediatamente.

#### Scenario: Menu sem associar/excluir

- **WHEN** o usuário abre **Ações** com clientes selecionados
- **THEN** não há itens Associar clientes nem Excluir do monitoramento
- **AND** Solicitar consulta (se permitido) e Limpar seleção estão disponíveis

#### Scenario: Associar via modal

- **WHEN** o usuário com permissão clica **Associar clientes** na toolbar
- **THEN** abre o modal de associação à carteira (fluxo controlado)
- **AND** a ação não ocorre por item de menu sem revisão

#### Scenario: Excluir pela linha abre modal de membership

- **WHEN** o usuário escolhe **Excluir do monitoramento** no menu da linha
- **THEN** abre o modal de associação à carteira (`AssociateMonitoringClientsModal`)
- **AND** MUST NOT abrir modal de confirmação do tipo "Excluir do monitoramento?"
- **AND** a remoção só ocorre após ação explícita dentro do modal de membership
