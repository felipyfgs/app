## 1. N0 — Rotas e modelo de apresentação

- [x] 1.1 Criar transformações puras para KPIs, progresso/departamentos e migração das queries legadas, com testes Vitest de valores, deep links e casos inválidos
  - Depende de: `adicionar-visao-lista-tarefas` marco `apply`, `restaurar-fila-tarefas-mestre-detalhe` marco `apply`
- [x] 1.2 Transferir a fila sem seleção para `/work/tasks`, atualizar o path-base do composable e distinguir Visão geral/Tarefas na navegação, com testes de rotas canônicas
  - Depende de: `adicionar-visao-lista-tarefas` marco `apply`, `restaurar-fila-tarefas-mestre-detalhe` marco `apply`

## 2. N1 — Dashboard estratégico

- [x] 2.1 Implementar `/work` com snapshot de `work/kpis`, departamentos, estados loading/erro/stale/retry e invalidação por `sessionEpoch`
  - Depende de: 1.1, 1.2
- [x] 2.2 Compor KPIs acionáveis, execução por departamento, prioridades, processos sem responsável, empty states e layout responsivo no shell Nuxt UI
  - Depende de: 1.1, 1.2
- [x] 2.3 Atualizar deep links do cockpit Início e demais superfícies Work, e adicionar gate Vitest da página/compatibilidade
  - Depende de: 1.1, 1.2

## 3. N2 — Gates integrados

- [x] 3.1 Executar `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity` e `pnpm run test:artifacts` em `apps/web`
  - Depende de: 2.1, 2.2, 2.3
- [x] 3.2 Executar `npx @fission-ai/openspec@1.6.0 validate adicionar-dashboard-estrategico-trabalho --strict` e auditar os requisitos contra a rota renderizada
  - Depende de: 2.1, 2.2, 2.3

## 4. N3 — Alinhamento à referência executiva

- [ ] 4.1 Reorganizar `/work` em desempenho geral, nível operacional, situação das tarefas e acessos rápidos, preservando dados reais, shell e estados existentes
  - Depende de: 2.1, 2.2
- [ ] 4.2 Transformar o acompanhamento por departamento em quadro compacto desktop e cards mobile, com cálculo testado do nível operacional e sem overflow
  - Depende de: 4.1
- [ ] 4.3 Executar os gates web completos, validação OpenSpec e inspeção visual desktop/mobile em light/dark
  - Depende de: 4.1, 4.2
