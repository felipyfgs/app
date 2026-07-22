## 1. N0 — OpenSpec e contratos API

- [x] 1.1 Artefatos OpenSpec da change (proposal/design/specs/tasks)
- [x] 1.2 Expandir `OperationalWorkBulkService` + controller/validation/policy por item + Feature tests
- [x] 1.3 `POST /work/processes/bulk` archive + Feature tests
- [x] 1.5 Expandir process bulk (`assign` / `set_department` / `set_due_date`) + Feature tests + modal UI
  - Depende de: 1.3
- [x] 1.4 `sort`/`direction` em `OperationalQueueQuery` + testes

## 2. N1 — Cliente e componentes

- [x] 2.1 Expor bulk tasks/processes e sort params em `createWorkApi` / filtros
  - Depende de: 1.2, 1.3, 1.4
- [x] 2.2 `WorkTaskStatusSelect.vue` + `WorkBulkActionsModal.vue`
  - Depende de: 2.1

## 3. N1 — Telas

- [x] 3.1 Processos: seleção, BulkActionBar, modal, sort URL, status na expansão
  - Depende de: 2.2
- [x] 3.2 Tarefas Lista: seleção, modal, sort URL, status inline
  - Depende de: 2.2

## 4. N2 — Gates

- [x] 4.1 Vitest/gates web + Feature API tocados + `openspec validate --changes --strict`
  - Depende de: 3.1, 3.2
