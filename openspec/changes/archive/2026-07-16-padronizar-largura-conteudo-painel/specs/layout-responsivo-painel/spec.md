## ADDED Requirements

### Requirement: Largura por arquétipo autenticado
O painel SHALL aplicar largura de conteúdo conforme o arquétipo da página autenticada, sem impor uma coluna estreita única a todas as superfícies.

#### Scenario: Configuração ou detalhe textual no desktop
- **WHEN** uma página autenticada de configuração ou detalhe textual é exibida em viewport desktop
- **THEN** o conteúdo é centralizado em um container confortável de aproximadamente 1024 px e pode usar toda a largura disponível abaixo desse limite

#### Scenario: Detalhe denso no desktop
- **WHEN** uma página autenticada de detalhe contém grids ou painéis auxiliares que exigem mais espaço
- **THEN** o conteúdo pode usar um container amplo de aproximadamente 1152 px

#### Scenario: Lista, home ou workspace
- **WHEN** uma página autenticada segue arquétipo de lista, home ou mestre–detalhe
- **THEN** tabelas, indicadores e workspaces permanecem fluidos na área disponível do painel

### Requirement: Comportamento responsivo
O painel SHALL manter o conteúdo utilizável em viewports menores independentemente do limite máximo usado no desktop.

#### Scenario: Viewport menor que o limite do container
- **WHEN** a largura disponível é menor que o limite máximo do arquétipo
- **THEN** o container ocupa a largura disponível, respeita o padding do `UDashboardPanel` e não provoca overflow horizontal por sua própria estrutura

### Requirement: Regra reutilizável de largura
O frontend SHALL centralizar as variantes de largura dos shells autenticados em uma primitiva compartilhada, preservando a anatomia dos arquétipos do template Nuxt UI fixado.

#### Scenario: Nova superfície de configuração ou detalhe
- **WHEN** uma nova página autenticada de configuração ou detalhe adota o padrão do painel
- **THEN** ela seleciona uma variante nomeada da primitiva compartilhada em vez de introduzir um novo limite de largura arbitrário no shell da página

#### Scenario: Limites locais permanecem independentes
- **WHEN** um input, texto, célula de tabela ou modal necessita de limite próprio
- **THEN** esse limite local pode permanecer sem alterar a variante de largura do shell da página
