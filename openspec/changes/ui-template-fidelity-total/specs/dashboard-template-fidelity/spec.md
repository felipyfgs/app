## ADDED Requirements

### Requirement: Cobertura total do inventário atual
O sistema MUST manter uma matriz que liste cada arquivo `frontend/app/pages/**/*.vue`, os layouts e todo componente que influencie chrome. Cada linha MUST registrar rota/caso, pai Nuxt, layout/auth, bundle canônico único, fontes exatas no template `0f30c09`, cadeia de componentes, fixture, evidências por critério e aceite final.

#### Scenario: Página sem entrada
- **WHEN** existe uma página no filesystem sem linha correspondente
- **THEN** o gate falha e a change permanece incompleta

#### Scenario: Página filha ou proxy
- **WHEN** uma página delega conteúdo a pai Nuxt ou componente de domínio
- **THEN** a matriz registra a cadeia completa, a URL própria recebe smoke e nenhum componente delegado pode esconder outro arquétipo

#### Scenario: Auth ou redirect
- **WHEN** a página não possui superfície dashboard porque é auth ou redirect
- **THEN** somente os critérios de dashboard impossíveis recebem `N/A` justificado e todos os demais testes permanecem obrigatórios

### Requirement: Um bundle canônico por superfície
Cada superfície autenticada renderizável SHALL escolher exatamente um bundle `SHELL`, `HOME`, `LIST`, `MASTER_DETAIL`, `SETTINGS_FORM` ou `SETTINGS_CARD_LIST` e MUST copiar diretamente os arquivos-fonte desse bundle. O sistema MUST NOT formar arquétipos híbridos pela soma de bundles diferentes.

#### Scenario: Lista com indicadores
- **WHEN** uma rota operacional é classificada como `LIST`
- **THEN** ela usa a ordem integral de `customers.vue` e não injeta `HomeStats`, KPI strip ou toolbar de outro bundle no chrome

#### Scenario: Lista com detalhe persistente
- **WHEN** a seleção exige detalhe contextual permanente
- **THEN** a rota inteira usa `MASTER_DETAIL`, com painéis adjacentes no desktop e slideover no mobile, em vez de combinar tabela Customers com overlay próprio

#### Scenario: Settings com lista curta
- **WHEN** uma seção Settings apresenta membros, departamentos ou itens curtos
- **THEN** ela usa `SETTINGS_CARD_LIST`, sem inserir um segundo panel `LIST` dentro do pai Settings

### Requirement: Zero wrapper de chrome paralelo
O frontend MUST renderizar o chrome canônico diretamente nas páginas ou no pai Nuxt canônico e MUST NOT usar wrappers de apresentação que escondam panel, navbar, toolbar, tabela, footer, split ou responsividade.

#### Scenario: Import proibido
- **WHEN** uma página importa `components/shell/{ListShell,StickyTableFilters,InfiniteTableLoader,TableFooter,KpiStrip}.vue`, `components/monitoring/{ModuleTable,ModuleToolbar,KpiStrip}.vue`, `components/docs/Workspace.vue` ou equivalente de chrome
- **THEN** o gate estrutural falha

#### Scenario: Componente-folha permitido
- **WHEN** um componente compartilhado contém somente conteúdo/dados de domínio e não define chrome do arquétipo
- **THEN** ele pode permanecer, desde que sua posição corresponda ao slot canônico e sua cadeia esteja registrada

#### Scenario: Pai Nuxt
- **WHEN** um pai existe apenas para nesting
- **THEN** ele é pass-through sem chrome ou implementa integralmente um único bundle canônico

### Requirement: Precedência da migração integral
Esta change MUST substituir decisões visuais/interacionais conflitantes de changes anteriores, inclusive `padronizar-tabelas-carregamento-incremental`, e MUST NOT aceitar seus deltas como autorização para infinite scroll, sentinel, sticky/virtualização ou footer ausente.

#### Scenario: Regra antiga conflita com o template
- **WHEN** um artefato histórico exige auto-load ou remove `UPagination`
- **THEN** a regra desta change prevalece, o artefato antigo não é sincronizado e a UI volta à composição literal do template

### Requirement: Status global derivado da matriz
O sistema MUST definir “100%” como todos os critérios aplicáveis de todas as linhas da matriz em `PASS`; 51/51 nomes, cobertura de código, gate lexical ou screenshot isolado MUST NOT ser interpretado como aceite total.

#### Scenario: Uma evidência pendente
- **WHEN** qualquer linha possui critério obrigatório `PENDING` ou `FAIL`
- **THEN** o status global permanece `PENDING` ou `FAIL`

#### Scenario: Critério não aplicável
- **WHEN** um critério realmente não se aplica
- **THEN** a linha usa `N/A` com justificativa verificável e mantém obrigatórios os demais critérios

## MODIFIED Requirements

### Requirement: Derivação literal e rastreável do código de referência
O sistema SHALL derivar cada superfície autenticada pela cópia direta do arquivo canônico no commit fixado e SHALL alterar somente textos, rotas, dados/API, permissões, tenancy, estados e segurança. Chrome, DOM, slots, ordem, classes críticas, geometria, breakpoints e interação MUST permanecer equivalentes à fonte sem exceção estrutural.

#### Scenario: Migração de página
- **WHEN** uma página entra na refatoração
- **THEN** o arquivo-fonte exato é aberto, copiado e registrado antes de conectar dados reais

#### Scenario: Melhoria local existente
- **WHEN** o código atual possui sticky, infinite scroll, virtualização, wrapper ou composição ausente na fonte
- **THEN** o comportamento é removido mesmo que tenha sido aprovado por change anterior

### Requirement: Matriz integral e versionada de páginas
O sistema SHALL manter uma matriz completa em relação ao filesystem no momento do aceite, com bundle primário único e fontes exatas. Campos vagos, wildcard, origem híbrida, `parent-or-missing`, “se aplicável” ou exceção estrutural MUST falhar a validação.

#### Scenario: Inventário muda
- **WHEN** uma página é criada, removida ou movida durante a change
- **THEN** denominador e matriz são recalculados no mesmo patch e a rota preservada é testada

#### Scenario: Origem híbrida
- **WHEN** uma linha combina bundles como `LIST+HOME`, `LIST+MASTER_DETAIL` ou `SETTINGS+LIST`
- **THEN** o gate falha até a superfície escolher um arquétipo único

### Requirement: Paridade estrutural por arquétipo de tela
O sistema SHALL implementar `HOME` a partir de `index.vue`, `LIST` a partir de `customers.vue`, `MASTER_DETAIL` a partir de `inbox.vue`, e `SETTINGS_*` a partir de `settings.vue` e suas subpáginas, conservando a composição integral de cada origem.

#### Scenario: Lista administrativa
- **WHEN** o usuário abre uma lista operacional
- **THEN** navbar, utilitários no body, tabela, footer, contagem e `UPagination` seguem `customers.vue`, com dados paginados no servidor

#### Scenario: Documentos
- **WHEN** o usuário abre `/docs`, `/docs/catalog` ou `/docs/:accessKey`
- **THEN** lista, seleção, segundo painel desktop e slideover mobile seguem `inbox.vue` e `inbox/*`

#### Scenario: Dashboard
- **WHEN** o usuário abre Home ou dashboard de monitoramento
- **THEN** navbar, toolbar funcional e body Stats → Chart → Sales seguem `index.vue` e `home/*`, sem blocos de chrome paralelos

#### Scenario: Detalhe e configuração
- **WHEN** o usuário abre Settings, detalhe de cliente ou detalhe de processo
- **THEN** toolbar de seções, largura `max-w-2xl`, cards e formulários seguem `settings.vue` e `settings/*`

### Requirement: Composição reconhecível após abstrações locais
Abstrações locais MAY encapsular apenas dados ou conteúdo-folha e MUST NOT encapsular ou reimplementar panel, navbar, toolbar, split, tabela completa, footer ou breakpoints do arquétipo.

#### Scenario: Composable de dados
- **WHEN** uma página usa composable para paginação, filtros ou cancelamento
- **THEN** o composable não renderiza UI e a página conserva o markup literal da fonte

#### Scenario: Wrapper visual
- **WHEN** um componente monta um painel inteiro por props ou slots
- **THEN** ele é rejeitado e o markup volta à página canônica

### Requirement: Componentes compartilhados preservam a forma canônica
Componentes compartilhados SHALL ser folhas de domínio posicionadas em slots existentes e MUST NOT criar uma segunda hierarquia visual. Componentes canônicos copiados do próprio template MAY ser compartilhados quando preservarem o arquivo-fonte sem API genérica adicional.

#### Scenario: Componente Home copiado
- **WHEN** `HomeStats` é adaptado para métricas reais
- **THEN** sua árvore visual permanece a da referência e somente labels/valores mudam

#### Scenario: ModuleTable existente
- **WHEN** uma carteira ainda depende de `components/monitoring/ModuleTable.vue`
- **THEN** a rota permanece `PENDING` até expandir diretamente o bundle escolhido e remover o wrapper

### Requirement: Presets tabulares rastreáveis e cobertura integral
Cada `UTable` administrativa MUST declarar no consumidor o `:ui` literal de `customers.vue`, e cada tabela compacta de dashboard MUST declarar a forma de `HomeSales.vue`. Presets de apresentação que alterem densidade, altura, sticky, virtualização ou layout MUST NOT ser usados.

#### Scenario: Tabela administrativa
- **WHEN** uma rota `LIST` renderiza dados
- **THEN** `base`, `thead`, `tbody`, `th`, `td` e `separator` correspondem literalmente à fonte e o footer canônico está presente

#### Scenario: Classe de viewport
- **WHEN** o gate encontra `100dvh`, max-height de feed ou sticky custom associado à tabela
- **THEN** a rota falha a fidelidade

### Requirement: Fidelidade visual mensurável
O sistema MUST validar cada caso visual da matriz em `1440×900` e `390×844`, além de operabilidade e ausência de overflow obrigatório em `360×800`, com fixtures determinísticas e `maxDiffPixelRatio: 0.005`.

#### Scenario: Comparação por zonas
- **WHEN** uma screenshot é comparada ao baseline aprovado
- **THEN** shell, header, toolbar, conteúdo, footer e overlays são avaliados sem mascarar geometria

#### Scenario: Tolerância maior
- **WHEN** um teste solicita tolerância acima do padrão
- **THEN** o aceite falha até a diferença estrutural ser corrigida ou a região estritamente dinâmica ser mascarada

### Requirement: Aceite completo por rota
Uma página SHALL ser fiel somente após passar inventário, estrutura renderizada, fluxo funcional, estados, papéis/tenancy, acessibilidade, visual desktop/mobile, overflow, segurança e revisão de dados sanitizados.

#### Scenario: Gate parcial verde
- **WHEN** a rota passa no gate lexical ou screenshot mas falha em outro critério
- **THEN** ela permanece não concluída

#### Scenario: Aceite final
- **WHEN** todas as linhas possuem todos os critérios aplicáveis em `PASS` e `N/A` justificado nos demais
- **THEN** `evidence/ACCEPTANCE.md` pode declarar `FINAL: PASS` e a change pode ser sincronizada/arquivada
