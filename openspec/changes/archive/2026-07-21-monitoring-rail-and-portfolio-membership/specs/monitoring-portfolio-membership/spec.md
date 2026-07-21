## ADDED Requirements

### Requirement: Opt-out de carteira por módulo

O sistema SHALL permitir excluir um cliente elegível da carteira de monitoramento de um módulo (e submodule, quando aplicável) sem remover o cadastro CRM nem alterar `tax_regime`. A exclusão MUST ser tenant-scoped ao office ativo. Incluir de volta MUST remover apenas o opt-out e MUST NOT inventar elegibilidade fora do regime do módulo.

#### Scenario: Excluir remove da lista

- **WHEN** o operador exclui um cliente da carteira do módulo/submodule ativo
- **THEN** o cliente deixa de aparecer nessa carteira
- **AND** o registro do cliente no CRM permanece

#### Scenario: Incluir reinstaura elegível excluído

- **WHEN** o operador inclui um cliente que estava excluído e ainda é elegível pelo regime do módulo
- **THEN** o cliente volta a aparecer na carteira
- **AND** nenhuma chamada SERPRO é disparada só por incluir

#### Scenario: Incluir fora do regime é rejeitado

- **WHEN** o operador tenta incluir um cliente cujo regime não é elegível para o módulo/submodule
- **THEN** a API rejeita de forma honesta (sem mudar `tax_regime`)

### Requirement: Modal Associar clientes

O produto SHALL oferecer um modal “Associar clientes” no contexto da carteira do módulo, permitindo buscar por nome/CNPJ, incluir elegíveis (incluindo antes excluídos) e excluir clientes do monitoramento daquele módulo/submodule.

#### Scenario: Modal no DAS do Simples

- **WHEN** o operador abre Associar clientes na carteira DAS do Simples (PGDASD)
- **THEN** pode incluir e excluir clientes dessa carteira sem usar o modal de categorias fiscais

### Requirement: Ação Excluir na linha

O produto SHALL expor a ação “Excluir” no menu de ações da linha do cliente em todas as carteiras de monitoramento aplicáveis, com o mesmo efeito de opt-out do módulo/submodule da superfície.

#### Scenario: Excluir pela linha

- **WHEN** o operador escolhe Excluir no dropdown de ações de uma linha
- **THEN** o cliente é excluído da carteira daquela superfície após confirmação
- **AND** a lista atualiza sem inventar status fiscal

### Requirement: Redirect pós-cadastro para carteira correta

Ao criar um cliente em `/clients`, o produto SHALL redirecionar para a carteira de monitoramento alinhada ao regime: Simples Nacional → aba PGDAS-D; MEI → aba PGMEI. Outros regimes MUST manter o fluxo atual da ficha CRM.

#### Scenario: Create Simples Nacional

- **WHEN** um cliente é criado com regime da família Simples Nacional (não MEI)
- **THEN** o usuário é levado à carteira `/monitoring/simples-mei` na aba PGDASD

#### Scenario: Create MEI

- **WHEN** um cliente é criado com regime MEI
- **THEN** o usuário é levado à carteira Simples/MEI na aba PGMEI

#### Scenario: Create outro regime

- **WHEN** um cliente é criado com regime fora de SN/MEI
- **THEN** o fluxo permanece o da ficha CRM (sem forçar carteira SN/MEI)
