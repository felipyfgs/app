# Drill de backup (pré-schema SVRS NFC-e XML)

**Change:** `add-svrs-nfce-outbound-xml-retrieval` · task 1.2  
**Data:** 2026-07-15T04:12:12Z  
**Ambiente:** local (Docker Compose)

## Procedimento executado

1. `bash ./docker/ops/backup.sh backups`
2. `bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-20260715T041212Z`
3. Reinício de `horizon` e `scheduler` após o backup

## Resultado

| Etapa | Status |
|-------|--------|
| Backup completo | OK — `backups/nfse-backup-20260715T041212Z` |
| `postgres.sql.gz` checksum | OK |
| `vault.tar.gz` checksum | OK |
| Manifesto | Presente (`MANIFEST.txt`, sem `VAULT_MASTER_KEY`) |
| Restore destrutivo em produção | **Não executado** nesta instância viva (runbook em `docs/ops/backup-restore.md`) |

## Conteúdo sanitizado do manifesto

Artefatos presentes: `postgres.sql.gz`, `vault.tar.gz`, `SHA256SUMS`, `MANIFEST.txt`.  
Nenhum CNPJ, chave de acesso, PFX, senha ou XML fiscal foi copiado para este documento.

## Conclusão

Gate de evidência de backup **satisfeito** antes das migrations aditivas do canal SVRS. Restore destrutivo permanece no runbook operacional e deve ser repetido em ambiente de ensaio antes de piloto com dados fiscais reais.
