## 1. Contratos compartilhados

- [x] 1.1 Definir os tipos controlados de valor/configuração de filtros e helpers de normalização/contagem, cobrindo-os em Vitest unitário.
- [x] 1.2 Adaptar `useFiscalModulePortfolio` para expor filtros controlados e transações quick/apply/reset, removendo o contexto e compatibilidades de infinite scroll sem consumidores.

## 2. Componentes de lista

- [x] 2.1 Refatorar `MonitoringModuleToolbar` para busca/situação rápidas e painel avançado com gatilho único, rascunho controlado e apply/reset atômicos.
- [x] 2.2 Extrair o `UTable`, seleção, visibilidade de colunas, empty state e paginação para componente próprio baseado em `customers.vue`.
- [x] 2.3 Extrair as ações em massa e restringir sua disponibilidade a módulos/capacidades suportados, com identidade de linha e cliente separadas.
- [x] 2.4 Reduzir `MonitoringModuleTable` ao papel de orquestrador preservando navbar, navegação, KPIs, alertas e ordem toolbar→tabela.

## 3. Migração das páginas

- [x] 3.1 Migrar Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Declarações e FGTS para `filters`/`filterConfig` e chaves por cliente.
- [x] 3.2 Migrar Guias com filtro de pagamento controlado, chave única por guia e clientes deduplicados para bulk.
- [x] 3.3 Migrar Cadastro/Vínculos e Processos com status controlado, chave por entidade, limpeza tenant-aware e sem bulk fiscal implícito.

## 4. Testes e verificação

- [x] 4.1 Configurar projetos Vitest Node/Nuxt sob `tests/unit/**` e adicionar testes de interação para collapsible, debounce, apply/reset e filtros especializados.
- [x] 4.2 Cobrir seleção por contexto/refresh, unicidade de Guias e capacidade de ações; reduzir asserts puramente textuais sem perder contratos de fidelidade.
- [x] 4.3 Executar `pnpm run test:gate`, `pnpm run generate`, `pnpm run test:fidelity`, `pnpm run test:artifacts` e `openspec validate refatorar-tabela-filtros-monitoramento --strict`.

## 5. Encerramento

- [x] 5.1 Após aceite da implementação, sincronizar/arquivar a change e commitar no mesmo dia o código, specs principais e archive.
