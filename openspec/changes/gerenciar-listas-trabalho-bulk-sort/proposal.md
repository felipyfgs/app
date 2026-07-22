## Why

Nas listas tabulares de Processos e Tarefas (visão Lista) falta gestão operacional densa: seleção em massa, ordenação real por coluna e mudança rápida de status — o operador precisa abrir cada detalhe para ações rotineiras.

## What Changes

- Expandir `POST /work/tasks/bulk` para ações completas (start/complete/resume/block/claim/assign/due/department) com auth por item e relatório parcial.
- Adicionar `POST /work/processes/bulk` (archive, assign, set_department, set_due_date).
- Aceitar `sort`/`direction` na fila `GET /work/queue`.
- UI: multiseleção + modal de ações em massa + `sortHeader` com URL sync + select de status inline (Processos expansão e Tarefas Lista).
- Fila (chat) permanece sem bulk nesta change.

Non-goals: redesign da Fila inbox; bulk dispense/reopen para não-ADMIN; exclusão física de processos; unarchive; mudar status/competence/client_id do processo em massa; sortHeader sem API.

## Capabilities

### New Capabilities

- `work-list-management`: gestão tabular de processos/tarefas (bulk, sort server-side, status inline).

### Modified Capabilities

- `shell-datatable-sort-contract`: listas Work fora de `/monitoring` sincronizam `sort`/`direction` na query (já coberto pelo requisito genérico; delta só se precisar citar Work explicitamente — preferir capability nova).

## Impact

- API: `OperationalWorkBulkService`, novo bulk de processos, `OperationalQueueQuery`, routes, policies, Feature tests.
- Web: `createWorkApi`, `WorkBulkActionsModal`, `WorkTaskStatusSelect`, `processes/index.vue`, `WorkQueueWorkspace` (Lista), `useWorkQueueFilters`.

### Dependências entre changes

- Nível: **C0**
- Depende de: nenhuma change bloqueante
- Bases estáveis: ShellDataTable, BulkActionBar, listas Work atuais
- Desbloqueia: implementação full-stack da gestão em lista
