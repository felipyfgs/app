> **STATUS: SUPERSEDED em 2026-07-16.** Esta change incompleta foi substituída por `ui-template-fidelity-total` por decisão explícita de migração integral ao template. Não sincronizar seus delta specs, não arquivar como concluída e não executar tarefas restantes. Registro: `../ui-template-fidelity-total/evidence/SUPERSESSION.md`.

## Por quê

As superfícies tabulares do painel evoluíram de forma desigual: parte delas já possui cabeçalho fixo, ordenação, seleção e ações com ícones, enquanto outras ainda expõem paginação tradicional ou controles sem o mesmo contrato interacional. Com o crescimento das carteiras, a troca manual de páginas também interrompe a análise operacional e precisa dar lugar a carregamento incremental acessível, sem trazer todo o conjunto para o navegador nem ordenar apenas a página já carregada.

## O que muda

- Auditar todas as superfícies tabulares autenticadas e classificá-las como tabela administrativa, tabela compacta, lista mestre–detalhe ou tabela embutida, registrando quais recursos são funcionais em cada uma.
- Manter busca, filtros e ações principais na faixa fixa do header do `UDashboardPanel`, preservando a composição do arquétipo `customers.vue` e o trabalho recente em `DashboardListShell`.
- Padronizar cabeçalhos ordenáveis, indicadores de direção, ações de linha, nomes acessíveis, seletor de colunas quando útil e estados loading/vazio/erro com os componentes e ícones oficiais do Nuxt UI.
- Exibir checkbox e seleção somente nas tabelas que possuam ação em massa real e autorizada; seleção acumulada deverá usar identificadores estáveis e permanecer coerente ao carregar novos blocos.
- **BREAKING (experiência de navegação):** remover `UPagination` e o footer persistente das listas operacionais grandes, substituindo a troca manual de página por carregamento incremental server-side automático próximo ao fim, como em um histórico de conversa.
- Preservar ordenação e filtros server-side sobre o conjunto completo. Ao alterar a consulta, descartar blocos anteriores, voltar ao topo e carregar novamente desde a primeira página ou cursor.
- Aplicar virtualização nativa do `UTable` somente quando a quantidade de linhas carregadas justificar, com altura limitada e overscan; listas pequenas e tabelas embutidas permanecem sem virtualização.
- Validar desktop, mobile, teclado, estados alternativos e crescimento de dados com testes unitários, integração e Playwright usando fixtures sanitizadas.

## Capacidades

### Novas capacidades

- Nenhuma.

### Capacidades modificadas

- `frontend-dashboard-experience`: substitui paginação/footer visível por carregamento incremental automático nas listas grandes e explicita contratos de ordenação global, seleção acionável, reset de consulta e virtualização seletiva.
- `dashboard-template-fidelity`: registra a remoção funcional do footer paginado de `customers.vue`, fixa filtros/ações no header do painel e exige paridade auditável dos recursos tabulares por rota.

## Impacto

- Frontend Nuxt: `DashboardListShell`, presets/utilitários de tabela, novo primitivo de carregamento incremental e páginas/componentes com `UTable` ou listas equivalentes.
- APIs Laravel: contratos paginados/cursor existentes continuam tenant-scoped; endpoints sem ordenação estável poderão receber allowlist de `sort`/`direction` e desempate por identificador, sem aceitar `office_id` do cliente.
- Testes: cobertura de anexação sem duplicatas, reset por filtro/ordenação, concorrência/cancelamento, seleção, auto-load até exaustão, sticky e virtualização.
- Dependências: nenhuma nova dependência direta; `@vueuse/core` e o suporte de virtualização do `@nuxt/ui` já estão instalados.

## Não-objetivos

- Não adicionar checkbox, seletor de colunas ou ação em massa meramente decorativos.
- Não carregar a coleção inteira no navegador, não ordenar somente os blocos já recebidos e não substituir isolamento server-side por filtragem local.
- Não aplicar virtualização a toda tabela indiscriminadamente nem criar um grid/design system paralelo ao `UTable`.
- Não alterar cursores fiscais NSU, regras de sincronização, conteúdo fiscal, permissões ou exposição de segredos.
- Não introduzir scraping, processo Node em produção ou novo starter Nuxt.
