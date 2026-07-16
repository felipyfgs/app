## ADDED Requirements

### Requirement: Anatomia canônica direta em toda rota autenticada
Toda rota autenticada visual MUST renderizar diretamente o bundle canônico escolhido, com `UDashboardPanel`, navbar, collapse, toolbar/body e componentes subsequentes na ordem do arquivo-fonte. O sistema MUST NOT depender de casca de chrome compartilhada ou de combinação entre arquétipos.

#### Scenario: Página de lista
- **WHEN** o usuário abre Clientes, Exportações, Sincronizações, Health, Closing, imports, processos ou carteira classificada como `LIST`
- **THEN** a página segue diretamente navbar → body com utilitários → `UTable` → footer/contagem/`UPagination` de `customers.vue`

#### Scenario: Página settings
- **WHEN** o usuário abre Configurações, detalhe de cliente ou detalhe de processo
- **THEN** toolbar de seções, container `lg:max-w-2xl`, cards e formulários seguem diretamente `settings.vue` e sua subpágina canônica

#### Scenario: Página mestre–detalhe
- **WHEN** o usuário abre Documentos, Work ou Caixa Postal classificada como `MASTER_DETAIL`
- **THEN** o primeiro painel, segundo painel desktop e slideover mobile seguem `inbox.vue` sem overlay desktop alternativo

### Requirement: Chrome antigo removido do runtime
O frontend MUST remover imports e uso dos wrappers e comportamentos de chrome substituídos, incluindo shell genérica, workspace de documentos, tabela fiscal genérica, filtros teleportados, infinite loader, footer encapsulado, KPI strips e presets de viewport.

#### Scenario: Gate de imports
- **WHEN** uma `page.vue` ainda importa um componente proibido pela matriz
- **THEN** o build de fidelidade falha com arquivo, import e bundle esperado

#### Scenario: Lógica de domínio reaproveitada
- **WHEN** um wrapper antigo contém chamada de API ou transformação necessária
- **THEN** apenas a lógica sem apresentação é extraída para composable tipado e o markup é reconstruído diretamente da referência

### Requirement: Auth possui contrato separado e explícito
Login e desafio/setup 2FA MUST permanecer fora de `UDashboardGroup`, usando layout Nuxt UI próprio, e MUST ser marcados como `template_dashboard=N/A` porque o template fixado não fornece autenticação.

#### Scenario: Login
- **WHEN** um visitante abre `/login`
- **THEN** não existe sidebar do dashboard e o formulário é acessível, responsivo e sanitizado

#### Scenario: Inventário total
- **WHEN** o aceite calcula a cobertura das páginas
- **THEN** auth continua no denominador funcional de 100%, mas não finge possuir origem visual inexistente no template

## MODIFIED Requirements

### Requirement: Hierarquia uniforme de ações e contexto
O sistema SHALL posicionar ações, filtros, tabs e utilitários exatamente onde o bundle canônico os posiciona. Uma lista `customers.vue` mantém utilitários no body; Home e Settings usam `UDashboardToolbar` apenas nos pontos existentes na referência. Sticky custom, teleport de filtros e segunda sidebar MUST NOT ser usados.

#### Scenario: Lista com criação
- **WHEN** um usuário autorizado abre uma lista com ação primária
- **THEN** a ação fica no `#right` da navbar e busca/filtros/colunas permanecem na faixa do body como em `customers.vue`

#### Scenario: Settings com seções
- **WHEN** o usuário navega no detalhe de cliente, processo ou escritório
- **THEN** a subnavegação fica na toolbar horizontal de `settings.vue` e o corpo mantém a largura canônica

#### Scenario: Ação destrutiva
- **WHEN** o usuário inicia exclusão ou mutação irreversível
- **THEN** o modal copia `DeleteModal.vue`, identifica alvo/consequência e usa ação `error`

### Requirement: Tabelas administrativas consistentes e server-side
Toda lista classificada como `LIST` SHALL copiar `customers.vue`, incluindo `UTable`, `:ui` literal, contagem e `UPagination`. Busca, filtro, ordenação, total e página MUST vir do servidor no escopo do escritório ativo; o frontend MUST NOT usar infinite scroll, sentinel, `Carregar mais`, virtualização, sticky custom ou paginação sobre coleção integral local.

#### Scenario: Mudança de página
- **WHEN** o usuário aciona `UPagination`
- **THEN** o frontend solicita somente a página correspondente, atualiza linhas e contagem e mantém busca/filtros/ordenação server-side

#### Scenario: Mudança de consulta
- **WHEN** busca, filtro, ordenação ou escritório muda
- **THEN** a página volta a 1, respostas obsoletas são descartadas e nenhuma linha do tenant anterior permanece

#### Scenario: API cursor-only de leitura
- **WHEN** uma lista administrativa depende de endpoint que só retorna `next_cursor`
- **THEN** a rota permanece `PENDING` até existir adaptação read-only com metadados compatíveis com `UPagination`, sem converter cursor fiscal NSU/nNF em offset

#### Scenario: Controle sem função real
- **WHEN** não existe ação em massa, preferência de coluna ou sorting global
- **THEN** o checkbox, seletor ou affordance correspondente não é exibido, preservando o espaço e a hierarquia restante do template

#### Scenario: Erro com dados anteriores
- **WHEN** a troca de página ou refresh falha após dados válidos
- **THEN** as linhas permanecem, o erro é anunciado e retry não duplica registros

### Requirement: Paginação server-side em toda lista potencialmente não limitada
Clientes, Exportações, Documentos quando classificados como `LIST` e qualquer universo tabular crescente SHALL expor `page`, `per_page`, `total` e ordenação estável, ou metadados equivalentes capazes de alimentar o `UPagination` sem consultas intermediárias ocultas. O sistema MUST NOT baixar todas as páginas para filtrar, ordenar ou paginar no navegador.

#### Scenario: Histórico extenso
- **WHEN** existem mais registros que o tamanho da página
- **THEN** o footer mostra total e páginas disponíveis e cada navegação consulta somente o recorte solicitado

#### Scenario: Ordenação com empate
- **WHEN** várias linhas compartilham o campo ordenado
- **THEN** o backend usa desempate estável e escopo tenant antes da paginação

### Requirement: Cursor sem simulação de offset
Cursores fiscais e de integração MUST permanecer semanticamente distintos da paginação visual. Uma API de consulta que não consiga oferecer salto paginado seguro MUST ser adaptada no backend/read model antes de usar `UPagination`; o frontend MUST NOT percorrer cursores ocultamente para simular uma página aleatória.

#### Scenario: Endpoint ainda incompatível
- **WHEN** não há metadados ou consulta direta para a página solicitada
- **THEN** a migração dessa rota não recebe `PASS` e não recorre a infinite scroll como fallback

#### Scenario: Cursor fiscal
- **WHEN** a UI muda página, filtro ou ordenação
- **THEN** nenhum cursor NSU/nNF operacional é alterado

### Requirement: Catálogo de Notas em mestre–detalhe responsivo
O sistema SHALL apresentar `/docs`, `/docs/catalog` e `/docs/:accessKey` pela composição integral de `inbox.vue`, com lista no primeiro painel, detalhe no segundo painel desktop e `USlideover` no mobile. `/notes/*` MUST ser apenas compatibilidade de rota enquanto existir.

#### Scenario: Seleção no desktop
- **WHEN** o usuário seleciona um documento em viewport `lg` ou maior
- **THEN** `/docs/:accessKey` identifica a seleção e o detalhe aparece no painel adjacente, sem modal ou slideover desktop

#### Scenario: Seleção no mobile
- **WHEN** o usuário seleciona um documento abaixo de `lg`
- **THEN** o detalhe abre no `USlideover` canônico, com foco contido e retorno ao item selecionado

#### Scenario: Nenhuma seleção
- **WHEN** `/docs` ou `/docs/catalog` abre sem access key
- **THEN** o segundo painel mostra o empty state central de Inbox e a lista permanece utilizável

#### Scenario: Redirect legado
- **WHEN** `/notes` ou `/notes/:accessKey` é acessado
- **THEN** a navegação vai para a rota equivalente em `/docs` sem renderizar chrome intermediário

### Requirement: Detalhe de nota sem abandonar o posto
O detalhe de documento SHALL seguir `InboxMail.vue`, conservar o contexto da lista e MUST usar painel adjacente em desktop e slideover em mobile, sem `DocsWorkspace` ou modal custom.

#### Scenario: Fechar detalhe mobile
- **WHEN** o usuário fecha o slideover
- **THEN** o foco retorna ao item correspondente e filtros/posição da lista são preservados

#### Scenario: Deep-link autorizado
- **WHEN** `/docs/:accessKey` é aberto diretamente
- **THEN** somente o documento do escritório ativo é carregado e o primeiro painel continua disponível conforme o arquétipo

### Requirement: Superfície Documentos em /docs
O destino Documentos SHALL manter `/docs` como visão por empresa, `/docs/catalog` como visão por documento e `/docs/:accessKey` como seleção canônica dentro do bundle `MASTER_DETAIL`, diferenciando as visões por dados/filtros e não por chrome distinto.

#### Scenario: Alternar visão
- **WHEN** o usuário alterna Por empresa e Catálogo
- **THEN** a rota muda entre `/docs` e `/docs/catalog`, mas a composição Inbox permanece idêntica

#### Scenario: CT-e legado
- **WHEN** `/settings/cte` é acessado
- **THEN** a rota redireciona para `/docs/catalog` com o filtro CT-e sem criar uma superfície Settings paralela

### Requirement: Anatomia e hierarquia uniformes em todas as páginas
Cada página autenticada MUST usar diretamente um único bundle da matriz. Conteúdo operacional MAY substituir dados e textos, mas MUST NOT acrescentar casca, toolbar, KPI strip, overlay desktop ou largura fora da origem.

#### Scenario: Monitoring com detalhe
- **WHEN** uma carteira exige seleção e detalhe persistente
- **THEN** ela usa `MASTER_DETAIL` integral em vez de `LIST` com slideover desktop

#### Scenario: Dashboard operacional
- **WHEN** a Home ou Monitoring Home apresenta KPIs
- **THEN** os KPIs ocupam `HomeStats` e o restante segue Chart/Sales, sem blocos de chrome adicionais

### Requirement: Estados assíncronos preservam trabalho válido
Listas e painéis MUST distinguir loading inicial, refreshing, vazio, erro sem dados e erro com dados preservados usando os pontos de feedback do bundle canônico, sem duplicar empty state ou criar footer/sentinel alternativo.

#### Scenario: Vazio
- **WHEN** a API retorna zero itens sem erro
- **THEN** existe um único empty acessível em pt-BR

#### Scenario: Resposta obsoleta
- **WHEN** filtro, página ou escritório muda durante uma requisição
- **THEN** a resposta antiga é ignorada e nunca mistura tenants

## REMOVED Requirements

### Requirement: Notas como posto operacional em tabela densa
**Reason**: A decisão de migração integral escolhe `inbox.vue` como único arquétipo de Documentos; tabela densa com modal cria composição híbrida fora da referência.

**Migration**: Usar `Catálogo de Notas em mestre–detalhe responsivo`, preservando `/docs` e `/docs/:accessKey` como rotas de domínio.
