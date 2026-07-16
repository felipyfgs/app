# Contratos de URL (1.8)

Office sempre da sessão Sanctum — **nunca** `office_id` na query.

## `/work` (fila)

| Param | Valores | Default | Notas |
|-------|---------|---------|-------|
| `tab` | `open` \| `hoje` \| `atrasadas` \| `semana` \| `impedidas` \| `concluidas` | `open` (omitido) | Server-side via QueueBucket |
| `task` | id numérico | — | Seleção; removido se item sair do filtro |
| `q` | string | — | Busca título/processo |
| `department_id` | int | — | |
| `assignee_membership_id` | int | — | |
| `client_id` | int | — | |
| `scope` | `default` \| `office` | `default` | OPERATOR |
| `page` | int ≥1 | 1 (omitido se 1) | |
| `per_page` | 1–100 | 25 | |

Valores vazios omitidos. Troca de filtro reinicia `page` e pode limpar `task`.

## `/work/calendar`

| Param | Valores | Default |
|-------|---------|---------|
| `view` | `month` \| `week` \| `day` | `month` (omitido) |
| `date` | `Y-m-d` | hoje civil (cliente até API devolver timezone) |
| `department_id`, `assignee_membership_id`, `client_id`, `status`, `risk` | filtros server-side | — |
| `page` | visão Dia | 1 |

## `/work/processes`

| Param | Default |
|-------|---------|
| `q`, `competence`, `status`, `risk`, `department_id`, `assignee_membership_id`, `client_id` | — |
| `page`, `per_page` | 1 / 25 |

## `/work/processes/{id}`

| Param | Valores |
|-------|---------|
| `section` | `resumo` \| `tarefas` \| `comentarios` \| `historico` (default `resumo`) |

Voltar para lista preserva query de `/work/processes` via `from` opcional ou history.

## `/work/templates`

| Param | Default |
|-------|---------|
| `q`, `is_active`, `page`, `per_page` | — / 1 / 25 |
| `step`, `batch_id`, `template_id` | fluxo de geração (quando ativo) |
