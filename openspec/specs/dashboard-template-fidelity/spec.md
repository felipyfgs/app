# Dashboard Template Fidelity

## Purpose

Fidelidade literal e auditável ao template Nuxt UI Dashboard fixado em .reference/nuxt-dashboard-template (commit 0f30c09).

## Requirements

### Requirement: Derivação literal e rastreável do código de referência
O sistema MUST derivar as telas autenticadas por cópia direta do código fixado em `.reference/nuxt-dashboard-template` e SHALL manter uma matriz que vincule cada arquivo destino ao arquivo ou bloco exato usado como origem.

#### Scenario: Componente equivalente disponível
- **WHEN** uma área do produto possui composição equivalente no template
- **THEN** a implementação começa pelo código copiado e preserva estrutura, ordem, componentes Nuxt UI, slots, props visuais, classes, dimensões, hierarquia, densidade e interação da referência

#### Scenario: Divergência necessária
- **WHEN** uma regra funcional, de segurança, tenancy, autorização ou contrato server-side exige comportamento diferente
- **THEN** a matriz registra a linha ou bloco alterado, a justificativa e a evidência de que manter literalmente o código seria incorreto ou inseguro

#### Scenario: Divergência não registrada
- **WHEN** a revisão encontra diferença visual ou interacional sem justificativa registrada
- **THEN** a diferença é tratada como defeito e impede o aceite da rota

#### Scenario: Reimplementação equivalente
- **WHEN** o produto usa wrapper, markup, slots, classes ou composição apenas equivalentes ao template, mas não derivados diretamente dele
- **THEN** a rota é reprovada e deve ser refeita a partir da cópia do código de referência

### Requirement: Shell fiel sem troca arbitrária de escritório
O sistema MUST reproduzir o shell do template com `UDashboardGroup`, sidebar recolhível e redimensionável, busca global, navegação vertical, rodapé do usuário, command palette e slideover global, sem permitir seleção de escritório fora da associação autenticada.

#### Scenario: Identidade do escritório
- **WHEN** o usuário visualiza o cabeçalho da sidebar expandida ou recolhida
- **THEN** a identidade do escritório mantém dimensões, alinhamento e tratamento visual do seletor de equipe da referência, mas não oferece troca de tenant

#### Scenario: Sidebar móvel
- **WHEN** um destino é selecionado em viewport móvel
- **THEN** a sidebar fecha e o painel de destino ocupa a área principal como no template

#### Scenario: Navegação por perfil
- **WHEN** sidebar, command palette ou ações rápidas são abertas
- **THEN** todos os destinos são derivados das mesmas permissões tipadas e nenhuma ação proibida é apresentada

### Requirement: Paridade estrutural por arquétipo de tela
O sistema SHALL implementar cada rota autenticada copiando o arquétipo correspondente do template: dashboard, lista administrativa, mestre–detalhe ou settings, e alterando apenas conteúdo e integrações necessárias.

#### Scenario: Dashboard operacional
- **WHEN** o usuário abre o dashboard
- **THEN** navbar, ações compactas, toolbar, grade de indicadores e conteúdo subsequente seguem a composição de `pages/index.vue` e `HomeStats.vue`, usando somente métricas reais

#### Scenario: Lista administrativa
- **WHEN** o usuário abre Clientes, Exportações ou Sincronizações
- **THEN** ação primária, faixa utilitária, tabela, ações de linha, estados e paginação seguem a composição de `customers.vue` sem substituir paginação server-side por paginação local

#### Scenario: Catálogo de notas
- **WHEN** o usuário abre Notas em desktop ou mobile
- **THEN** a experiência segue o mestre–detalhe de Inbox, usando painéis adjacentes no desktop e slideover no mobile, com rota canônica e dados fiscais sanitizados

#### Scenario: Detalhe e administração
- **WHEN** o usuário abre o detalhe de Cliente ou Administração
- **THEN** navbar, toolbar de seções, largura do conteúdo e cards seguem o arquétipo Settings da referência

### Requirement: Fidelidade visual mensurável
O sistema MUST manter idênticos tipografia, escala, espaçamento, largura, densidade, bordas, cantos, superfícies, props visuais, breakpoints e cores da referência e MUST validar elementos críticos por comparação visual determinística.

#### Scenario: Comparação por zonas
- **WHEN** uma screenshot é comparada ao baseline
- **THEN** shell, header, toolbar, conteúdo e overlays são avaliados separadamente e nenhuma zona crítica excede a tolerância documentada

#### Scenario: Conteúdo dinâmico
- **WHEN** valores, datas ou textos variáveis impedem comparação estável
- **THEN** o teste usa dados sintéticos determinísticos ou mascara apenas a região dinâmica sem ignorar sua geometria

#### Scenario: Alteração visual intencional
- **WHEN** uma mudança aprovada altera o baseline
- **THEN** a atualização inclui justificativa, diff revisável e atualização da matriz de paridade

### Requirement: Interações e estados equivalentes
O sistema SHALL manter posição e prioridade de ações, abertura e fechamento de overlays, loading, feedback, foco, atalhos e navegação por teclado equivalentes ao padrão de referência.

#### Scenario: Ação primária e ações rápidas
- **WHEN** uma tela oferece criação ou outra ação primária
- **THEN** a ação ocupa o mesmo nível hierárquico do template e ações adicionais aparecem em dropdown ou faixa apropriada, condicionadas ao perfil

#### Scenario: Estado assíncrono
- **WHEN** uma leitura está carregando, vazia, falhou ou preserva dados anteriores após erro
- **THEN** a tela apresenta estado distinto e acessível, sem confundir erro com ausência de dados

#### Scenario: Modal ou slideover
- **WHEN** o usuário abre e fecha um overlay
- **THEN** o foco fica contido, retorna ao acionador e o comportamento de teclado segue o componente correspondente do template

### Requirement: Paridade responsiva e acessível
O sistema MUST manter a experiência utilizável e fiel em `1440×900`, `390×844` e largura de `360 px`, sem depender somente de cor ou mouse.

#### Scenario: Desktop
- **WHEN** a aplicação é exibida em `1440×900`
- **THEN** sidebar, painéis, tabelas, toolbars e overlays preservam proporções e alinhamentos da referência

#### Scenario: Mobile
- **WHEN** a aplicação é exibida em `390×844`
- **THEN** navegação, ações principais, identidade, estados e detalhes permanecem acessíveis na composição móvel correspondente

#### Scenario: Largura mínima
- **WHEN** um fluxo principal é executado em largura de `360 px`
- **THEN** o documento não apresenta rolagem horizontal e nenhuma ação obrigatória fica inacessível

#### Scenario: Operação por teclado
- **WHEN** o usuário navega sem mouse
- **THEN** foco visível, ordem de tabulação, menus, tabelas selecionáveis e overlays permitem concluir o fluxo coberto

### Requirement: Evidência visual sanitizada e reproduzível
O sistema MUST gerar evidências de fidelidade com fixtures sintéticas e MUST impedir que screenshots, traces, snapshots ou relatórios contenham material fiscal ou credencial sensível.

#### Scenario: Execução limpa
- **WHEN** a suíte visual é executada em ambiente limpo com versões fixadas
- **THEN** as mesmas rotas, estados, viewports, fontes e dados determinísticos produzem resultados reproduzíveis

#### Scenario: Varredura de artefatos
- **WHEN** screenshots, traces e relatórios são gerados
- **THEN** uma verificação rejeita PFX, senha, chave privada, PEM, XML fiscal, cookies, tokens, `vault_object_id` e resposta ADN bruta

#### Scenario: Dependência de produção
- **WHEN** o frontend é compilado para produção
- **THEN** fixtures, interceptadores e baselines não criam rota mock, dependência de runtime ou processo Node adicional

### Requirement: Aceite completo por rota
O sistema SHALL considerar uma rota fiel somente após cumprir a matriz estrutural, os testes funcionais e acessíveis, as comparações visuais aplicáveis e a revisão de divergências autorizadas.

#### Scenario: Critério incompleto
- **WHEN** uma rota passa no screenshot mas falha em comportamento, acessibilidade, segurança ou responsividade
- **THEN** a rota permanece não concluída

#### Scenario: Aceite final
- **WHEN** todas as rotas passam nas camadas estrutural, visual, interacional e responsiva
- **THEN** o relatório final lista evidências, exceções aprovadas e comandos reproduzíveis de validação

### Requirement: Matriz integral e versionada de páginas
O sistema SHALL manter uma matriz de paridade que cubra cada arquivo de página autenticada, página de autenticação, página aninhada e redirecionamento legado, vinculando arquivo destino, rota, arquétipo, fonte exata no template, divergências autorizadas e evidências de aceite.

#### Scenario: Página existente entra na refatoração
- **WHEN** uma página listada em `frontend/app/pages/` é preparada para refatoração
- **THEN** a matriz identifica seu arquétipo e arquivo-fonte antes de qualquer alteração de markup

#### Scenario: Nova página surge durante a change
- **WHEN** outra change adiciona uma página enquanto esta refatoração está em andamento
- **THEN** a nova página entra na matriz e recebe os mesmos gates antes de ser considerada concluída

#### Scenario: Redirecionamento legado
- **WHEN** uma rota existe somente para compatibilidade
- **THEN** a matriz a classifica como redirecionamento, testa destino e ausência de chrome duplicado e não exige uma tela visual artificial

### Requirement: Aprendizado externo subordinado ao template
O sistema MAY incorporar de referências externas somente padrões de hierarquia da informação, densidade, contexto, filtros, totalizações, agenda e progresso que possam ser compostos com os arquétipos do template, e MUST NOT copiar identidade visual, chrome, iconografia, paleta ou composição que substitua a referência fixada.

#### Scenario: Padrão externo compatível
- **WHEN** contexto de período ou totalização melhora uma lista sem alterar a árvore canônica de navbar, toolbar, body e footer
- **THEN** o padrão pode ser adaptado com componentes Nuxt UI e sua origem/justificativa é registrada

#### Scenario: Padrão externo conflitante
- **WHEN** uma referência externa propõe sidebar, ação, densidade ou navegação incompatível com o arquétipo do template ou com acessibilidade
- **THEN** o padrão é rejeitado e a tela preserva a composição do clone fixado

#### Scenario: Controle externo sem função real
- **WHEN** a referência apresenta filtro, gráfico ou ação sem contrato de dados real no produto
- **THEN** o sistema não reproduz o controle apenas por semelhança visual

### Requirement: Paridade por estados e interações principais
O aceite de cada rota SHALL cobrir sua composição preenchida e todos os estados aplicáveis de carregamento, vazio, falha inicial, falha de atualização com dados preservados, somente leitura, overlay principal e responsividade; uma screenshot do caminho feliz isolado MUST NOT encerrar a rota.

#### Scenario: Falha de atualização com dados válidos
- **WHEN** uma página já preenchida falha ao atualizar
- **THEN** a evidência comprova que os dados anteriores continuam visíveis e que a falha oferece nova tentativa

#### Scenario: Página com mutação por papel
- **WHEN** a mesma rota é aberta por papel autorizado e por `VIEWER`
- **THEN** o aceite comprova que leitura permanece disponível e ações proibidas não são apresentadas ao `VIEWER`

#### Scenario: Cobertura responsiva
- **WHEN** a rota possui conteúdo de dados, formulário ou detalhe
- **THEN** ela é validada em `1440×900`, `390×844` e sem overflow obrigatório em `360 px`

### Requirement: Composição reconhecível após abstrações locais
Componentes compartilhados criados durante a refatoração MUST permanecer pequenos, explícitos e rastreáveis e MUST NOT esconder o `UDashboardPanel`, a API do `UTable`, os slots principais do template ou as decisões de responsividade da página.

#### Scenario: Preset tabular compartilhado
- **WHEN** uma tabela reutiliza um preset visual
- **THEN** suas colunas, slots, paginação, seleção e ações continuam declarados de forma inspecionável no consumidor

#### Scenario: Wrapper universal proposto
- **WHEN** uma abstração exigiria configurar navbar, toolbar, body, tabela e overlays por um objeto genérico
- **THEN** a abstração é rejeitada em favor da cópia/adaptação explícita do arquétipo

### Requirement: Matriz de derivação das rotas de Monitoramento
O sistema MUST registrar cada rota `/monitoring` na matriz de fidelidade com o arquivo ou bloco exato do template fixado usado como origem e com justificativa para qualquer divergência funcional.

#### Scenario: Dashboard Fiscal
- **WHEN** `/monitoring` é implementado ou revisado
- **THEN** navbar, toolbar, faixa de indicadores e blocos operacionais são rastreados até `pages/index.vue` e `components/home/*`

#### Scenario: Carteira de módulo
- **WHEN** Simples/MEI, DCTFWeb/MIT, FGTS, Parcelamentos, SITFIS, Declarações ou Guias é implementado ou revisado
- **THEN** toolbar, filtros, tabela, ações de linha, estados e paginação são rastreados até `pages/customers.vue`, com HomeStats somente onde houver métricas reais

#### Scenario: Caixa Postal e detalhe do cliente
- **WHEN** Caixa Postal ou detalhe fiscal do cliente é implementado ou revisado
- **THEN** a matriz aponta respectivamente para Inbox/InboxMail e Settings/seções, incluindo a adaptação desktop/mobile

### Requirement: Referências visuais externas não substituem o template
As capturas fornecidas SHALL orientar somente densidade informacional, ordem de KPIs, filtros úteis e leitura de carteira. Estrutura, componentes, slots, ações, responsividade, tema e acessibilidade MUST continuar derivados do Nuxt UI Dashboard fixado.

#### Scenario: Barra lateral de ações da referência externa
- **WHEN** a captura externa posiciona ações em uma coluna lateral não existente no arquétipo oficial
- **THEN** a implementação usa navbar, toolbar e ações de linha do template em vez de copiar a coluna literalmente

#### Scenario: Identidade da referência externa
- **WHEN** cores, marca, tipografia ou ícones da captura diferem do design system do produto
- **THEN** o sistema preserva tokens semânticos Nuxt UI, identidade MonitorHub e ícones `i-lucide-*`

### Requirement: Componentes compartilhados preservam a forma canônica
Wrappers criados para o Monitoramento MUST expandir para a mesma árvore, slots e classes críticas dos arquétipos de origem e MUST permitir apenas adaptações tipadas de conteúdo, dados, filtros, permissões e estados.

#### Scenario: FiscalModuleTable compartilhada
- **WHEN** uma carteira usa o componente compartilhado
- **THEN** o resultado mantém `UDashboardPanel`, `UDashboardNavbar`, `UDashboardToolbar`, faixa utilitária, `UTable`, empty/error e paginação na ordem do template Customers

#### Scenario: Slot especializado
- **WHEN** um módulo necessita tabs, banner de cobertura ou filtro próprio
- **THEN** o recurso entra em slot documentado sem reordenar arbitrariamente a hierarquia canônica

#### Scenario: Wrapper genérico incompatível
- **WHEN** a abstração exige `Record<string, unknown>`, campos mágicos ou condição específica de vários módulos no mesmo template
- **THEN** a implementação é reprovada e deve usar contratos discriminados ou componente específico

### Requirement: Regressão visual determinística de todo o Monitoramento
A suíte visual SHALL cobrir Dashboard, cada carteira, Caixa Postal selecionada, detalhe fiscal do cliente e overlays críticos com dados sintéticos sanitizados e viewports fixas.

#### Scenario: Estado preenchido desktop
- **WHEN** a suíte captura uma rota em `1440×900`
- **THEN** shell, header, navegação do módulo, KPIs, filtros, tabela e detalhe aplicável são comparados por zonas ao baseline aprovado

#### Scenario: Estado preenchido mobile
- **WHEN** a suíte captura a mesma rota em `390×844`
- **THEN** navegação, filtros prioritários, identidade do cliente, situação e ação principal continuam visíveis ou acessíveis no overlay correspondente

#### Scenario: Estados alternativos
- **WHEN** a rota é testada em loading, vazio, erro, `UNSUPPORTED` ou `BLOCKED`
- **THEN** cada estado possui evidência funcional e visual própria, sem reutilizar screenshot de sucesso como único aceite

#### Scenario: Artefato sanitizado
- **WHEN** screenshots, traces ou relatórios são produzidos
- **THEN** a varredura confirma ausência de material fiscal real, PFX, senha, PEM, XML, cookie, token e identificador de cofre

### Requirement: Aceite visual inclui conteúdo operacional realista
Uma rota de Monitoramento SHALL ser considerada visualmente concluída somente quando o cenário preenchido exercitar dados, filtros, navegação e ações permitidas coerentes, além de passar estados vazio/erro e responsividade.

#### Scenario: Página apenas com empty state
- **WHEN** a implementação possui estrutura correta mas não existe fixture preenchida e navegável
- **THEN** a rota permanece incompleta para esta change

#### Scenario: Screenshot sem interação
- **WHEN** a imagem está estável mas filtros, deep-links, paginação, detalhe ou ação permitida não funcionam
- **THEN** o aceite falha até os testes funcionais correspondentes passarem

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

### Requirement: Matriz de derivação da família Work
O sistema MUST registrar cada rota e componente principal de `/work` com o arquivo exato do Nuxt UI Dashboard Template fixado usado como origem e com justificativa para qualquer divergência funcional.

#### Scenario: Minha fila
- **WHEN** `/work` é implementado ou revisado
- **THEN** split, resize, seleção, atalhos, estado neutro e overlay mobile são rastreados a `pages/inbox.vue`, `components/inbox/InboxList.vue` e `components/inbox/InboxMail.vue`

#### Scenario: Processos e modelos
- **WHEN** listas, detalhe ou editor são implementados
- **THEN** filtros/tabela/rodapé apontam para `pages/customers.vue`, seções para `pages/settings.vue` e formulário curto para `components/customers/AddModal.vue`

#### Scenario: Calendário e resumo
- **WHEN** calendário ou bloco de progresso é implementado
- **THEN** navbar, toolbar, painéis e stats apontam para `pages/index.vue`, `components/home/*` e o padrão multi-panel do Dashboard, com `UCalendar` apenas como seletor

### Requirement: Referência Makro limitada à organização da informação
A Agenda Makro SHALL orientar somente alternância temporal, densidade por dia, navegação de período, minicalendário e rail de listas; estrutura, marca, cores, tipografia, ícones, responsividade e acessibilidade MUST permanecer derivadas do template/Nuxt UI do MonitorHub.

#### Scenario: Grade horária da referência
- **WHEN** a imagem externa mostra eventos posicionados entre horas
- **THEN** a implementação usa lanes por data e prazos reais, sem copiar horários ou criar campos de compromisso inexistentes

#### Scenario: Sidebar e identidade da referência
- **WHEN** a referência externa usa sidebar, logo, cores ou ícones próprios
- **THEN** a implementação preserva `UDashboardGroup`, sidebar do MonitorHub, `OfficeIdentity`, tokens semânticos e ícones `i-lucide-*`

### Requirement: Forma canônica preservada nas adaptações operacionais
Componentes compartilhados do workspace MUST expandir para a árvore, slots e classes críticas do arquétipo de origem e SHALL adaptar somente labels, rotas, APIs, permissões, estados e conteúdo de domínio.

#### Scenario: Lista operacional compartilhada
- **WHEN** um componente encapsula linhas da fila ou tabela de processos
- **THEN** headers, seleção, hover/foco, divisores, loading, vazio e paginação permanecem reconhecíveis contra o arquivo do template

#### Scenario: Abstração genérica incompatível
- **WHEN** um wrapper exige campos mágicos, `Record<string, unknown>` indiscriminado ou reordena slots por rota
- **THEN** o aceite falha e a página deve usar contrato tipado ou componente específico

### Requirement: Regressão visual e funcional determinística do workspace
A suíte SHALL cobrir todas as rotas `/work` em desktop e mobile com fixtures persistidas e âncora temporal fixa, incluindo estados alternativos e overlays críticos.

#### Scenario: Estado preenchido desktop
- **WHEN** Playwright captura `1440×900`
- **THEN** shell, navbar, toolbar, filtros, lista/grade, detalhe, contagens e ação primária são comparados por zonas ao baseline aprovado

#### Scenario: Estado preenchido mobile
- **WHEN** Playwright captura `390×844`
- **THEN** contexto, identidade, estado, prazo e ação essencial permanecem visíveis ou acessíveis em slideover/drawer

#### Scenario: Estados alternativos e largura mínima
- **WHEN** loading, vazio, erro, viewer e 360 px são exercitados
- **THEN** cada estado possui evidência própria, foco/teclado continuam funcionais e não há overflow horizontal do documento

#### Scenario: Artefato sanitizado
- **WHEN** screenshots, traces ou relatórios são produzidos
- **THEN** o scanner confirma ausência de material sensível, XML fiscal real, cookies e dados do tenant sentinela
