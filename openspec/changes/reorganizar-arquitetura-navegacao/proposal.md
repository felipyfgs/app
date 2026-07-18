## Por quê

A navegação autenticada cresceu de forma horizontal e hoje concentra até 11 destinos no Monitoramento, 10 seções no detalhe cadastral do cliente e 14 seções no detalhe fiscal, o que reduz a legibilidade da barra superior, exige rolagem para descobrir recursos e dificulta a expansão do produto. A aplicação precisa de uma hierarquia canônica, responsiva e orientada ao domínio antes que novas superfícies multitenant e fiscais ampliem ainda mais esses menus.

## O que muda

- Reorganizar a navegação autenticada em áreas globais estáveis na sidebar e, dentro de cada contexto, em no máximo duas camadas visíveis de Tabs → Subtabs.
- Consolidar Trabalho, Clientes, Fiscal, Documentos, Operações, Conta e Administração em modelos canônicos reutilizados pela sidebar, busca global, barras locais e atalhos autorizados.
- Agrupar os onze módulos fiscais em Visão geral, Obrigações, Regularidade, Financeiro e Comunicações, preservando todas as rotas e funcionalidades atuais.
- Agrupar as seções dos detalhes cadastral e fiscal do cliente por contexto, substituindo barras horizontais extensas sem criar uma terceira camada de navegação.
- Tratar filtros, presets, modalidades e alternâncias locais como controles de visualização, não como destinos de navegação.
- Tornar a barra superior compacta: título ou breadcrumb, no máximo uma ação primária exposta e ações secundárias em menu explícito, sem remover capacidades.
- Usar o mesmo catálogo de destinos no desktop e no mobile; no mobile, apresentar seletores acessíveis quando a faixa de tabs não couber sem rolagem de descoberta.
- Preservar URLs, redirects legados, histórico do navegador, deep links, regras de permissão, contexto de `CurrentOffice`, estados locais e comportamento das páginas existentes.
- Cobrir a taxonomia, permissões, estados ativos, responsividade, teclado, foco, nomes acessíveis e ausência de overflow com testes e validação visual rota a rota.

Não são objetivos desta change: alterar contratos HTTP ou regras fiscais; criar ou remover funcionalidades; habilitar SERPRO live, mutações fiscais, canais outbound ou flags; mudar a autoridade de tenancy; renomear rotas por estética; substituir os arquétipos do template; criar um design system paralelo; emitir parecer jurídico; ou incorporar superfícies futuras antes de seus contratos estarem disponíveis.

## Capacidades

### Novas capacidades

- `navigation-architecture`: taxonomia canônica da navegação autenticada, hierarquia Tabs → Subtabs, comportamento responsivo e acessível, preservação de rotas/permissões e critérios de validação visual.

### Capacidades modificadas

Nenhuma.

## Impacto

- Frontend Nuxt: shell autenticado, catálogos de navegação, busca global, atalhos, barras superiores, shells de área e detalhes de entidade em `frontend/app/`.
- Nuxt UI: reutilização de `UDashboardSidebar`, `UDashboardNavbar`, `UDashboardToolbar`, `UNavigationMenu`, `UTabs`, `USelectMenu`/`UDropdownMenu` e componentes existentes de conteúdo.
- Testes: matrizes unitárias de rotas, estado ativo, permissões e responsividade; gates de lint, typecheck, Vitest, generate, fidelidade e artefatos; inspeção visual de cada rota aplicável em desktop e mobile.
- OpenSpec: nova capability transversal justificada porque a mesma taxonomia precisa ser única entre shell, áreas e contextos, evitando mudanças independentes e contraditórias por módulo.
- Backend e APIs: sem alteração prevista; o frontend continua refletindo capacidades retornadas pelo backend, que permanece como autoridade.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: shell do Nuxt UI Dashboard fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09`, rotas Nuxt atuais e capability principal `schema-conventions`.
- Depende de: `padronizar-autorizacao-multitenant` apenas no contrato de `tenant-access-governance` já definido em specs.
- Capability/contrato consumido: permissões efetivas e separação entre autoridade global e tenant; a navegação somente reflete essas capacidades.
- Marco exigido: `specs`.
- Relação: coordenada; a implementação desta change não espera o encerramento do rollout multitenant, mas deve reconciliar arquivos compartilhados e manter compatibilidade com helpers transitórios.
- Desbloqueia: inclusão escalável de perfis e permissões, administradores da plataforma e novas superfícies fiscais sem ampliar barras horizontais no mesmo nível.
- Condições de paralelismo: áreas sem arquivos compartilhados podem avançar em paralelo; alterações em `navigation.ts`, `account-navigation.ts`, `useDashboard.ts`, permissions, shell ou testes de navegação devem ser serializadas com a change multitenant e preservar o worktree existente.
