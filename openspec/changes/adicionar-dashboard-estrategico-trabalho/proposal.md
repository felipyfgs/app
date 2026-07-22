## Why

A rota `/work` abre diretamente a fila de tarefas, mas a liderança do escritório não possui uma visão consolidada para avaliar volume, risco, progresso e distribuição do trabalho. Os KPIs locais já existem; falta transformá-los em uma superfície estratégica, acionável e coerente com o dashboard Nuxt UI do produto.

## What Changes

- Tornar `/work` a visão estratégica de Trabalho, com KPIs, progresso por departamento, prioridades de risco, processos sem responsável e atalhos para as superfícies operacionais.
- Mover a fila de tarefas para a rota canônica `/work/tasks`, preservando `/work/tasks/{id}` para o detalhe e filtros na query string.
- Redirecionar URLs legadas de fila em `/work?tab=...`, `/work?view=...` ou `/work?task=...` para `/work/tasks`, sem perder filtros ou seleção.
- Atualizar navegação e deep links internos para separar claramente Visão geral, Tarefas, Processos, Calendário e Modelos.
- Consumir exclusivamente `GET /api/v1/work/kpis`, sem criar métricas fictícias nem provocar egress fiscal.
- Refinar a composição conforme a referência executiva fornecida: desempenho geral em destaque, nível operacional, quadro compacto por departamento, situação das tarefas e acessos rápidos, sem copiar o shell externo nem inventar período histórico.

Non-goals: alterar o cálculo ou o contrato HTTP dos KPIs; criar gráficos históricos sem série temporal disponível; realizar mutações fiscais ou SERPRO live; habilitar flags/canais SEFAZ; adicionar `mei` ao Compose; implementar operações de backup/restore indisponíveis.

## Capabilities

### New Capabilities

- `work-strategic-dashboard`: visão estratégica autenticada de processos e tarefas em `/work`, com navegação acionável e compatibilidade das URLs legadas da fila.

### Modified Capabilities

- Nenhuma.

## Impact

- Web Nuxt: nova composição de `/work`, nova entrada `/work/tasks`, navegação Work, deep links de KPIs, refinamento visual executivo e testes Vitest/gates de fidelidade.
- API e persistência: sem mudança; reutiliza o read model tenant-scoped de `/api/v1/work/kpis`.
- Dependências: nenhuma biblioteca nova.

### Dependências entre changes

- Nível: `C1`.
- Bases estáveis: endpoint local `GET /api/v1/work/kpis`, shell do dashboard e contratos Work já implementados.
- Depende de: `adicionar-visao-lista-tarefas` e `restaurar-fila-tarefas-mestre-detalhe` — contrato da fila e detalhe, marco `apply`, relação `coordenada`.
- Capability/contrato e marco exigido: fila dual e mestre–detalhe já aplicados antes da troca da rota-base.
- Desbloqueia: futuras análises históricas e filtros estratégicos de Trabalho.
- Paralelismo: pode avançar em paralelo a changes fiscais e de comunicação; mudanças concorrentes em navegação, `WorkQueueWorkspace` ou cockpit Início devem preservar os novos caminhos canônicos.
