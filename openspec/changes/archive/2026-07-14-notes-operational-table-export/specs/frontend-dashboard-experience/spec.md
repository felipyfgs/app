## ADDED Requirements

### Requirement: Notas como posto operacional em tabela densa
O sistema SHALL apresentar o catálogo de Notas em tabela administrativa densa alinhada visualmente a Clientes (cabeçalho, densidade, bordas, estados de loading/vazio), com colunas prioritárias legíveis ao operador (número ou chave curta, papel, contraparte por nome, competência, valor, situação), tipografia mono apenas em CNPJ/chave, e paginação por cursor da API (carregar mais), sem baixar o catálogo inteiro no cliente.

#### Scenario: Linha de nota no desktop
- **WHEN** o catálogo devolve notas com projeção enriquecida
- **THEN** cada linha exibe ao menos número (ou fallback de chave curta), papel fiscal legível, contraparte (nome quando existir; CNPJ mono secundário), competência, valor em BRL e status

#### Scenario: Cursor preservado
- **WHEN** o usuário carrega mais páginas do catálogo
- **THEN** o sistema usa `next_cursor`, acumula linhas e não simula offset client-side sobre o universo do escritório

#### Scenario: Mobile
- **WHEN** o viewport é móvel
- **THEN** identidade da nota, valor e status permanecem visíveis; colunas secundárias somem ou vão ao detalhe

### Requirement: Tabs de visualização Por documento e Por empresa
O sistema SHALL oferecer no shell de Notas abas de visualização **Por documento** e **Por empresa**, refletidas na URL, sem inventar métricas.

#### Scenario: Por documento
- **WHEN** o usuário está na aba Por documento
- **THEN** vê a tabela de notas com os filtros ativos da URL

#### Scenario: Por empresa
- **WHEN** o usuário está na aba Por empresa
- **THEN** vê linhas agregadas por cliente do escritório ativo (identidade + contagem de notas no filtro) derivadas da API, não de um agrupamento incompleto só no browser

#### Scenario: Drill-down
- **WHEN** o usuário abre uma empresa a partir da aba Por empresa
- **THEN** o sistema navega para Por documento com `client_id` (e filtros compatíveis) na URL

### Requirement: Seleção em massa com exportação a partir de Notas
O sistema SHALL permitir multi-seleção de notas **já carregadas** somente quando o usuário tiver permissão de exportar, e SHALL oferecer ações reais: exportar seleção (lista limitada de chaves) e exportar filtro atual. O sistema MUST NOT exibir checkboxes cosméticos sem ação.

#### Scenario: Exportar filtro
- **WHEN** um usuário ADMIN ou OPERATOR solicita exportar com os filtros atuais do catálogo
- **THEN** o sistema cria uma exportação assíncrona com esses filtros e informa o resultado de forma observável (toast e/ou link para Exportações)

#### Scenario: Exportar seleção
- **WHEN** o usuário seleciona N notas carregadas (N ≤ teto configurado) e solicita exportar seleção
- **THEN** o sistema envia as chaves de acesso como escopo da exportação e não inclui chaves de outro escritório

#### Scenario: VIEWER
- **WHEN** o usuário é VIEWER
- **THEN** não vê multi-select de exportação nem botão de criar export a partir de Notas

#### Scenario: Acima do teto
- **WHEN** a seleção excede o teto de chaves permitido
- **THEN** o sistema impede a solicitação e explica o limite sem iniciar job

### Requirement: Detalhe de nota sem abandonar o posto
O sistema SHALL manter a rota canônica `/notes/:accessKey` e o detalhe legível (partes, locais, cStat, chave copiável, download XML auditado) em painel adjacente, drawer ou slideover, de modo que a tabela e os filtros permaneçam utilizáveis ao fechar o detalhe.

#### Scenario: Seleção a partir da tabela
- **WHEN** o usuário abre uma nota na tabela em desktop
- **THEN** a URL atualiza para `/notes/:accessKey` com query de filtros e o detalhe fica acessível sem perder o contexto da lista

#### Scenario: Mobile
- **WHEN** o viewport é menor que `lg`
- **THEN** o detalhe usa slideover e o fechamento restaura a lista com os mesmos filtros

## MODIFIED Requirements

### Requirement: Tabelas administrativas consistentes e server-side
O sistema SHALL apresentar Clientes, Notas, Exportações e Sincronizações com cabeçalho, bordas, densidade, alinhamento, estado de carregamento e paginação visualmente consistentes com a tabela do template, preservando o modelo server-side de cada API. Em Notas, a lista principal MUST ser tabela densa com cursor (não grade de cards nem lista monoespaçada de chave como título).

#### Scenario: Lista de clientes paginada
- **WHEN** o usuário muda a página da lista de Clientes
- **THEN** o sistema solicita a página à API, mantém busca e filtros e não pagina localmente apenas os registros já recebidos

#### Scenario: Lista paginada por cursor
- **WHEN** o usuário carrega mais Notas ou Sincronizações
- **THEN** o sistema usa o cursor fornecido pela API, mantém os registros anteriores e não converte o fluxo em paginação offset fictícia

#### Scenario: Coluna secundária no mobile
- **WHEN** uma tabela é exibida em viewport móvel
- **THEN** identidade, estado e ação principal permanecem disponíveis e colunas secundárias são ocultadas ou transferidas ao detalhe

#### Scenario: Controle sem função real
- **WHEN** uma lista não possui ação em massa ou preferência de colunas funcional
- **THEN** o sistema não exibe seleção em massa ou seletor de colunas apenas para imitar o template

#### Scenario: Seleção em Notas com export
- **WHEN** o usuário autorizado pode exportar a partir do catálogo de Notas
- **THEN** a seleção em massa é permitida e cada seleção leva a exportação de filtro ou de chaves, nunca a um estado sem ação

### Requirement: Catálogo de Notas em mestre–detalhe responsivo
O sistema SHALL apresentar Notas como posto operacional: tabela densa (ou painel mestre equivalente) com detalhe em painel adjacente redimensionável, drawer ou slideover, em viewport `lg+` e slideover abaixo de `lg`, mantendo `/notes/:accessKey` como rota canônica da seleção e as tabs Por documento / Por empresa quando implementadas.

#### Scenario: Seleção de nota em desktop
- **WHEN** o usuário seleciona uma nota em viewport `lg` ou maior
- **THEN** a rota muda para `/notes/:accessKey`, a linha fica selecionada e o detalhe aparece no painel adjacente ou drawer sem descartar filtros da URL

#### Scenario: Seleção de nota no mobile
- **WHEN** o usuário seleciona uma nota em viewport menor que `lg`
- **THEN** a rota muda para `/notes/:accessKey` e o detalhe abre em slideover que pode ser fechado por teclado ou controle visível

#### Scenario: Catálogo sem seleção no desktop
- **WHEN** nenhuma nota está selecionada em viewport `lg` ou maior
- **THEN** a área de detalhe (se visível) apresenta estado neutro orientando a selecionar uma nota, sem espaço morto enganoso

#### Scenario: Navegação pelo teclado
- **WHEN** o foco está no catálogo e o usuário usa os comandos de seleção documentados
- **THEN** a seleção avança ou recua entre registros visíveis e o item selecionado permanece visível

#### Scenario: Abertura direta do detalhe
- **WHEN** o usuário abre diretamente `/notes/:accessKey`
- **THEN** o sistema carrega o detalhe autorizado e disponibiliza retorno ao catálogo sem revelar existência de nota de outro escritório
