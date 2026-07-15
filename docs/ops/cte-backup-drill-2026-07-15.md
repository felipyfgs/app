# Drill de backup (pré-schema CT-e completo) — task 1.3

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import`  
**Data:** 2026-07-15T05:32:24Z  
**Ambiente:** local (Docker Compose)

## Procedimento executado

1. `bash ./docker/ops/backup.sh backups`
2. `bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-20260715T053224Z`
3. Horizon e scheduler foram parados pelo script de backup e recolocados com `docker compose start horizon scheduler`

## Resultado

| Etapa | Status |
|-------|--------|
| Backup completo | OK — `backups/nfse-backup-20260715T053224Z` |
| `postgres.sql.gz` checksum | OK |
| `vault.tar.gz` checksum | OK |
| Manifesto | Presente (`MANIFEST.txt`, sem `VAULT_MASTER_KEY`) |
| Restore destrutivo | **Não executado** nesta instância viva (runbook em `docs/ops/backup-restore.md`) |

## Conclusão

Gate de evidência de backup **satisfeito** antes das migrations fiscais desta change (papéis CT-e, qualidade, canal `CTE_AUTXML_DISTDFE`, metadados de projeção). Restore destrutivo permanece no runbook e deve ser ensaiado antes de piloto com dados fiscais reais.
