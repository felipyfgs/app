# Backup/restore drill — schema operacional Work

**Data:** 2026-07-16  
**Change:** `add-operational-process-management`  
**Ambiente:** stack Docker local (postgres + vault volume)

## Escopo do drill

Inclui:

- Tabelas: `work_departments`, `process_templates`, `process_template_tasks`, `process_generation_batches`, `process_generation_items`, `operational_processes`, `operational_tasks`, `operational_comments`, `operational_task_evidences`, `operational_exports`, `offices.timezone`, `office_user.work_department_id`
- Metadados de evidência (sem bytes em claro no DB)
- Objetos cifrados com `SecureObjectPurpose::OperationalTaskEvidence` no volume `vault_data`

Não inclui: reemissão de ZIP fiscal, cursores NSU, credenciais SERPRO.

## Procedimento executado (2026-07-16)

```bash
# 1) Migrations + seed demo
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --class=OperationalWorkDemoSeeder

# 2) Backup verificável (script compose)
bash docker/ops/backup.sh backups
# Resultado: backups/nfse-backup-20260716T011429Z

# 3) Verificar (quando make disponível)
# make backup-verify BACKUP=backups/nfse-backup-20260716T011429Z

# 4) Contagens pós-seed
docker compose exec php php artisan tinker --execute="
echo 'processes='.\App\Models\OperationalProcess::withoutGlobalScopes()->count().PHP_EOL;
echo 'tasks='.\App\Models\OperationalTask::withoutGlobalScopes()->count().PHP_EOL;
echo 'depts='.\App\Models\WorkDepartment::withoutGlobalScopes()->count().PHP_EOL;
"
```

**Artefato gerado:** `backups/nfse-backup-20260716T011429Z` (Backup concluído).

## Resultado esperado

| Check | Critério |
|-------|----------|
| Manifesto | checksums OK em `make backup-verify` |
| Schema | tabelas work presentes pós-restore |
| Metadados | contagens de processos/tarefas restauradas |
| Cofre | objetos `OPERATIONAL_TASK_EVIDENCE` legíveis só com AAD correta |
| Isolamento | office A não lê office B após restore |

## Restore (destrutivo — só com confirmação)

```bash
make restore BACKUP=backups/nfse-backup-... CONFIRM_RESTORE=SIM
```

**Não executar restore em ambiente com dados reais sem janela de manutenção.**

## Rollback de aplicação (sem apagar dados)

1. Remover/ocultar rotas e nav Work no deploy.
2. Desabilitar `work:cleanup` / jobs de export/geração.
3. Preservar tabelas e objetos do cofre (forward-only após piloto).

## Registro

- Seed: `OperationalWorkDemoSeeder` (CNPJ compartilhado entre alpha/beta)
- Cleanup: `php artisan work:cleanup`
- Strategy: `docs/ops/operational-process-migration-strategy.md`
