## Context

Após o toggle Fila|Lista, a Lista usava cards custom e Processos usava accordion — ambos rejeitados na prática. O kit do painel já padroniza listas em `ShellDataTable`.

## Goals / Non-Goals

**Goals:**
- Lista de Tarefas e página de Processos em `ShellDataTable` (Nuxt UI `UTable` + mobile cards do shell).
- Mesmos filtros/paginação; Fila intacta.
- Row/action → detalhe canônico (`/work/tasks/:id`, `/work/processes/:id`).

**Non-Goals:**
- Expandir tarefas inline na tabela de processos.
- Ordenação server-side nova (só se já existir).
- Alterar contratos HTTP.

## Decisions

### 1. `ShellDataTable` nos dois lugares
- Preset `monitoring-compact` (como Modelos).
- `primaryColumnId` / `statusColumnId` / `summaryColumnIds` para mobile cards.

### 2. Tarefas Lista
- Colunas: tarefa, status, prazo, cliente/processo, responsável.
- `@select` / clique na linha → `selectTask` + slideover.
- Footer nativo do `ShellDataTable` (remover footer duplicado do panel).

### 3. Processos
- Colunas: processo, cliente, status, progresso, prazo, responsável, ações.
- Ações: abrir processo, monitoramento, cliente.
- Remover uso do accordion; arquivo pode permanecer órfão só se ainda referenciado — preferir deixar de importar e ajustar gates.

### 4. Spec
- Atualizar `work-tasks-queue-views` e gates que exigiam accordion/cards.

## Risks / Trade-offs

- [Perda da expansão inline] → Mitigado por link ao detalhe + contagem de tarefas na coluna.
- [Gates antigos] → Atualizar na mesma change.

## Migration Plan

- Só frontend; rollback reverte os dois componentes/páginas.
