# Estratégia de migration — módulo operacional

**Change:** `add-operational-process-management`  
**Data:** 2026-07-16

## Migrations desta change

| Arquivo | Conteúdo |
|---------|----------|
| `2026_07_16_300000_add_office_timezone_for_operations` | `offices.timezone` + backfill `America/Sao_Paulo` |
| `2026_07_16_300100_create_work_departments_and_membership_link` | `work_departments` + `office_user.work_department_id` |
| `2026_07_16_300200_create_process_templates_tables` | modelos e tarefas padrão |
| `2026_07_16_300300_create_process_generation_tables` | batches/itens de preview |
| `2026_07_16_300400_create_operational_processes_and_tasks` | processos/tarefas + unique parcial TEMPLATE |
| `2026_07_16_300500_create_operational_comments_evidences_exports` | comentários, evidências, export CSV |

Todas as tabelas de conteúdo têm `office_id` obrigatório (plano de dados).

## Rollback

### Ambiente sem dados reais (dev/CI)

`php artisan migrate:rollback --step=6` reverte integralmente o schema desta change.

### Após piloto / com evidências

**Forward-only.** Não rodar down em produção:

1. Desabilitar rotas/nav/jobs operacionais no deploy.
2. Preservar tabelas e objetos do cofre (`OPERATIONAL_TASK_EVIDENCE`).
3. Correções de schema apenas por migrations aditivas.

Nunca apagar automaticamente evidências ou auditoria no rollback de aplicação.
