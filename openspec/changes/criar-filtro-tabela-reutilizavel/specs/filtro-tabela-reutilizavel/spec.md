## ADDED Requirements

### Requirement: Filtro estruturado mantém estado aplicado separado do rascunho
O componente SHALL receber definições e modelos aplicados de forma controlada e SHALL manter internamente apenas o rascunho da edição atual. Confirmar SHALL emitir uma única atualização completa; fechar o seletor ou editor sem confirmar MUST descartar o rascunho sem emitir alteração.

#### Scenario: Fechar edição sem confirmar
- **WHEN** o usuário alterar o valor de um filtro e fechar o editor sem confirmar
- **THEN** o modelo aplicado SHALL permanecer inalterado e nenhuma consulta SHALL ser solicitada

#### Scenario: Confirmar uma edição
- **WHEN** o usuário confirmar um valor válido
- **THEN** o componente SHALL emitir exatamente uma atualização com o filtro adicionado ou substituído

### Requirement: Filtros ativos são únicos, ordenados e removíveis
O núcleo SHALL normalizar filtros por definição, MUST manter no máximo um filtro ativo por chave e SHALL ordenar chips conforme a ordem das definições. Remover SHALL emitir uma única atualização sem a chave; limpar tudo SHALL emitir um evento dedicado. Valores vazios definidos como `all`, `''` ou `null` MUST NOT formar filtros ativos.

#### Scenario: Adicionar campo já ativo
- **WHEN** uma chave já possuir filtro aplicado
- **THEN** ela MUST NOT aparecer como opção duplicada de adição e uma edição SHALL substituir o modelo existente

#### Scenario: Normalizar valores vazios
- **WHEN** um modelo contiver valor vazio para sua definição
- **THEN** o núcleo SHALL omiti-lo dos chips e da serialização aplicada

### Requirement: Tipos de editor preservam valor e rótulo válidos
Definições SHALL suportar opção, competência mensal e cliente com operador fixo `eq`, exibido como `é`. Competência MUST aceitar somente `YYYY-MM`; cliente SHALL preservar um rótulo visual separado do identificador bruto e SHALL permitir editor customizado por slot.

#### Scenario: Competência inválida
- **WHEN** o rascunho de competência não representar um mês `YYYY-MM` válido
- **THEN** a ação de confirmar MUST permanecer indisponível

#### Scenario: Cliente selecionado
- **WHEN** o editor customizado confirmar um cliente
- **THEN** o modelo SHALL guardar o identificador bruto e o rótulo visual correspondente

### Requirement: Seletor de filtros é responsivo e acessível
O desktop SHALL usar `UPopover` com `UCommandPalette` para selecionar campos e o mobile SHALL usar `UDrawer` sem overflow horizontal. Chips SHALL usar controles Nuxt UI agrupados e fornecer nomes acessíveis para editar e remover cada filtro.

#### Scenario: Selecionar campo no desktop
- **WHEN** a viewport for desktop e o usuário acionar `Adicionar filtro`
- **THEN** um popover SHALL apresentar apenas definições ainda inativas

#### Scenario: Editar filtro no mobile
- **WHEN** a viewport for mobile e o usuário adicionar ou editar um filtro
- **THEN** o editor SHALL abrir em drawer e seus controles MUST permanecer dentro da largura disponível
