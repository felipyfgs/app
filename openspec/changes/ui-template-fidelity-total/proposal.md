## Why

O painel já cita o template Nuxt UI Dashboard (`0f30c09`) e passou por changes de fidelidade, porém o inventário atual mostra deriva: dezenas de rotas autenticadas dependem de cascas paralelas (`ShellListShell`, `MonitoringModuleTable`, `DocsWorkspace`), infinite scroll, presets, densidades, toolbars e overlays que não existem na referência. O resultado é layout “parecido”, não uma migração para o template.

Por decisão de produto em **2026-07-16**, esta change desconsidera decisões visuais/interacionais de changes anteriores e passa a ser a única direção normativa da migração. Ela substitui explicitamente `padronizar-tabelas-carregamento-incremental`: seus deltas não devem ser sincronizados e infinite scroll, sentinel, sticky/virtualização e ausência de footer deixam de ser exceções autorizadas.

Esta change fecha a cobertura **total** do frontend autenticado e de auth, com matriz origem→destino, remoção de deriva e gates automatizados. Aqui, **100%** significa que cada `frontend/app/pages/**/*.vue` possui um caso de aceite rastreável e passa todos os critérios aplicáveis. Nas superfícies autenticadas renderizáveis, chrome, DOM, ordem, slots, classes críticas e interação são copiados do arquivo canônico; não significa cobertura de código de 100% nem igualdade de conteúdo/dados com a demo.

## What Changes

- Inventariar **todos** os arquivos em `frontend/app/pages/` (baseline: 51), seus pais Nuxt, layouts e componentes delegados, classificando cada caso em arquétipo do template (shell, home, lista admin, mestre–detalhe, settings, modal form, lista em card, auth fora do shell ou redirect).
- Exigir que toda superfície autenticada derive da **cópia direta e estruturalmente literal** de um único arquétipo em `.reference/nuxt-dashboard-template` (commit `0f30c09`), preservando chrome, slots, ordem, classes críticas, densidade, breakpoints e hierarquia de ações.
- Remover como primitivas de chrome `components/shell/{ListShell,StickyTableFilters,InfiniteTableLoader,TableFooter,KpiStrip}.vue`, `components/monitoring/{ModuleTable,ModuleToolbar,KpiStrip}.vue`, `components/docs/Workspace.vue` (e seus nomes autoimportados), além de presets de apresentação em `table-ui.ts`; cada página deve expor diretamente o markup do arquivo canônico escolhido. Componentes-folha de domínio podem permanecer se não esconderem ou reordenarem o arquétipo.
- Proibir arquétipos híbridos (`LIST+HOME`, `LIST+MASTER_DETAIL`, `SETTINGS+LIST`), wrappers “equivalentes”, classes de layout paralelas, `100dvh`, teleports de filtros, infinite scroll, sentinel, virtualização e sticky custom.
- Para listas administrativas, copiar integralmente `customers.vue`: utilitários no body, `UTable` com `:ui` literal, contagem e footer com `UPagination`. A paginação, busca, filtro e ordenação continuam server-side e nunca carregam o universo inteiro no navegador.
- Migrar Documentos para o arquétipo `inbox.vue`: lista e painel adjacente no desktop, detalhe em `USlideover` somente no mobile, mantendo `/docs` e `/docs/:accessKey` como rotas do domínio.
- Manter adaptações **somente** para labels pt-BR, rotas, API Sanctum real, permissões, tenancy, dados/estados e segurança. Essas adaptações não podem alterar composição, geometria ou interação do arquétipo.
- Atualizar a matriz de paridade e as specs `dashboard-template-fidelity` + `frontend-dashboard-experience` com cobertura de **todos** os arquivos e estados de rota atuais (não só o MVP original), sem valores finais vagos como `inferido`, `parent-or-missing` ou “se aplicável”.
- Manter uma matriz de aceite por `page.vue` com fonte exata, bundle único, cadeia página→pai→componentes-folha, fixture e evidências estrutural, funcional, de estados, papéis, tenancy, acessibilidade, visual desktop/mobile, overflow e segurança.
- Automatizar gates derivados dessa matriz: validação semântica do inventário, contratos renderizados dos bundles diretos, testes unitários/componentes, Playwright funcional + visual em `1440×900` / `390×844` / overflow `360`, acessibilidade WCAG 2.2 AA aplicável e varredura de segredos.
- **BREAKING (UX):** qualquer chrome, densidade, footer, composição ou interação que fuja do template será removido ou refeito — inclusive “melhorias” locais e exceções herdadas de changes anteriores.

## Definição de cobertura total

- O denominador é cada arquivo `pages/**/*.vue` existente no momento do aceite, além de layouts e componentes globais que definem o shell; wrappers proibidos precisam ser removidos, não aceitos como cascas.
- Página filha ou proxy só passa quando o pai Nuxt e os componentes delegados também possuem evidência; pais usados apenas para nesting devem ser removidos ou permanecer sem chrome.
- Rota visual só recebe `PASS` após estrutura, fluxo funcional, estados aplicáveis, papéis/permissões, tenancy, teclado/acessibilidade, `1440×900`, `390×844`, overflow em `360 px` e sanitização estarem verdes.
- Redirect recebe `N/A` apenas para screenshot e estados visuais; destino, query string, histórico e ausência de chrome duplicado continuam obrigatórios.
- O status global permanece `PENDING` enquanto qualquer linha ou gate obrigatório estiver pendente, mesmo que o inventário nominal esteja em 51/51.

## Não-objetivos

- Alterar regras fiscais, cursores NSU/nNF, SERPRO, cofre, tenancy backend ou contratos de API além do necessário para metadados de lista já existentes.
- Portal de contribuinte, scraping, Gov.br, CAPTCHA.
- Novo starter Nuxt, design system paralelo ou biblioteca de componentes fora do Nuxt UI 4.
- Copiar conteúdo demo do template (Teams multi-team, cookie consent, “View page source”, billing fictício).
- Expor PFX, senha, PEM, tokens, Termo, XML bruto ou `office_id` livre no client.
- Não substituir paginação server-side por carga integral ou paginação apenas local no browser. APIs cursor-only de leitura devem ganhar adaptação paginada estável para alimentar o `UPagination`, sem alterar cursores fiscais NSU/nNF.
- Não sincronizar nem arquivar como concluída a change substituída `padronizar-tabelas-carregamento-incremental`; sua decisão foi revogada e está registrada em `evidence/SUPERSESSION.md`.

## Capabilities

### New Capabilities

- Nenhuma. A cobertura total aprofunda capacidades já existentes.

### Modified Capabilities

- `dashboard-template-fidelity`: ampliar matriz e requisitos para **todas** as rotas atuais; gate de reprovação automática por ausência de arquétipo único, wrapper de chrome, híbrido ou divergência estrutural; inventário versionado obrigatório.
- `frontend-dashboard-experience`: uniformizar shell, hierarquia de ações, estados, overlays e responsividade em work/monitoring/closing/docs/health e demais superfícies que cresceram fora do conjunto original de fidelidade.

## Impact

- **Frontend:** `frontend/app/layouts/*`, `pages/**`, `components/**`, remoção de cascas/presets paralelos, `utils/navigation.ts`, `composables/useDashboard.ts`, `app.vue`, `app.config.ts`.
- **Referência:** somente leitura em `.reference/nuxt-dashboard-template` @ `0f30c09`.
- **Testes:** `frontend/tests/unit`, `frontend/tests/e2e` (visual + funcional), scripts de auditoria estrutural.
- **Docs na change:** matriz de paridade, registro de exceções, relatório de aceite.
- **Backend:** somente adaptações read-only de paginação/metadados quando uma API cursor-only não puder alimentar o controle visual do template; sem migrations fiscais e sem alteração de cursores NSU/nNF.
- **Ops:** sem Node em produção; SPA same-origin inalterada.
