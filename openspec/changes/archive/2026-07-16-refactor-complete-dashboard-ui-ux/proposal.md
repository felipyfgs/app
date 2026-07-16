## Why

O painel já possui todas as principais superfícies do produto, mas cresceu por entregas sucessivas e hoje apresenta diferenças de densidade, contexto, filtros, formulários, overlays, estados assíncronos e comportamento responsivo entre rotas equivalentes. A refatoração é necessária agora para transformar o aprendizado obtido com interfaces contábeis operacionais — especialmente contexto persistente, agenda, filtros próximos dos dados e totalizações — em uma experiência única, sem abandonar o Nuxt UI Dashboard Template fixado nem comprometer tenancy, permissões ou segurança fiscal.

## What Changes

- Refatorar o shell, autenticação e todas as rotas do frontend por uma matriz integral de páginas, usando os arquétipos literais de `.reference/nuxt-dashboard-template` no commit `0f30c09` como forma obrigatória.
- Incorporar, dentro desses arquétipos, os padrões úteis observados no MakroWeb: contexto operacional visível, seletor de período/competência, agenda Dia/Semana/Mês, filtros próximos dos dados, rodapés com paginação/contagens/totalizações e visão de carga por departamento.
- Uniformizar hierarquia visual, densidade, tipografia, cores semânticas, botões, toolbars, breadcrumbs contextuais, tabelas, cards, formulários, modais, slideovers, drawers, estados vazios, loading, erro e atualização com dados preservados.
- Evoluir o calendário operacional para visões mensal, semanal e diária, com painel de tarefas do período, filtros reproduzíveis e detalhe responsivo.
- Reorganizar fluxos de importação e geração em etapas explícitas com seleção, configuração, validação, confirmação, progresso e resultado, sem tornar ação fiscal indisponível aparentemente executável.
- Tornar dashboard, monitoramento, trabalho e detalhe de cliente mais escaneáveis por meio de contexto tenant-scoped, competência, progresso, riscos e deep-links acionáveis, sem gráficos decorativos ou métricas inventadas.
- Revisar a autenticação com `UAuthForm`, o setup de 2FA com `UForm`/`UStepper`, uploads com `UFileUpload`, tabelas com `UTable`/TanStack e overlays com os componentes Nuxt UI adequados, conforme APIs confirmadas nos MCPs oficiais.
- Preservar URLs canônicas e contratos de API atuais, criando endpoints somente quando uma interação aprovada não puder ser sustentada por dados reais e paginados do backend.
- Exigir aceite por arquivo de página, família de componentes, papel, estado assíncrono e viewport, com regressão visual determinística e varredura de conteúdo sensível.

## Não-objetivos

- Copiar a identidade visual, a sidebar, a paleta, os ícones sem rótulo ou os gráficos de pizza do MakroWeb.
- Criar novo starter, novo design system, nova biblioteca de componentes ou wrapper genérico que esconda a API do Nuxt UI.
- Alterar regras fiscais, cursores NSU/nNF, captura, projeções, contrato SERPRO, cofre, autenticação backend ou separação entre plano de controle e plano de dados.
- Criar portal ou login para contribuinte final, scraping de portal, automação Gov.br ou cobertura integral de FGTS Digital.
- Expor seletor livre de escritório, `office_id` no cliente, PFX, senha, PEM, chave privada, tokens, Consumer Secret, Termo XML ou conteúdo fiscal bruto.
- Inventar métricas, séries temporais, progresso, totais, ações ou estados apenas para preencher a interface.
- Substituir paginação/cursor server-side por carga integral ou paginação local.

## Capabilities

### New Capabilities

- Nenhuma. A change aprofunda capacidades de frontend e fidelidade já existentes.

### Modified Capabilities

- `dashboard-template-fidelity`: ampliar a matriz de paridade para todas as páginas, estados, overlays e viewports e disciplinar como padrões externos de densidade/informação podem ser incorporados sem substituir os arquétipos do template.
- `frontend-dashboard-experience`: uniformizar contexto operacional, calendários, filtros, tabelas, formulários, autenticação, importações, responsividade, acessibilidade e estados assíncronos em todas as rotas.
- `operations-dashboard`: apresentar carga e progresso por departamento, agenda operacional e deep-links de risco sem misturar sinais fiscais, de infraestrutura ou de trabalho.

## Impact

- **Frontend:** `frontend/app/app.vue`, `app.config.ts`, tema, layouts, navegação, composables, utilitários, componentes compartilhados e os 51 arquivos atuais em `frontend/app/pages/`.
- **Template:** cópia/adaptação rastreável de shell, Home, Customers, Inbox, Settings, AddModal, MembersList e componentes auxiliares do clone fixado; nenhuma troca de starter.
- **Nuxt UI 4:** uso consolidado de `UDashboard*`, `UNavigationMenu`, `UAuthForm`, `UForm`, `UTable`, `UCalendar`, `UStepper`, `UFileUpload`, `UModal`, `USlideover`, `UDrawer`, feedback e cores semânticas.
- **Nuxt 4:** manutenção de `app/`, rotas file-based, middleware, SPA `ssr: false`, build estático e Nginx same-origin, sem runtime Node em produção.
- **Backend:** preferencialmente nenhum impacto; eventuais read models ou metadados de paginação/totalização deverão ser tenant-scoped, sanitizados e cobertos por specs antes de implementação.
- **Testes:** ampliação de Vitest/component tests e Playwright visual/funcional para desktop `1440×900`, mobile `390×844` e verificação de overflow em `360 px`, incluindo papéis e estados de dados.
- **Compatibilidade:** rotas legadas `/notes/*` e `/docs/import-batches` continuam apenas como redirecionamentos sanitizados para os destinos canônicos.
