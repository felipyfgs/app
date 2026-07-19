## ADDED Requirements

### Requirement: Shell autenticado permanece usável em viewport estreito

O sistema SHALL garantir que páginas autenticadas no layout default exponham `UDashboardSidebarCollapse` (via `ShellPageNavbar` ou equivalente) e que o drawer/sidebar Nuxt UI permita navegar sem overflow horizontal grave no content root (`min-w-0`).

#### Scenario: Navbar com collapse

- **WHEN** uma page autenticada com chrome canônico é renderizada em viewport `< lg`
- **THEN** o navbar inclui controle de collapse da sidebar
- **AND** o conteúdo principal não força scroll horizontal da viewport

#### Scenario: Fechar sidebar ao navegar

- **WHEN** o usuário escolhe um destino na sidebar em viewport estreito
- **THEN** a sidebar fecha (comportamento drawer) após a navegação

### Requirement: Listas densas usam cards abaixo de md

O sistema SHALL apresentar listas admin/operacionais densas (via `ShellDataTable` ou composição `ModuleDataTable`) como **cards** em viewport `< md`, e como tabela em `md+`, sem depender de `min-w-*` que cause overflow horizontal grave na viewport do telefone.

#### Scenario: Lista operacional no telefone

- **WHEN** o usuário abre uma lista migrada (ex.: exports, closing, docs imports, work processes) em viewport `< md`
- **THEN** as linhas aparecem como cards com campos primários e ações acessíveis
- **AND** a viewport não exige scroll horizontal para ler o conteúdo principal do card

#### Scenario: Lista no desktop

- **WHEN** a mesma lista é vista em viewport `md+`
- **THEN** a grade tabular permanece disponível com presets `table-ui`

### Requirement: Mestre-detalhe usa slideover ou stack abaixo de lg

O sistema SHALL, em superfícies mestre–detalhe (mailbox, fila de trabalho, calendário e equivalentes), usar painéis adjacentes apenas em `lg+` e, em `< lg`, apresentar o detalhe via `USlideover` ou empilhamento, sem dois painéis competindo por largura.

#### Scenario: Abrir detalhe no telefone

- **WHEN** o usuário seleciona um item da lista mestre em viewport `< lg`
- **THEN** o detalhe abre em slideover (ou stack full-width)
- **AND** o usuário pode fechar o detalhe e voltar à lista

#### Scenario: Desktop mantém split

- **WHEN** a mesma superfície é usada em viewport `lg+`
- **THEN** lista e detalhe podem coexistir em painéis adjacentes (resizable quando já suportado)

### Requirement: Settings e detalhe empilham sem comprimir o shell

O sistema SHALL empilhar formulários/settings e navegação de seção em viewport estreito (`SectionNavigation` como select `< lg` quando aplicável) e MUST NOT introduzir `max-w-*` arbitrário no shell que comprima listas/workspaces fluidos. Larguras de página permanecem via `DashboardContent` (`comfortable` / `wide` / fluido).

#### Scenario: Conta no telefone

- **WHEN** o usuário abre `/conta/*` ou settings equivalentes em viewport `< lg`
- **THEN** a navegação de seção é usável (select ou equivalente)
- **AND** os formulários empilham em coluna sem overflow-x grave

#### Scenario: Detalhe de cliente

- **WHEN** o usuário abre `/clients/:id` em viewport abaixo de `xl`
- **THEN** o conteúdo principal e o aside não ficam lado a lado em colunas fixas estreitas
- **AND** ambos permanecem acessíveis em fluxo vertical (stack)

### Requirement: Auth e home sem regressão mobile

O sistema SHALL manter o layout auth em coluna única no mobile (marca oculta ou compacta) e a home com toolbars/KPIs que fazem wrap sem overflow-x grave.

#### Scenario: Login no telefone

- **WHEN** o usuário abre `/login` em viewport `< lg`
- **THEN** o formulário é usável em coluna única
- **AND** o painel de marca full-bleed desktop não bloqueia o form

#### Scenario: Home no telefone

- **WHEN** o usuário abre `/` em viewport `< sm`
- **THEN** KPIs/atalhos se reorganizam (wrap/grid) sem scroll horizontal da viewport

### Requirement: Gate de responsividade impede regressão

O sistema SHALL manter teste(s) automatizado(s) que verificam o contrato mobile das superfícies migradas (presença do caminho de cards/shell mobile e/ou ausência de padrões proibidos documentados no design) e o gate `pnpm run test:gate` MUST passar ao concluir a change.

#### Scenario: Gate unitário

- **WHEN** a suíte unitária de contrato mobile/shell é executada
- **THEN** as asserções do catálogo migrado passam

#### Scenario: Gate web completo

- **WHEN** `pnpm run test:gate` é executado em `apps/web` após N4
- **THEN** o comando termina com sucesso
