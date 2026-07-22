## Why

A visão em cards/accordion de Tarefas (Lista) e Processos ficou densa e inconsistente com o restante do painel. O padrão canônico de listas N1 é `ShellDataTable` (UTable + cards mobile), já usado em Modelos e catálogos.

## What Changes

- Visão **Lista** de Tarefas passa a usar `ShellDataTable` (não mais `WorkTaskListView` em cards).
- Página **Processos** passa a usar `ShellDataTable` no lugar do accordion/cards (`WorkProcessAccordionList`).
- Manter Fila (mestre–detalhe) em Tarefas; detalhe canônico via slideover na Lista e links explícitos em Processos.
- Atualizar gates/testes de migração shell e orquestração Work.

Non-goals: mudança de API; redesenhar Fila inbox; expansão inline de tarefas na linha do processo (detalhe canônico cobre).

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `work-tasks-queue-views`: Lista MUST usar `ShellDataTable`.
- (contrato Processos nesta change): listagem tabular via `ShellDataTable` com link ao detalhe canônico.

## Impact

- `WorkQueueWorkspace.vue`, remoção de `WorkTaskListView.vue`
- `apps/web/app/pages/work/processes/index.vue`, deprecar/remover uso de `WorkProcessAccordionList.vue`
- Testes unitários / gates shell + painel

### Dependências entre changes

- Nível: **C0** (emenda da change `adicionar-visao-lista-tarefas` já aplicada)
- Depende de: nenhuma change bloqueante
- Relação: emenda coordenada do artefato ativo
