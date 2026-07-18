## ADDED Requirements

### Requirement: Hierarquia canônica de navegação autenticada
O sistema SHALL organizar a navegação autenticada em áreas globais e, dentro do contexto ativo, em no máximo duas camadas visíveis de Tabs → Subtabs, com até cinco itens por camada no desktop.

#### Scenario: Área com múltiplos grupos
- **WHEN** o usuário abre uma área que possui mais de cinco destinos folha
- **THEN** o sistema apresenta até cinco grupos no primeiro nível e somente os destinos do grupo ativo no segundo nível

#### Scenario: Detalhe de entidade
- **WHEN** o usuário entra no detalhe de um cliente, processo, documento, lote ou escritório
- **THEN** a navegação contextual substitui a navegação local da área e não cria uma terceira faixa horizontal

#### Scenario: Grupo com destino único
- **WHEN** o grupo ativo possui apenas um destino
- **THEN** o sistema não renderiza uma camada redundante de subtabs

### Requirement: Áreas globais estáveis e orientadas por capacidade
O sistema SHALL apresentar as áreas Início, Trabalho, Clientes, Fiscal, Documentos, Operações, Conta e Admin conforme as capacidades efetivas do usuário, mantendo gestão separada das áreas operacionais.

#### Scenario: Usuário com contexto tenant
- **WHEN** um usuário autenticado possui contexto tenant e capacidades operacionais
- **THEN** a sidebar apresenta somente as áreas e destinos autorizados para esse contexto

#### Scenario: Administrador da plataforma sem tenant
- **WHEN** um administrador da plataforma não possui tenant selecionado
- **THEN** a navegação não apresenta superfícies fiscais tenant-scoped e oferece apenas destinos globais autorizados

#### Scenario: Troca de tenant
- **WHEN** a identidade ou o tenant ativo muda
- **THEN** o sistema reconstrói a navegação com as novas capacidades e descarta estado ativo tenant-scoped obsoleto

### Requirement: Fonte única para destinos e estado ativo
O sistema MUST derivar sidebar, busca global, atalhos e barras locais de catálogos canônicos compartilhados para rótulo, path, ordem, permissão e regra de estado ativo.

#### Scenario: Destino folha autorizado
- **WHEN** um destino autorizado é incluído em uma área
- **THEN** o mesmo rótulo e path são usados na navegação local e na busca global sem cadastro duplicado divergente

#### Scenario: Rota de detalhe dinâmica
- **WHEN** o usuário acessa uma rota de detalhe ou subrota de um destino
- **THEN** o catálogo destaca o grupo e o destino folha corretos por regras centralizadas de `exact`, prefixo ou override explícito

#### Scenario: Destino não autorizado
- **WHEN** a capacidade necessária não está presente
- **THEN** o destino é omitido de sidebar, busca, atalhos e navegação local sem substituir a autorização do backend

### Requirement: Navegação da área Trabalho
O sistema SHALL organizar Trabalho em Minha fila, Processos, Calendário e Modelos, condicionando Modelos à capacidade correspondente e tratando status da fila como filtros.

#### Scenario: Navegação principal de Trabalho
- **WHEN** o usuário autorizado abre Trabalho
- **THEN** ele pode acessar `/work`, `/work/processes`, `/work/calendar` e, quando permitido, `/work/templates` pela navegação da área

#### Scenario: Presets da fila
- **WHEN** o usuário alterna entre abertas, hoje, atrasadas, semana, impedidas ou concluídas
- **THEN** a alternância permanece um controle de filtro e não cria novos destinos globais

#### Scenario: Detalhe de processo
- **WHEN** o usuário abre `/work/processes/:id`
- **THEN** o contexto apresenta Resumo, Tarefas, Comentários e Histórico sem empilhar a barra principal de Trabalho

### Requirement: Navegação cadastral de Clientes
O sistema SHALL manter Lista e Dashboard no catálogo de Clientes e agrupar o detalhe em Visão geral, Dados, Fiscal e Integrações, preservando cada path existente.

#### Scenario: Catálogo de clientes
- **WHEN** o usuário abre `/clients` ou `/clients/dashboard`
- **THEN** a barra local apresenta Lista e Dashboard e preserva a ação autorizada de Novo cliente

#### Scenario: Detalhe cadastral
- **WHEN** o usuário abre `/clients/:id` ou uma subrota existente
- **THEN** o sistema disponibiliza Resumo em Visão geral; Cadastro e Estabelecimentos em Dados; CCMEI, Receitas SICALC, Pagamentos e Renúncias em Fiscal; e Certificado A1, Sincronização e Captura de saídas em Integrações

#### Scenario: Modal de cliente
- **WHEN** o detalhe é aberto no modal existente
- **THEN** o modal reutiliza os mesmos grupos e rótulos apenas para as seções que oferece, sem publicar destino inexistente

### Requirement: Navegação da área Fiscal
O sistema SHALL agrupar o monitoramento em Visão geral, Obrigações, Regularidade, Financeiro e Comunicações, mantendo as rotas fiscais atuais.

#### Scenario: Grupos fiscais
- **WHEN** o usuário abre qualquer rota sob `/monitoring`
- **THEN** o sistema apresenta Dashboard fiscal em Visão geral; Simples/MEI, DCTFWeb/MIT e Declarações em Obrigações; SITFIS, FGTS/eSocial, Cadastro/Vínculos e Processos fiscais em Regularidade; Parcelamentos e Guias em Financeiro; e Caixas Postais em Comunicações

#### Scenario: Alternância local de submódulo
- **WHEN** o usuário alterna PGDAS-D/PGMEI ou DCTFWeb/MIT
- **THEN** o sistema mantém a alternância como modo local no path canônico atual, sem criar terceira camada de navegação, query ou rota nova

#### Scenario: Filtros e modalidades
- **WHEN** o usuário altera KPI, filtro, período, triagem ou modalidade fiscal
- **THEN** o controle permanece na toolbar de dados ou no conteúdo e não é promovido a destino de navegação

### Requirement: Navegação do detalhe fiscal do cliente
O sistema SHALL agrupar as seções de `/monitoring/clients/:clientId/:section?` em Visão geral, Atividade, Obrigações, Financeiro e Regularidade, preservando carregamento lazy e paths.

#### Scenario: Grupos do detalhe fiscal
- **WHEN** um cliente fiscal válido é aberto
- **THEN** o sistema disponibiliza Resumo; Execuções, Achados e Pendências; Declarações, PGDAS-D e FGTS; Parcelamentos e Guias; SITFIS, Cadastro/Vínculos, CCMEI, Renúncias e Processos fiscais nos respectivos grupos

#### Scenario: Carregamento de seção
- **WHEN** o usuário seleciona uma subtab do detalhe fiscal
- **THEN** somente a seção ativa é carregada conforme o contrato lazy atual e respostas de outro tenant ou contexto continuam descartadas

#### Scenario: Vocabulário visível
- **WHEN** a seção técnica `findings` é apresentada ao usuário
- **THEN** o rótulo visível é `Achados` sem exigir renomear identificadores internos ou APIs

### Requirement: Navegação de Documentos e Operações
O sistema SHALL agrupar Por cliente, Catálogo e Processamento em Documentos e manter Saúde, Sincronizações e Fechamento em Operações, sem alterar paths ou capacidades.

#### Scenario: Processamento documental
- **WHEN** o usuário abre Importações ou Exportações
- **THEN** a navegação destaca Documentos → Processamento e disponibiliza `/docs/imports` e `/exports`

#### Scenario: Detalhe documental
- **WHEN** o usuário abre `/docs/:accessKey` ou `/docs/imports/:id`
- **THEN** o contexto herda respectivamente Catálogo ou Importações e mantém ações e retorno apropriados

#### Scenario: Operações
- **WHEN** o usuário abre `/health`, `/syncs` ou `/closing`
- **THEN** a navegação destaca respectivamente Saúde, Sincronizações ou Fechamento dentro de Operações

### Requirement: Navegação de Conta e Administração
O sistema SHALL separar Perfil, Organização, Pessoas e acesso e Plano em Conta, e SHALL manter superfícies globais de Administração isoladas das superfícies tenant.

#### Scenario: Conta completa autorizada
- **WHEN** o usuário possui as capacidades tenant correspondentes
- **THEN** Conta oferece Perfil; Escritório e Departamentos em Organização; Equipe em Pessoas e acesso; Assinatura e Consumo em Plano

#### Scenario: Superfície futura indisponível
- **WHEN** Perfis e permissões ou Administradores da plataforma ainda não foram implementados por suas changes proprietárias
- **THEN** a navegação não publica links inativos, mas os grupos existentes permanecem aptos a recebê-los posteriormente

#### Scenario: Console SERPRO
- **WHEN** um administrador autorizado abre `/admin/serpro`
- **THEN** o console agrupa Status, Consumo e Liberação em Operação; Acesso, Contratos e Cobertura em Integração; e Canário DTE em Canário

### Requirement: Barra superior compacta sem perda funcional
O sistema SHALL apresentar em cada navbar título ou breadcrumb, controle da sidebar e no máximo uma ação primária exposta, agrupando ações secundárias autorizadas em um menu `Mais ações` acessível.

#### Scenario: Página com múltiplas ações
- **WHEN** uma página possui duas ou mais ações autorizadas
- **THEN** uma ação contextual primária permanece exposta e todas as demais continuam disponíveis, com rótulo, no menu `Mais ações`

#### Scenario: Ação não autorizada
- **WHEN** o usuário não possui capacidade para uma ação
- **THEN** a ação não aparece nem diretamente nem no menu de ações

#### Scenario: Título longo
- **WHEN** o título ultrapassa a largura disponível
- **THEN** ele pode ser truncado visualmente, mas conserva nome acessível completo e não provoca overflow horizontal da página

### Requirement: Experiência responsiva e acessível
O sistema MUST oferecer os mesmos destinos autorizados em desktop e mobile sem depender exclusivamente de rolagem horizontal para descoberta.

#### Scenario: Desktop
- **WHEN** a viewport possui largura suficiente para o layout desktop
- **THEN** tabs e subtabs aparecem como navegação horizontal com foco visível, item ativo e no máximo cinco itens por camada

#### Scenario: Mobile
- **WHEN** tabs ou subtabs não cabem na viewport móvel
- **THEN** o sistema oferece um seletor rotulado com todos os destinos autorizados, estado atual e alvos de toque de pelo menos 44 px

#### Scenario: Navegação por teclado
- **WHEN** o usuário navega sem mouse
- **THEN** todos os destinos e menus podem receber foco, ser acionados por teclado e expõem nome acessível e estado atual

#### Scenario: Título de rota
- **WHEN** a navegação conclui uma mudança de rota
- **THEN** a página apresenta título coerente em pt-BR e localização ativa anunciável por tecnologia assistiva

### Requirement: Compatibilidade integral de rotas e fluxos
O sistema MUST preservar URLs, redirects legados, histórico do navegador, deep links, parâmetros aceitos, permissões e funcionalidades existentes durante a reorganização.

#### Scenario: URL existente
- **WHEN** uma URL canônica atual é acessada diretamente ou recarregada
- **THEN** a mesma funcionalidade abre e a nova hierarquia destaca o contexto correto

#### Scenario: Alias legado
- **WHEN** o usuário acessa um alias em `/notes/*`, `/settings/*`, redirects de 2FA ou submódulo fiscal legado
- **THEN** o redirect existente continua apontando ao destino canônico sem criar item duplicado na navegação

#### Scenario: Ações e dados da página
- **WHEN** o chrome de uma tela é reorganizado
- **THEN** tabelas, formulários, filtros, ações, loading, vazio, erro, detalhes e regras de permissão preservam o comportamento anterior

### Requirement: Validação automatizada e visual de todas as superfícies
O sistema MUST ter evidência de que cada rota aplicável permanece funcional, responsiva e acessível após a mudança.

#### Scenario: Gates automatizados
- **WHEN** a implementação estiver pronta para verificação
- **THEN** `pnpm run test:gate`, `pnpm run generate`, `pnpm run test:fidelity` e `pnpm run test:artifacts` concluem com sucesso

#### Scenario: Matriz visual
- **WHEN** a verificação visual for executada
- **THEN** cada rota `SHELL`, `CHILD` relevante e `AUTH` possui resultado registrado em desktop e mobile, incluindo estados aplicáveis de loading, vazio, erro, permissão, detalhe, foco e overflow

#### Scenario: Redirect visual
- **WHEN** uma rota é somente redirect
- **THEN** a evidência valida o destino final e o comportamento do redirect, sem exigir screenshot de um estado intermediário transitório
