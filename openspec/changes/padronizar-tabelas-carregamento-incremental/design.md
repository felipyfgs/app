> **STATUS: SUPERSEDED em 2026-07-16.** Não aplicar, sincronizar ou arquivar como concluída. A decisão substituta está em `../ui-template-fidelity-total/evidence/SUPERSESSION.md`.

## Contexto

O frontend já centraliza a forma das listas em `DashboardListShell`, `DashboardStickyTableFilters`, `table-ui.ts` e `table-sort.ts`, derivados do `customers.vue` fixado. O worktree contém uma evolução ainda não commitada que teleporta filtros e ações para o header do `UDashboardPanel`, adiciona `sticky="header"` e limita a altura das tabelas. Essa evolução deve ser preservada, corrigindo apenas o `sticky` aplicado indevidamente ao root inteiro do `UTable`.

As fontes de dados não são homogêneas: Notas, Saúde e Sincronizações já possuem cursor; Clientes, carteiras fiscais, Exportações, Work, Importações e Consumo usam `LengthAwarePaginator`; algumas listas pequenas retornam coleções completas. O Nuxt UI `4.9.0` instalado oferece `UTable` com sorting/selection controlados, sticky e virtualização; o próprio exemplo oficial recomenda `useInfiniteScroll` do VueUse para anexar páginas server-side. `@vueuse/core` já é dependência direta.

No plano de dados, toda consulta continua derivando o escritório da sessão e aplicando `office_id` antes de filtro, ordenação ou cursor. No plano de controle, telas de plataforma continuam exigindo `PLATFORM_ADMIN` e não ganham acesso implícito a dados fiscais. Nenhum estado de tabela persiste segredo ou material fiscal bruto.

## Objetivos / Não-objetivos

**Objetivos:**

- oferecer um contrato único de experiência para listas grandes, ainda que os adaptadores de API sejam cursor ou página numerada;
- manter toolbar/filtros/ações disponíveis enquanto somente o corpo tabular rola;
- ordenar o conjunto completo no servidor com allowlist e desempate único;
- anexar blocos sem duplicação, impedir respostas obsoletas e reiniciar corretamente ao mudar consulta ou tenant;
- limitar o DOM das maiores tabelas com virtualização nativa e altura controlada;
- manter seleção estável, acionável, autorizada e restrita às linhas explicitamente carregadas/selecionadas;
- preservar a anatomia e as classes críticas do template fixado.

**Não-objetivos:**

- criar um grid genérico que esconda colunas, slots ou decisões da página;
- adicionar ação em massa, ordenação arbitrária ou seletor de colunas sem contrato funcional;
- migrar nesta change todos os endpoints legados para uma única implementação SQL ou alterar cursores fiscais/NSU;
- carregar todas as linhas no browser ou prometer seleção de todo o resultado não carregado;
- mudar permissões, conteúdo fiscal, cofre ou contratos SERPRO.

## Decisões

### 1. A forma continua sendo `customers.vue`, sem footer persistente nas listas incrementais

Cada consumidor mantém `UTable`, colunas, slots, sorting, selection e ações declarados localmente. `DashboardListShell` conserva navbar/header/body; `DashboardStickyTableFilters` coloca busca, filtros, seletor de colunas e utilidades no header fixo. Em viewport móvel, listas que apresentam KPIs antes da tabela mantêm excepcionalmente os filtros no fluxo natural do body, depois dos cards; KPIs e filtros saem antes de o cabeçalho tabular fixar, evitando sobreposição e duas áreas de rolagem. A lista incremental não mantém footer, contagem, botão de continuação ou mensagem permanente de fim; durante uma carga adicional apresenta apenas indicador transitório.

Alternativa considerada: criar um componente universal configurado por schema. Rejeitada porque ocultaria a API do `UTable`, reduziria a rastreabilidade contra o template e misturaria permissões/domínios.

### 2. Um composable controla anexação; a API de cada tela continua explícita

Será criado um composable genérico de estado, não de domínio, com `rows`, `nextCursor` ou `nextPage`, `hasMore`, `total`, `pendingInitial`, `pendingMore`, `error`, `loadMore` e `resetAndLoad`. O loader injetado recebe `AbortSignal` e retorna um bloco normalizado. Cada página continua montando seus próprios parâmetros, chamando seu módulo de `useApi()` e mapeando a resposta.

O composable:

- associa cada carga a uma geração da consulta e ignora resposta de geração anterior;
- cancela a requisição anterior quando possível;
- impede cargas concorrentes equivalentes;
- anexa com deduplicação por chave estável fornecida pelo consumidor;
- limpa linhas, cursor/página e seleção quando filtros, sorting ou tenant mudam;
- preserva linhas válidas se apenas a carga adicional falhar.

Alternativa considerada: usar uma store global. Rejeitada porque o estado é efêmero por rota e uma store aumentaria o risco de mistura entre escritórios.

### 3. Cursor é preferido; paginação numerada existente terá adaptador incremental

Endpoints já baseados em cursor permanecem cursor-first. Endpoints de alta mutação e baixo custo de migração, como Exportações, devem evoluir para cursor opaco. Nos endpoints complexos que hoje dependem de totalização, joins ou ordenação em memória, a primeira entrega pode anexar páginas numeradas sequenciais, desde que a ordenação seja total e haja deduplicação; nenhum endpoint novo de lista grande deve nascer apenas com offset.

Todo cursor/ordenador server-side MUST:

- aplicar o escopo de tenant/autorização antes do recorte;
- aceitar somente campos e direções em allowlist;
- adicionar `id` ou chave única como desempate determinístico;
- manter semântica de nulos explícita;
- ser reiniciado quando filtro, sorting ou escritório mudar.

Alternativa considerada: buscar tudo e usar sorting/virtualização client-side. Rejeitada por custo, inconsistência e risco de isolamento.

### 4. Infinite scroll é automático como histórico de conversa

`useInfiniteScroll` observa o root rolável exposto por `UTable.$el`, com distância antecipada e `canLoadMore = hasMore && !pending`. Quando uma lista móvel com KPIs usa o body do painel como rolagem única, o binding observa esse ancestral em vez de criar uma segunda área rolável na tabela. A aproximação do fim pelo mouse, touch ou teclado aciona o próximo bloco sem ação manual. Um indicador transitório com `role="status"` anuncia carga adicional; a exaustão apenas interrompe novas requisições, sem renderizar “Fim da lista”. Falhas adicionais não apagam linhas e são recuperadas pelas ações de retry dos alertas da própria tela.

Alternativa considerada: manter footer com `Carregar mais` e “Fim da lista”. Rejeitada por decisão explícita de produto: a interação deve se comportar como lista de conversa auto-carregável.

### 5. Sorting é manual/server-side; checkbox depende de ação real

Colunas ordenáveis usam `sortHeader`, `v-model:sorting` e `sortingOptions.manualSorting = true`; a mudança de sorting redefine a consulta e rola ao topo. Feeds cuja ordem é semântica e fixa não exibem affordance de ordenação até a API oferecer contrato global.

Seleção usa `getRowId` com chave de domínio estável e checkbox tri-state apenas sobre linhas carregadas. Hoje o catálogo de Notas mantém seleção por causa da exportação. Clientes, processos, lotes e demais tabelas só recebem checkbox quando houver ação em massa implementada e autorizada; não haverá “selecionar todos os resultados” implícito.

### 6. Virtualização é seletiva e baseada na versão instalada

Listas grandes e de altura de linha previsível ativam `virtualize` desde a montagem, com `estimateSize` e `overscan` adequados, além de altura/max-height no root. Tabelas compactas do dashboard, resumos, eventos e listas curtas não virtualizam. Linhas móveis que incorporam nome fantasia, identificador, estado e ação têm altura variável e, portanto, não virtualizam nem limitam o root; usam a rolagem natural do body. O `sticky="header"` é mantido nos containers tabulares controlados: no mobile ele compensa o padding superior do body, usa fundo opaco e camada acima das linhas para não deixar conteúdo atravessar a faixa anterior ao cabeçalho. O suporte conjunto existe no Nuxt UI `4.9.0` e foi conferido no runtime local.

O preset tabular remove `sticky top-0 z-10` do root, pois isso fixa a tabela inteira; mantém apenas o container rolável/altura e deixa `sticky="header"` fixar o `thead`.

### 7. Matriz de aplicação

- Carregamento incremental/virtualizável: Clientes, documentos por cliente, catálogo de Notas, carteiras de Monitoramento, Guias, Processos/Modelos Work, Fechamento, Exportações, lotes/itens de importação e lançamentos de consumo.
- Conversão direta de cursor/botão para auto-load sem footer: Saúde e Sincronizações.
- Mestre–detalhe rolável: Caixa Postal mantém o arquétipo Inbox e recebe carregamento incremental sem virar `UTable` artificialmente.
- Permanecem pequenas/embutidas: dashboard de clientes, totais da Home, eventos do documento, onboarding AutXML, snapshots do detalhe e resumo por serviço.

## Riscos / Trade-offs

- **Offset com dados mutáveis pode pular linhas entre blocos** → preferir cursor nos feeds de alta mutação, usar ordenação total/dedupe no adaptador e registrar migrações restantes.
- **Virtualização com altura de linha variável pode deslocar o scroll** → limitar às tabelas de linhas previsíveis, ajustar `estimateSize` por família e cobrir desktop/mobile.
- **Auto-load pode disparar em sequência quando o container não enche** → usar `canLoadMore`, guarda de pending, limite server-side e estado interno de exaustão.
- **Mudança rápida de filtros pode misturar respostas** → AbortController, geração de consulta e reset atômico.
- **Seleção pode apontar para linha não visível após reset** → limpar seleção ao trocar consulta/tenant e usar IDs estáveis.
- **Alterações locais em andamento podem ser sobrescritas** → aplicar patches mínimos sobre o worktree atual e revisar o diff por arquivo.

## Plano de migração

1. Consolidar preset, composable, indicador transitório acessível e testes unitários.
2. Migrar primeiro Notas, Saúde e Sincronizações, que já possuem cursor/append.
3. Migrar Clientes e famílias compartilhadas (`FiscalModuleTable`, imports, Work), mantendo adaptador de página quando necessário.
4. Completar sorting server-side somente nas colunas permitidas e remover affordances locais incorretas.
5. Validar fixtures grandes, teclado, mobile e troca de tenant; manter os endpoints paginados compatíveis durante a transição.
6. Rollback: consumidores podem desativar temporariamente o auto-load sem reverter contratos da API; o composable e os novos parâmetros são aditivos.

## Questões em aberto

- Nenhuma bloqueante. Migrações de offset para cursor que dependam de read model/SQL materializado serão registradas como trabalho posterior, sem impedir a experiência incremental sobre os contratos estáveis atuais.
