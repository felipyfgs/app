> **SUPERSEDED:** este delta não deve ser sincronizado. `ui-template-fidelity-total` substitui carregamento incremental por fidelidade integral ao template.

## MODIFIED Requirements

### Requirement: Paridade estrutural por arquétipo de tela
O sistema SHALL implementar cada rota autenticada copiando o arquétipo correspondente do template: dashboard, lista administrativa, mestre–detalhe ou settings, e alterando apenas conteúdo e integrações necessárias. Nas listas grandes, a remoção do footer paginado e o carregamento incremental automático SHALL ser registrados como adaptação funcional server-side e MUST preservar navbar, toolbar, tabela e ações do arquétipo.

#### Scenario: Dashboard operacional
- **WHEN** o usuário abre o dashboard
- **THEN** navbar, ações compactas, toolbar, grade de indicadores e conteúdo subsequente seguem a composição de `pages/index.vue` e `HomeStats.vue`, usando somente métricas reais

#### Scenario: Lista administrativa grande
- **WHEN** o usuário abre Clientes, Exportações ou Sincronizações
- **THEN** ação primária, faixa utilitária fixa, tabela, ações de linha e estados seguem `customers.vue`, enquanto o footer é omitido e os próximos blocos chegam automaticamente por paginação/cursor server-side

#### Scenario: Catálogo de notas
- **WHEN** o usuário abre Notas em desktop ou mobile
- **THEN** a experiência segue o mestre–detalhe de Inbox, usando painéis adjacentes no desktop e slideover no mobile, com rota canônica e dados fiscais sanitizados

#### Scenario: Detalhe e administração
- **WHEN** o usuário abre o detalhe de Cliente ou Administração
- **THEN** navbar, toolbar de seções, largura do conteúdo e cards seguem o arquétipo Settings da referência

### Requirement: Composição reconhecível após abstrações locais
Componentes compartilhados criados durante a refatoração MUST permanecer pequenos, explícitos e rastreáveis e MUST NOT esconder o `UDashboardPanel`, a API do `UTable`, os slots principais do template ou as decisões de responsividade da página.

#### Scenario: Preset tabular compartilhado
- **WHEN** uma tabela reutiliza preset visual, estado incremental ou indicador transitório
- **THEN** suas colunas, slots, sorting, seleção, virtualização, ações e chamada da API continuam declarados de forma inspecionável no consumidor

#### Scenario: Wrapper universal proposto
- **WHEN** uma abstração exigiria configurar navbar, toolbar, body, tabela e overlays por um objeto genérico
- **THEN** a abstração é rejeitada em favor da cópia/adaptação explícita do arquétipo

### Requirement: Presets tabulares rastreáveis e cobertura integral
O sistema SHALL definir presets compartilhados de `UTable` derivados literalmente de `customers.vue` e `HomeSales.vue`, MUST aplicar um desses presets a toda superfície tabular autenticada e MUST registrar exceções visuais justificadas no código ou na matriz de paridade. O preset de lista grande SHALL limitar o container rolável sem fixar o root inteiro e SHALL delegar cabeçalho fixo à prop nativa `sticky`. A regressão visual SHALL cobrir cada rota tabular de primeiro nível em desktop e mobile, além de verificar ausência de overflow em 360 px.

#### Scenario: Nova tabela administrativa
- **WHEN** uma rota autenticada adiciona uma tabela de dados operacionais
- **THEN** ela reutiliza o preset administrativo ou denso rastreável a `customers.vue`, com cabeçalho, bordas e densidade equivalentes, sem footer persistente

#### Scenario: Tabela compacta do dashboard
- **WHEN** o dashboard exibe uma tabela auxiliar sem carregamento incremental
- **THEN** ela usa a variante compacta derivada de `HomeSales.vue` e não inventa uma quarta composição visual

#### Scenario: Header fixo sem tabela inteira fixa
- **WHEN** uma lista grande rola dentro do root do `UTable`
- **THEN** somente o cabeçalho solicitado por `sticky="header"` permanece fixo e o root não usa `sticky top-0` para prender a tabela inteira

#### Scenario: Cobertura responsiva
- **WHEN** a suíte visual percorre as rotas tabulares autenticadas
- **THEN** existem evidências determinísticas em 1440×900 e 390×844 e verificação de overflow em 360 px sem material fiscal real

### Requirement: Estado tabular único e acessível
Cada tabela SHALL distinguir internamente loading inicial, carga adicional, erro, vazio, exaustão e dados preservados após falha, MUST apresentar somente um estado vazio e SHALL manter nomes acessíveis em controles icônicos e ações de linha.

#### Scenario: Resposta inicial vazia
- **WHEN** a API retorna o primeiro bloco sem itens e não há erro
- **THEN** a superfície apresenta um único estado vazio em pt-BR sem combinar a linha vazia padrão do `UTable` com outro `UEmpty`

#### Scenario: Falha com dados anteriores
- **WHEN** uma atualização ou carga adicional falha depois de a tabela já possuir linhas
- **THEN** as linhas permanecem visíveis, um status anuncia a falha e uma ação acessível oferece nova tentativa

#### Scenario: Fim do resultado
- **WHEN** a API informa ausência de próximo cursor ou página
- **THEN** a superfície deixa de disparar carregamentos automáticos sem renderizar footer ou mensagem permanente de fim
