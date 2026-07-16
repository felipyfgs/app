## 0. Autoridade e supersessão

- [x] 0.1 Registrar em `evidence/SUPERSESSION.md` a decisão de 2026-07-16: `ui-template-fidelity-total` substitui `padronizar-tabelas-carregamento-incremental`, sem sync/archive como concluída
- [x] 0.2 Ler integralmente os arquivos canônicos de shell, Home, Customers, Inbox, Settings, card-list e modais no commit `0f30c09` e registrar em `evidence/TEMPLATE-SOURCES.md`
- [ ] 0.3 Retirar `padronizar-tabelas-carregamento-incremental` do diretório de changes ativas sem sincronizar seus delta specs, preservando o registro histórico de supersessão
- [ ] 0.4 Atualizar `AGENTS.md` e as cópias versionadas das skills `nuxt-dashboard-template`/`frontend-nuxt-stack` para remover auto-load, footerless e outras regras revogadas
- [ ] 0.5 Reler as main specs e garantir que todo conflito histórico seja explicitamente modificado ou removido pelos deltas desta change

## 1. Inventário e gate normativo

- [x] 1.1 Inventariar as 51 `frontend/app/pages/**/*.vue`, layouts e componentes de chrome existentes
- [x] 1.2 Registrar em `VILLAINS.md` wrappers, hybrids, infinite/sticky/virtualização, presets e classes que não existem no template
- [ ] 1.3 Converter `parity-matrix.md` em manifesto estruturado com bundle primário único, fontes exatas, pai, cadeia, fixture e todos os campos de aceite
- [ ] 1.4 Fazer o gate falhar para aliases híbridos, origem vaga, wildcard, `parent-or-missing`, exceção estrutural ou campo obrigatório ausente
- [x] 1.5 Fazer o gate falhar quando uma página importa wrapper/componente de chrome proibido
- [ ] 1.6 Implementar contratos AST/component ou DOM renderizado para ordem, slots, classes críticas, breakpoints e interação de cada bundle
- [x] 1.7 Fazer o gate de `LIST` exigir utilitários no body, `UTable` literal, footer, contagem e `UPagination`
- [ ] 1.8 Fazer o gate de `MASTER_DETAIL` exigir dois painéis no desktop e `USlideover` apenas no mobile
- [ ] 1.9 Reclassificar ou remover automaticamente qualquer `page.vue` nova antes de permitir merge

## 2. Purga de chrome paralelo

- [ ] 2.1 Remover `components/shell/ListShell.vue` das páginas e expandir o markup canônico diretamente em cada consumidor
- [ ] 2.2 Remover `components/monitoring/ModuleTable.vue`/`ModuleToolbar.vue` e reconstruir cada carteira a partir de um único bundle
- [ ] 2.3 Remover `components/docs/Workspace.vue`/detalhe modal e reconstruir Documentos a partir de `inbox.vue`
- [ ] 2.4 Remover `components/shell/StickyTableFilters.vue` e todos os teleports/sticky custom de filtros
- [ ] 2.5 Remover `components/shell/InfiniteTableLoader.vue`, `useInfiniteTable`, sentinels, auto-load e exaustão silenciosa
- [ ] 2.6 Remover `components/shell/TableFooter.vue` como wrapper e copiar o footer de `customers.vue` diretamente nas páginas `LIST`
- [ ] 2.7 Remover KPI strips/custom chrome e encaixar métricas reais em `HomeStats`, `HomeChart` e `HomeSales`
- [ ] 2.8 Remover presets de apresentação/max-height em `table-ui.ts`, classes `100dvh`/`calc(...)`, virtualização e sticky de tabela
- [ ] 2.9 Extrair de wrappers antigos somente composables de dados tipados, sem markup ou decisões de layout
- [ ] 2.10 Remover `pages/clients.vue` se for apenas casca de nesting, ou reduzi-lo a pass-through sem chrome preservando `/clients` e `/clients/dashboard`

## 3. Fundação e shell

- [ ] 3.1 Copiar `app.vue`, `app.config.ts` e CSS da referência, adaptando apenas idioma, SEO e identidade permitida
- [ ] 3.2 Copiar `layouts/default.vue` e preservar `UDashboardGroup`, sidebar, search, duas navegações, footer e slideover na mesma ordem
- [ ] 3.3 Adaptar `OfficeIdentity` sobre a geometria de `TeamsMenu`, sem seletor livre de escritório
- [ ] 3.4 Copiar `UserMenu`, `NotificationsSlideover` e `useDashboard`, removendo somente demos e conectando permissões/dados reais
- [ ] 3.5 Validar shell expandido, collapsed/mobile, command palette, atalhos e troca de escritório sem mistura tenant

## 4. Migração por bundle

- [ ] 4.1 Migrar `/` e `/monitoring` para `HOME`, mantendo somente Stats → Chart → Sales e controles reais da toolbar
- [ ] 4.2 Migrar `/clients/dashboard` e `/work/calendar` para `HOME` sem shell/KPI/toolbar paralelos
- [ ] 4.3 Migrar `/clients`, `/closing`, `/exports`, `/health`, `/docs/imports`, `/work/processes` e `/work/templates` para `LIST`
- [ ] 4.4 Migrar carteiras sem detalhe persistente, incluindo DCTFWeb e Simples/MEI, para `LIST`
- [ ] 4.5 Migrar `/docs`, `/docs/catalog` e `/docs/:accessKey` para `MASTER_DETAIL` de Inbox
- [ ] 4.6 Migrar `/work`, `/monitoring/mailbox`, Declarações, FGTS, Guias, Parcelamentos, SITFIS e Sincronizações para `MASTER_DETAIL` quando o detalhe persistente for obrigatório
- [ ] 4.7 Migrar `/settings`, `/clients/:id`, `/monitoring/clients/:id`, `/docs/imports/:id` e `/work/processes/:id` para `SETTINGS_FORM`
- [ ] 4.8 Migrar Departamentos, Estabelecimentos, Procurações e Consumo para `SETTINGS_CARD_LIST`
- [ ] 4.9 Copiar `AddModal.vue`/`DeleteModal.vue` somente nas mutações curtas de bundles `LIST`, preservando foco, Zod, labels e ações
- [ ] 4.10 Eliminar overlays desktop que substituam segundo painel e larguras `max-w-4xl`/full-width que substituam `max-w-2xl` de Settings

## 5. Dados, paginação e domínio

- [ ] 5.1 Manter busca, filtro, sorting, paginação e total server-side, sempre após autorização e escopo de tenant
- [ ] 5.2 Adaptar endpoints read-only cursor-only para metadados compatíveis com `UPagination`, sem percorrer cursores ocultamente e sem tocar NSU/nNF
- [ ] 5.3 Garantir allowlist de sorting, desempate determinístico, estado de nulos e ausência de truncamento silencioso
- [ ] 5.4 Cancelar/ignorar respostas obsoletas e resetar página/seleção ao mudar filtro, sorting ou escritório
- [ ] 5.5 Manter checkbox, column visibility e sorting somente quando houver função real e autorização
- [ ] 5.6 Cobrir loading inicial, refreshing, vazio, erro inicial, erro com dados e retry nos pontos canônicos de cada bundle
- [ ] 5.7 Confirmar que nenhuma API/UI aceita `office_id` livre ou expõe PFX, senha, PEM, token, Termo ou XML bruto

## 6. Auth e compatibilidade de rotas

- [ ] 6.1 Validar Login, challenge 2FA e setup 2FA pelo contrato Nuxt UI separado e registrar `template_dashboard=N/A` justificado
- [ ] 6.2 Validar redirects `/notes/*`, `/docs/import-batches` e `/settings/cte`: destino, query, histórico e ausência de chrome intermediário
- [ ] 6.3 Remover redirects realmente obsoletos se não houver contrato de compatibilidade ativo, atualizando filesystem e matriz no mesmo patch

## 7. Validação e aceite final

- [ ] 7.1 Vincular cada página restante a smoke da URL própria, testes de componentes e evidência funcional
- [ ] 7.2 Executar e corrigir lint, typecheck, Vitest completo, testes reais de componentes Vue e `nuxt generate`
- [ ] 7.3 Cobrir por Playwright todas as páginas renderizáveis e redirects em sessão/fixtures determinísticas
- [ ] 7.4 Cobrir estados, `ADMIN` confirmado/não confirmado, `OPERATOR`, `VIEWER` e troca de escritório por família
- [ ] 7.5 Cobrir WCAG 2.2 AA automatizada e fluxos de teclado/foco, incluindo table sorting, overlays, formulários 422 e splits
- [ ] 7.6 Gerar/aprovar visual de cada caso em `1440×900` e `390×844` com `maxDiffPixelRatio: 0.005`
- [ ] 7.7 Validar operabilidade e ausência de overflow obrigatório em `360×800`
- [ ] 7.8 Executar scan de DOM, responses, traces, relatórios e screenshots para impedir material fiscal/credencial sensível
- [ ] 7.9 Tornar lint, typecheck, unit/component, fidelity semântico, generate, Playwright, a11y e scan gates obrigatórios do CI
- [ ] 7.10 Atualizar `evidence/ACCEPTANCE.md` para `FINAL: PASS` somente quando 100% dos critérios aplicáveis de 100% das páginas estiverem verdes
- [ ] 7.11 Executar `openspec validate ui-template-fidelity-total --json`, sincronizar main specs e arquivar somente após `FINAL: PASS`
