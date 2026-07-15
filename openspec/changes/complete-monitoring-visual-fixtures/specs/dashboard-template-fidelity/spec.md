## ADDED Requirements

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

