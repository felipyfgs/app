## 1. N0 — Contrato (já feito)

- [x] 1.1 Query `view` fila|lista
- [x] 1.2 Testes parse/serialize

## 2. N1 — ShellDataTable Tarefas + Processos

- [x] 2.1 Substituir `WorkTaskListView` por `ShellDataTable` na visão Lista de `WorkQueueWorkspace`
- [x] 2.2 Substituir `WorkProcessAccordionList` por `ShellDataTable` em `/work/processes`
- [x] 2.3 Remover `WorkTaskListView.vue` e `WorkProcessAccordionList.vue`; atualizar gates
  - Depende de: 2.1, 2.2

## 3. N2 — Gates

- [x] 3.1 `pnpm exec vitest` nos testes tocados + eslint arquivos
  - Depende de: 2.3
  - Evidência: vitest exit 0; eslint exit 0
- [x] 3.2 `openspec validate --changes --strict`
  - Depende de: 3.1
