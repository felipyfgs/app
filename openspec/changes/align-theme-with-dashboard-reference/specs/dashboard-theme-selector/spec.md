## ADDED Requirements

### Requirement: Tema inicial alinhado ao dashboard de referência
O painel SHALL iniciar com a paleta semântica `primary` configurada como `green`, a paleta `neutral` configurada como `zinc`, a fonte `Public Sans` e a escala verde canônica de `.local/reference/nuxt-dashboard-template`.

#### Scenario: Carregamento inicial do painel
- **WHEN** o frontend é carregado sem alteração de tema durante o runtime
- **THEN** componentes `primary` usam a escala verde do arquétipo e superfícies neutras usam `zinc`

### Requirement: Seleção de paleta no menu do usuário
O painel SHALL oferecer no menu do usuário um submenu de tema com as paletas primárias e neutras disponibilizadas pelo seletor do dashboard de referência, indicando a opção ativa.

#### Scenario: Usuário abre o seletor de tema
- **WHEN** o usuário abre `Tema` no menu do usuário
- **THEN** o painel apresenta as opções de cor primária e neutra com chips visuais e marca a seleção atual

### Requirement: Aplicação imediata da seleção
O painel SHALL atualizar o alias semântico correspondente em `useAppConfig()` quando uma cor primária ou neutra for selecionada, sem fechar preventivamente o submenu.

#### Scenario: Usuário escolhe uma nova cor primária
- **WHEN** o usuário seleciona uma opção diferente em `Cor primária`
- **THEN** `appConfig.ui.colors.primary` recebe a cor escolhida e os componentes `primary` refletem a mudança no runtime atual

#### Scenario: Usuário escolhe uma nova paleta neutra
- **WHEN** o usuário seleciona uma opção diferente em `Cor neutra`
- **THEN** `appConfig.ui.colors.neutral` recebe a cor escolhida e as superfícies neutras refletem a mudança no runtime atual

### Requirement: Compatibilidade com o menu adaptado do produto
O seletor de tema MUST coexistir com a alternância claro/escuro e com os itens de conta, instalação PWA e logout já presentes, sem introduzir links demonstrativos do template.

#### Scenario: Menu do usuário após a incorporação
- **WHEN** o menu do usuário é renderizado para um usuário autenticado
- **THEN** as ações existentes continuam disponíveis de acordo com seu contexto e o seletor de tema aparece no mesmo menu
