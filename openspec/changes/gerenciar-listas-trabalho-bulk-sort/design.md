## Context

Listas Work já usam `ShellDataTable`. Bulk de tarefas existe mas é ADMIN-only e cobre pouco. Fila não ordena por coluna do usuário. Processos já aceitam sort na API sem UI.

## Goals / Non-Goals

**Goals:** bulk completo de tarefas + bulk de processos (archive + assign/department/due_date); sort fila/processos; status inline; modal único de ações em massa; auth por item nas transições.

**Non-Goals:** Fila chat com checkboxes; exclusão física de processos; unarchive; status/competence/client_id do processo em massa; dispense/reopen em massa para não-ADMIN.

## Decisions

1. **Bulk tasks** — estender `OperationalWorkBulkService` com `action` + campos condicionais; response `{ data, meta: { succeeded, failed } }` para falha parcial (evidência).
2. **Auth** — authorize cada item com policy de transição/claim; dispense/reopen só ADMIN.
3. **Bulk processes** — `POST /work/processes/bulk` com `archive` | `assign` | `set_department` | `set_due_date`; auth por item (`archive` vs `update`); sem delete físico nem mudança de status derivado.
4. **Queue sort** — whitelist pós-enrich; default bucket quando omitido.
5. **UI** — `BulkActionBar` + `WorkBulkActionsModal`; seleção de tarefas na expansão de processos; `WorkTaskStatusSelect` nas linhas.
6. **URL sort** — sync `sort`/`direction` (Work fora de monitoring).

## Risks / Trade-offs

- [Falha parcial no lote] → meta.failed com motivo; toast resume.
- [409 lock] → item falha; demais seguem quando possível dentro da transaction por item ou aborta item.

## Migration Plan

Só API+web; rollback reverte endpoints novos e UI.
