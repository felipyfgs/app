# Baseline — complete-operational-workspace-ui-and-demo-fixtures

Registrado em 2026-07-15 antes das alterações desta change.

## Contagens office `demo` (slug `demo`, id=1)

| Entidade | Contagem |
|----------|---------:|
| OperationalProcess | 0 |
| OperationalTask | 0 |
| ProcessTemplate | 0 |
| WorkDepartment | 0 |
| OperationalComment | 0 |
| OperationalTaskEvidence | 0 |
| Client (catálogo demo) | 21 |

Usuários de sessão: `admin@example.com`, `operador@example.com`, `viewer@example.com` com membership no office `demo`.

## Payloads `/api/v1/work/*` (estado pré-change)

- `GET /queue` — lista paginada server-side (`tab`, `department_id`, `assignee_membership_id`, `client_id`, `q`, `scope`, `page`, `per_page`); enriquece `bucket`, `risks`, `effective_due_date`, dept/assignee/process.
- `GET /tasks/{id}` — detalhe com evidences/comments; `vault_object_id` omitido.
- `GET /processes` / `GET /processes/{id}` — lista/detalhe tenant-scoped.
- `GET /templates` — modelos com tasks.
- `GET /calendar?from&to` — agregados diários `{date,total}` apenas; sem risco/itens tipados por intervalo.
- `GET /calendar/day?date` — itens do dia sem filtros dept/risco/cliente.
- `GET /kpis` — totais globais + `by_department: [{work_department_id,total}]` (apenas abertas); sem breakdown concluídas/atrasadas/multa/sem responsável por dept.

## Screenshots

Baselines visuais serão capturados em 12.7/12.8 com âncora fixa após o seed. Pré-change: filas vazias em `/work`, calendário sem dias, processos/modelos vazios.

## Seeder pré-existente

`OperationalWorkDemoSeeder` criava offices `demo-work-alpha` / `demo-work-beta`, **não** o office `demo`, e **não** era chamado por `DatabaseSeeder`.
