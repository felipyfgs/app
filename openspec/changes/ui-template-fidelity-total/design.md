## Context

O monorepo fixa `.reference/nuxt-dashboard-template` no commit `0f30c09d697160ef5dd0aaaec27fae8d7195d930`. A referência possui arquétipos concretos para shell, home, lista administrativa, mestre–detalhe, settings, lista em card e modais. O frontend cresceu para 51 `pages/**/*.vue` com wrappers, híbridos, infinite scroll, sticky/virtualização e chrome local que não existem nesses arquivos.

Em **2026-07-16**, o produto decidiu desconsiderar as decisões visuais/interacionais das changes anteriores e migrar integralmente para o template. Esta change substitui `padronizar-tabelas-carregamento-incremental`; essa change incompleta não deve ser sincronizada nem arquivada como concluída. O registro da supersessão está em `evidence/SUPERSESSION.md`.

**Ordem de autoridade para esta migração:** domínio e segurança → arquivo exato do template → dados/API reais. Nenhuma change antiga autoriza divergência de chrome ou interação.

## Goals / Non-Goals

**Goals:**

1. Manter inventário completo de todos os arquivos `pages/**/*.vue`, layouts e componentes que influenciam chrome.
2. Fazer cada superfície autenticada renderizável escolher **um único arquétipo primário** e copiar diretamente sua árvore, slots, ordem, classes críticas, breakpoints e interação.
3. Remover wrappers, presets e comportamentos de apresentação que não existam no template.
4. Restaurar em toda lista administrativa a composição integral de `customers.vue`, inclusive footer, contagem e `UPagination`, alimentados por paginação server-side.
5. Usar `inbox.vue` integralmente em toda experiência mestre–detalhe, inclusive Documentos.
6. Aceitar somente adaptações de textos, rotas, dados/API, permissões, tenancy, estados e segurança.
7. Declarar `FINAL: PASS` apenas com 100% dos critérios aplicáveis em 100% das páginas existentes no aceite.

**Non-Goals:**

- Copiar mocks, conteúdo demo, TeamsMenu multi-team, cookie marketing ou “View page source”.
- Carregar coleções inteiras no navegador ou paginar apenas as linhas locais.
- Alterar cursores fiscais NSU/nNF, captura fiscal, SERPRO, cofre ou tenancy backend.
- Criar novo starter, design system, arquétipo híbrido ou “melhoria” visual local.
- Forçar páginas de login/2FA para dentro do shell dashboard; o template fixado não possui arquétipo de autenticação.

### Significado normativo de “100%” e “literal”

- **100% do inventário**: cada `pages/**/*.vue` existente no aceite está classificada e possui evidência. Páginas autenticadas visuais passam pelo template; auth e redirects usam `N/A` apenas nos critérios de dashboard realmente impossíveis, mantendo seus demais testes obrigatórios.
- **Literal**: o markup nasce por cópia direta do arquivo canônico e conserva componentes, slots, ordem, classes críticas, geometria, densidade, breakpoints e interação.
- **Adaptação permitida**: somente label, rota, dado/API, permissão, tenant, estado assíncrono e proteção de segredo. A adaptação não pode mudar o arquétipo.
- **Sem exceção estrutural**: “produto”, “legado”, “change anterior”, “melhoria local” e “wrapper rastreável” não justificam desvio de composição.
- **Fidelidade visual**: cada caso usa fixture determinística e tolerância global `maxDiffPixelRatio: 0.005`; tolerância maior não pode autorizar geometria diferente.

## Decisions

### D1 — Bundles canônicos únicos

| Bundle | Fontes exatas | Contrato |
|--------|---------------|----------|
| `SHELL` | `app/layouts/default.vue`, `TeamsMenu.vue`, `shell/UserMenu.vue`, `shell/NotificationsSlideover.vue`, `useDashboard.ts` | Shell global; `OfficeIdentity` preserva a geometria de `TeamsMenu` e restringe memberships |
| `HOME` | `app/pages/index.vue` + `app/components/home/*` | Navbar, toolbar quando funcional e body na ordem Stats → Chart → Sales |
| `LIST` | `app/pages/customers.vue` + `app/components/customers/{AddModal,DeleteModal}.vue` | Utilitários no body, `UTable`, footer, contagem e `UPagination` |
| `MASTER_DETAIL` | `app/pages/inbox.vue` + `app/components/inbox/{InboxList,InboxMail}.vue` | Painéis adjacentes desktop; `USlideover` apenas abaixo de `lg` |
| `SETTINGS_FORM` | `app/pages/settings.vue` + `app/pages/settings/index.vue` | Toolbar de seções, `max-w-2xl`, cards e formulário |
| `SETTINGS_CARD_LIST` | `app/pages/settings.vue` + `app/pages/settings/members.vue` + `MembersList.vue` | Lista curta dentro dos cards de Settings |
| `AUTH` | sem fonte no dashboard fixado | Contrato separado Nuxt UI; fora do denominador visual do dashboard |
| `REDIRECT` | sem superfície visual | Destino/query/histórico e ausência de chrome intermediário |

Combinações internas do mesmo bundle são permitidas. Cruzar bundles para criar `LIST+HOME`, `LIST+MASTER_DETAIL` ou `SETTINGS+LIST` é proibido. Conteúdo de domínio entra nos pontos dinâmicos do bundle escolhido, não cria uma nova casca.

### D2 — Chrome direto; wrappers de apresentação proibidos

Cada página de primeiro nível deve conter o `UDashboardPanel` e a anatomia do seu bundle diretamente. Pais Nuxt podem fornecer somente o bundle `SETTINGS` ou `MASTER_DETAIL` canônico, ou ser pass-through `<NuxtPage />` sem chrome.

Devem ser removidos como primitivas de apresentação:

- `components/shell/ListShell.vue` (`ShellListShell`);
- `components/monitoring/ModuleTable.vue` e `ModuleToolbar.vue`;
- `components/docs/Workspace.vue` e detalhe modal usado no lugar de Inbox;
- `components/shell/StickyTableFilters.vue` e teleports de filtros;
- `components/shell/InfiniteTableLoader.vue` e `useInfiniteTable`;
- `components/shell/TableFooter.vue` como wrapper do footer;
- `components/shell/KpiStrip.vue`, `components/monitoring/KpiStrip.vue` e chrome extra de `components/shell/OperationalContext.vue`;
- presets de layout/densidade/max-height em `table-ui.ts` e classes `calc(100dvh…)`.

Componentes-folha de domínio continuam permitidos quando não contêm panel, navbar, toolbar, footer, tabela completa, split ou regras de layout do arquétipo.

**Trade-off aceito:** haverá repetição controlada de markup. Nesta migração, diff direto contra a referência vale mais que a deduplicação de chrome.

### D3 — Lista administrativa e paginação

Toda rota `LIST` copia a ordem de `customers.vue`:

1. `UDashboardPanel`;
2. navbar com collapse e ação primária no `#right`;
3. body com busca/utilitários;
4. `UTable` com `:ui` literal no consumidor;
5. footer com contagem e `UPagination`.

Busca, filtro, ordenação e paginação são server-side, tenant-scoped e determinísticos. A UI não usa auto-load, sentinel, `Carregar mais`, exaustão silenciosa, virtualização ou sticky custom. Uma API de leitura cursor-only deve receber um adaptador paginado estável (`page`, `per_page`, `total` ou metadados equivalentes) antes de alimentar `UPagination`; isso não altera cursor fiscal NSU/nNF.

Ao mudar filtro, ordenação, página ou escritório, respostas obsoletas são canceladas/ignoradas e seleção incompatível é limpa. Checkbox, seletor de colunas e sorting só aparecem quando funcionais e autorizados.

### D4 — Documentos usa Inbox

`/docs`, `/docs/catalog` e `/docs/:accessKey` usam `MASTER_DETAIL`:

- primeiro painel redimensionável com lista/filtros;
- segundo painel adjacente no desktop ou empty canônico;
- `USlideover` no mobile;
- seleção/deep-link em `/docs/:accessKey`;
- `/notes/*` permanece somente redirect compatível enquanto existir.

`components/docs/Workspace.vue` em tabela+modal deixa de ser aceito. A mudança de rota e labels é adaptação de domínio; a forma e a interação vêm de `inbox.vue`.

### D5 — Supersessão das changes anteriores

- `ui-template-fidelity-total` é a única change normativa de UI/UX desta migração.
- `padronizar-tabelas-carregamento-incremental` permanece como histórico incompleto e **não** será sincronizada nem arquivada como entregue.
- Contratos seguros daquela investigação — paginação/ordenação server-side, allowlist, isolamento tenant, cancelamento de resposta obsoleta, ausência de truncamento e estados honestos — são incorporados aqui sem infinite scroll.
- A limpeza do diretório ativo da change substituída será uma ação OpenSpec explícita, sem marcar tarefas antigas como concluídas e sem aplicar seus delta specs.
- Main specs conflitantes são modificadas/removidas por esta change; histórico arquivado não prevalece sobre o novo delta.

### D6 — Auth e redirects

O template fixado não possui login ou 2FA. Essas três páginas permanecem no inventário como `AUTH`, com layout Nuxt UI separado, acessibilidade, responsividade e segurança obrigatórias, mas `template_dashboard=N/A` justificado.

Redirects não recebem tela artificial. Eles testam destino, query, histórico e ausência de flash/chrome. Um arquivo usado apenas para sustentar nesting custom deve ser removido se Nuxt puder preservar as rotas sem ele.

### D7 — Gate sem falso `PASS`

O gate atual de nomes é apenas baseline histórico. O gate definitivo usa manifesto estruturado e valida:

1. cada página presente exatamente uma vez;
2. bundle primário único e fontes existentes no commit fixado;
3. ausência de imports/proibições da D2 e de aliases híbridos;
4. AST/DOM renderizado com ordem, slots, classes e interação do bundle;
5. `LIST` com footer/contagem/`UPagination` e paginação server-side;
6. `MASTER_DETAIL` com segundo painel desktop e slideover mobile;
7. funcional, estados, papéis/tenancy, teclado/a11y, visual 1440/390, overflow 360 e segurança por caso.

Comentário, nome de componente, import ou screenshot isolado não constituem evidência estrutural.

### D8 — Ordem de migração

1. Registrar supersessão e reabrir todo aceite produzido sob as regras antigas.
2. Remover wrappers, infinite/sticky/virtualização, presets e chrome paralelo.
3. Copiar shell global do template.
4. Migrar `HOME`, `LIST`, `MASTER_DETAIL`, `SETTINGS_*` por família.
5. Adaptar APIs read-only necessárias ao `UPagination`, sem tocar cursores fiscais.
6. Validar auth e redirects pelo contrato próprio.
7. Executar gates integrais e produzir `FINAL: PASS` somente em 100%.

## Evidence model

Cada linha da matriz contém ou referencia:

`arquivo`, `rota/caso`, `pai Nuxt`, `layout/auth`, `bundle`, `origens exatas`, `cadeia de componentes`, `fixture`, `estrutura`, `funcional`, `estados`, `papéis/tenancy`, `a11y`, `visual-1440`, `visual-390`, `overflow-360`, `segurança`, `evidência` e `aceite`.

Não existem campos finais `inferido`, wildcard, `parent-or-missing`, “se aplicável” ou exceção estrutural. `N/A` exige justificativa verificável.

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Escopo de 50+ páginas | Waves por bundle e gate derivado do inventário |
| Repetição de markup | Comparação direta com fonte e revisão automatizada |
| API cursor-only não alimenta `UPagination` | Adaptador read-only paginado; cursores fiscais permanecem intocados |
| Regressão de permissões/tenancy | Matriz por papel e troca de escritório durante requisição |
| Falso positivo do gate lexical | AST/DOM, interação e evidência visual por rota |
| Conteúdo dinâmico instável | Fixtures sintéticas e máscaras somente sobre valores, nunca geometria |
| Auth sem fonte no template | `N/A` explícito de dashboard + contrato Nuxt UI separado |

## Open Questions

Nenhuma bloqueante. A decisão normativa é: template vence integralmente na forma e interação; dados, permissões, tenancy e segurança são as únicas adaptações.
