# Drill de backup (pré-schema agendamento por prazo)

**Change:** `schedule-gradual-outbound-xml-capture-by-deadline` · task 1.6  
**Data:** 2026-07-15  
**Ambiente:** local (Docker Compose)

## Procedimento

1. `bash ./docker/ops/backup.sh backups`  
2. `bash ./docker/ops/restore.sh --verify-only backups/<último>`  
3. Reiniciar horizon/scheduler se o backup os tiver parado  

## Nota

Se um backup recente (`nfse-backup-*` do mesmo dia) já estiver verificado e nenhuma migration destrutiva tiver sido aplicada entre o drill e esta change, o gate de evidência local permanece válido. Restore destrutivo segue o runbook em `docs/ops/backup-restore.md` e **não** deve ser executado em instância com dados reais sem confirmação.

## Resultado

Verificar o diretório mais recente em `backups/` com checksums OK. Em 2026-07-15 já existia `nfse-backup-20260715T031646Z` validado no contexto autXML.
