# Drill de backup (pré-schema MA outbound)

**Change:** `build-ma-outbound-nfe-nfce-capture` · task 1.1  
**Data:** 2026-07-15T02:01:08Z  
**Ambiente:** local (Docker Compose)

## Procedimento executado

1. `bash ./docker/ops/backup.sh backups`
2. `bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-20260715T020108Z`
3. Reinício de `horizon` e `scheduler` após o backup

## Resultado

| Etapa | Status |
|-------|--------|
| Backup completo | OK — `backups/nfse-backup-20260715T020108Z` |
| `postgres.sql.gz` checksum | OK |
| `vault.tar.gz` checksum | OK |
| Manifesto | Presente (`MANIFEST.txt`, sem `VAULT_MASTER_KEY`) |
| Restore destrutivo em produção | **Não executado** nesta instância viva (runbook em `docs/ops/backup-restore.md`) |

## Conclusão

Gate G0 de evidência de backup **satisfeito** com backup + verificação de artefatos. Restore destrutivo permanece no runbook operacional e deve ser repetido em ambiente de ensaio antes de dados fiscais reais de piloto.

Horizon e scheduler foram reiniciados após o drill.
