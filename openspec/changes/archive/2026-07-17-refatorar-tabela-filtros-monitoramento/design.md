## Context

As listas fiscais usam `MonitoringModuleTable` e `MonitoringModuleToolbar`, mas a implementação atual concentra shell, KPIs, tabela, seleção, permissões e efeitos de domínio em um único componente. Busca e situação existem simultaneamente como controles rápidos e como rascunhos avançados; filtros especializados são slots não controlados. O resultado permite sobrescrita de estado, múltiplas recargas, seleção residual e chaves repetidas em listas com mais de uma entidade por cliente.

O frontend é uma SPA Nuxt 4 autenticada por Sanctum. Todas as requisições permanecem tenant-scoped pelo `CurrentOffice`; esta change não adiciona nem aceita `office_id`. O backend Laravel, filas Horizon, flags e integrações SERPRO não mudam.

## Goals / Non-Goals

**Goals:**

- Aplicar um contrato controlado comum a todas as listas do monitoramento.
- Preservar o arquétipo de lista de `customers.vue` @ `0f30c09`, a paginação e a ordenação server-side.
- Manter busca/situação imediatas e aplicar os demais filtros em uma única transação.
- Isolar apresentação da tabela, estado de seleção e ações em massa.
- Limpar seleção ao mudar o contexto de dados e preservar apenas IDs ainda válidos em refresh manual.
- Cobrir interações reais com Vitest em ambiente Nuxt, sem Playwright.

**Non-Goals:**

- Alterar endpoints, DTOs, read models, rotas, middleware ou persistência.
- Alterar capacidades fiscais, habilitar flags/canais ou executar consultas/mutações reais.
- Reintroduzir infinite scroll, SSR Node ou Playwright E2E.
- Arquivar ou ampliar outras changes ativas.

## Decisions

### Contrato controlado e configurável

`MonitoringFilterValue` conterá busca, situação e os valores avançados atualmente usados (`competence`, `clientId`, `deliveryStatus`, `paymentStatus`, `status`). `MonitoringFilterConfig` declarará os controles rápidos e uma lista discriminada de campos avançados `month`, `client` ou `select`.

Busca emitirá `quick-filter-change` após 320 ms ou imediatamente no Enter; situação emitirá imediatamente. O painel avançado manterá apenas os campos que renderiza e, ao aplicar, combinará seu rascunho com a busca e situação mais recentes. A alternativa de manter slots livres foi rejeitada porque não permite contar, validar, aplicar e resetar campos especializados atomicamente.

### Um único gatilho para o painel avançado

O botão no slot padrão do `UCollapsible` será o único gatilho de abertura. Um watcher sincronizará o rascunho ao abrir e o descartará ao fechar sem aplicar. A busca e a situação não serão duplicadas dentro do painel. O contador do botão refletirá somente campos avançados aplicados; `Limpar` restaurará todos os filtros e emitirá uma única transação.

### Componentes por responsabilidade

`MonitoringModuleTable` continuará sendo a casca pública e preservará `UDashboardPanel`, navbar, navegação, KPIs e alertas. Ele delegará a toolbar, o `UTable`/footer e as ações em massa para componentes próprios. Chamadas fiscais e modais sairão da tabela visual, mas permanecerão no componente compartilhado de bulk para evitar duplicação nas páginas.

Eventos e slots sem consumidores serão removidos. Ações globais de exportação continuarão ausentes do cabeçalho conforme a change de rotas; somente ações contextuais à seleção serão renderizadas.

### Identidade e ciclo da seleção

Cada call site fornecerá `getRowId`. Carteiras usarão `client_id`; Guias usará o ID da guia; Cadastro/Vínculos e Processos usarão o ID da entidade. Quando houver bulk, `getClientId` resolverá e deduplicará clientes separadamente da identidade da linha.

A seleção será limpa quando mudarem Office, rota, página, filtro aplicado ou ordenação. No refresh manual do mesmo contexto, o estado será podado para manter somente IDs ainda presentes. Módulos fora de `FiscalPortfolioModuleKey`, como Cadastro/Vínculos e Processos, não habilitarão bulk fiscal por default.

### Migração sem alterar loaders

`useFiscalModulePortfolio` exporá `filters` e handlers para quick/apply/reset, reutilizando sua transação reativa para produzir uma única carga. O contexto privado de tabela e compatibilidades de infinite scroll sem consumidores serão removidos. Guias, Cadastro/Vínculos e Processos manterão seus loaders atuais, mas adaptarão os filtros especializados ao mesmo contrato e limparão dados imediatamente na troca de Office.

### Testes de componente no gate existente

O Vitest será dividido em projeto Node e projeto Nuxt sob `tests/unit/**`. O projeto Nuxt usará `@nuxt/test-utils`, já instalado, apenas para componentes que dependem de auto-imports/Nuxt UI. Os testes Node continuarão rápidos para utils e contratos puros.

## Risks / Trade-offs

- [Migração ampla de props pode quebrar um call site] → typecheck, testes de superfície e busca final por props/slots antigos.
- [Refresh pode manter seleção de entidade removida] → podar o mapa de seleção contra os IDs da resposta atual.
- [Troca de Office pode mostrar/selecionar dados anteriores] → observar `sessionEpoch`, limpar seleção e, nos loaders próprios, limpar linhas antes da nova carga.
- [Nuxt component tests aumentam o tempo do gate] → manter somente interações essenciais no projeto Nuxt e regras puras no projeto Node.
- [Bulk em lista com várias linhas do mesmo cliente] → identidade por entidade e deduplicação explícita de `client_id` antes da ação.
- [Bilhetagem/mutação acidental] → testes não chamam endpoints reais; capacidades e flags existentes continuam vencendo.

## Migration Plan

1. Adicionar tipos e componentes compartilhados mantendo a forma visual atual.
2. Adaptar o composable de carteira e migrar os módulos padrão.
3. Migrar Guias, Cadastro/Vínculos e Processos e remover os contratos antigos sem consumidores.
4. Atualizar testes unitários/Nuxt e executar todos os gates.

Rollback: restaurar os componentes compartilhados e os bindings antigos; não há migração de dados ou alteração de API.

## Open Questions

Nenhuma.
