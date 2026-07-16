## ADDED Requirements

### Requirement: Presets tabulares rastreáveis e cobertura integral
O sistema SHALL definir presets compartilhados de `UTable` derivados literalmente de `customers.vue` e `HomeSales.vue`, MUST aplicar um desses presets a toda superfície tabular autenticada e MUST registrar exceções visuais justificadas no código ou na matriz de paridade. A regressão visual SHALL cobrir cada rota tabular de primeiro nível em desktop e mobile, além de verificar ausência de overflow em 360 px.

#### Scenario: Nova tabela administrativa
- **WHEN** uma rota autenticada adiciona uma tabela de dados operacionais
- **THEN** ela reutiliza o preset administrativo ou denso rastreável a `customers.vue`, com cabeçalho, bordas, densidade e footer equivalentes

#### Scenario: Tabela compacta do dashboard
- **WHEN** o dashboard exibe uma tabela auxiliar sem paginação
- **THEN** ela usa a variante compacta derivada de `HomeSales.vue` e não inventa uma quarta composição visual

#### Scenario: Cobertura responsiva
- **WHEN** a suíte visual percorre as rotas tabulares autenticadas
- **THEN** existem evidências determinísticas em 1440×900 e 390×844 e verificação de overflow em 360 px sem material fiscal real

### Requirement: Estado tabular único e acessível
Cada tabela SHALL distinguir loading, erro, vazio e dados preservados após falha, MUST apresentar somente um estado vazio e SHALL manter nomes acessíveis em controles icônicos e ações de linha.

#### Scenario: Resposta vazia
- **WHEN** a API retorna uma página sem itens e não há erro
- **THEN** a superfície apresenta um único estado vazio em pt-BR sem combinar a linha vazia padrão do `UTable` com outro `UEmpty`

#### Scenario: Falha com dados anteriores
- **WHEN** uma atualização falha depois de a tabela já possuir linhas
- **THEN** as linhas permanecem visíveis e um alerta de atualização falha oferece nova tentativa

