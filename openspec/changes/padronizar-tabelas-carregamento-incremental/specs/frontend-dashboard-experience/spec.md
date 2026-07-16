> **SUPERSEDED:** este delta não deve ser sincronizado. `ui-template-fidelity-total` substitui carregamento incremental por fidelidade integral ao template.

## MODIFIED Requirements

### Requirement: Hierarquia uniforme de ações e contexto
O sistema SHALL posicionar ação primária na navbar, filtros globais, filtros de tabela ou subnavegação na toolbar fixa do header, utilidades tabulares junto desses filtros e ações secundárias por registro no fim da linha. O corpo rolável MUST NOT fazer a busca, filtros ou ações globais desaparecerem durante a análise de uma lista grande.

#### Scenario: Tela de lista com criação
- **WHEN** o usuário autorizado abre Clientes ou Exportações
- **THEN** a navbar apresenta no máximo uma ação primária de criação e a busca, filtros e utilidades da lista aparecem na faixa fixa abaixo da navbar

#### Scenario: Rolagem de lista grande
- **WHEN** o usuário percorre linhas suficientes para rolar o corpo da lista
- **THEN** navbar, filtros e ações globais continuam disponíveis e o cabeçalho das colunas permanece visível dentro do container tabular

#### Scenario: Lista móvel com indicadores antes da tabela
- **WHEN** uma lista móvel apresenta cards de indicadores antes dos filtros e da tabela
- **THEN** os filtros permanecem depois dos cards no fluxo do body, ambos saem antes de o cabeçalho tabular fixar e a tabela não prende linhas enquanto os cards ainda estão visíveis

#### Scenario: Tela com subnavegação
- **WHEN** o usuário abre o detalhe de Cliente
- **THEN** a toolbar apresenta as seções disponíveis e indica visualmente e semanticamente a seção ativa

#### Scenario: Ação destrutiva
- **WHEN** o usuário inicia uma ação destrutiva ou irreversível
- **THEN** um modal identifica alvo e consequência e exige confirmação em ação de cor semântica `error`

### Requirement: Tabelas administrativas consistentes e server-side
O sistema SHALL apresentar Clientes, Notas, Exportações, Sincronizações e demais listas operacionais grandes com cabeçalho, bordas, densidade e ações consistentes com o template, preservando filtro, ordenação e recorte no servidor. Essas listas MUST carregar blocos adicionais automaticamente conforme a rolagem, sem `UPagination`, footer persistente, botão `Carregar mais` ou mensagem “Fim da lista”. Em Notas, chips de situação MUST usar o vocabulário NFS-e Nacional após o alinhamento de status.

#### Scenario: Lista de clientes incremental
- **WHEN** o usuário se aproxima do fim das linhas carregadas de Clientes
- **THEN** o sistema solicita o próximo bloco à API, anexa registros únicos, mantém busca/filters/sorting e não traz a coleção inteira nem pagina apenas os registros locais

#### Scenario: Lista incremental por cursor
- **WHEN** o usuário carrega mais Notas, Saúde ou Sincronizações
- **THEN** o sistema usa o cursor da API, mantém os filtros da consulta e não reaproveita cursor de outra consulta ou escritório

#### Scenario: Rolagem por teclado e carga transitória
- **WHEN** o usuário se aproxima do fim por mouse, touch ou teclado
- **THEN** o próximo bloco é solicitado automaticamente e um status transitório anuncia a carga sem criar footer permanente

#### Scenario: Alteração de filtro ou ordenação
- **WHEN** o usuário muda busca, filtro ou coluna/direção de ordenação
- **THEN** o sistema cancela ou ignora respostas obsoletas, limpa blocos e seleção incompatíveis, volta ao início e consulta o servidor com ordenação global determinística

#### Scenario: Coluna secundária no mobile
- **WHEN** uma tabela é exibida em viewport móvel
- **THEN** identidade, estado e ação principal permanecem disponíveis e colunas secundárias são ocultadas ou transferidas ao detalhe

#### Scenario: Controle sem função real
- **WHEN** uma lista não possui ação em massa, preferência de colunas ou ordenação global funcional
- **THEN** o sistema não exibe checkbox, seletor ou affordance de sorting apenas para imitar o template

#### Scenario: Seleção em Notas com export
- **WHEN** o usuário autorizado pode exportar a partir do catálogo de Notas
- **THEN** a seleção usa chaves estáveis das linhas carregadas e cada seleção leva à exportação de filtro ou de chaves, nunca a um estado sem ação

#### Scenario: Lista virtualizada
- **WHEN** uma tabela classificada como grande acumula muitos registros de altura previsível
- **THEN** o `UTable` limita os nós renderizados por virtualização com overscan, preservando sticky, teclado, ações e carregamento incremental

#### Scenario: Linhas móveis de altura variável
- **WHEN** identidade, identificador, estado e ação são compostos em uma linha móvel de altura variável
- **THEN** a tabela não virtualiza essas linhas, não cria rolagem interna e o cabeçalho fixo cobre de forma opaca todo o topo útil sem deixar texto ou ações passarem por baixo da sua borda superior

#### Scenario: Troca de escritório durante carga
- **WHEN** o usuário troca explicitamente de escritório enquanto existe requisição ou linhas carregadas
- **THEN** a tabela descarta estado e respostas do tenant anterior antes de apresentar dados do novo escritório e nunca envia `office_id` livre pelo cliente
