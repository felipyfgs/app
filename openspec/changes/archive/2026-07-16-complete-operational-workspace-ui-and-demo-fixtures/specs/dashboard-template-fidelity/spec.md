## ADDED Requirements

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
