# Drill de backup (pré-schema autXML / import em massa)

**Change:** `add-office-autxml-and-bulk-xml-import` · task 1.5  
**Data:** 2026-07-15T03:16:46Z  
**Ambiente:** local (Docker Compose)

## Procedimento executado

1. `bash ./docker/ops/backup.sh backups`
2. `bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-20260715T031646Z`
3. Horizon e scheduler são parados pelo script de backup e devem ser recolocados em operação pelo Compose

## Resultado

| Etapa | Status |
|-------|--------|
| Backup completo | OK — `backups/nfse-backup-20260715T031646Z` |
| `postgres.sql.gz` checksum | OK |
| `vault.tar.gz` checksum | OK |
| Manifesto | Presente (`MANIFEST.txt`, sem `VAULT_MASTER_KEY`) |
| Restore destrutivo | **Não executado** nesta instância viva (runbook em `docs/ops/backup-restore.md`) |

## Conclusão

Gate de evidência de backup **satisfeito** com backup + verificação de artefatos **antes** das novas tabelas de identidade fiscal do escritório, cursor autXML, quarentena e importação em lote. Restore destrutivo permanece no runbook operacional e deve ser repetido em ensaio antes de dados fiscais reais de piloto (task 13.1).

## Atualização task 13.1 (2026-07-15)

- Migrations de autXML/import **aplicadas** em ambiente local com `SEFAZ_AUTXML_DISTDFE_ENABLED=false`.
- Tabelas verificadas presentes: identidades, credentials, cursores, batches, quarantine.
- Backup **pós-schema** + verify-only: `backups/nfse-backup-20260715T114027Z` (`postgres.sql.gz` OK, `vault.tar.gz` OK, manifesto sem master key).
- Restore destrutivo **não** executado na instância viva.
- Ver status consolidado de piloto: `docs/ops/autxml-pilot-gates-2026-07-15.md`.
