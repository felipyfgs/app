# Drill de backup (pré plano de controle — hub fiscal completo)

**Change:** `build-complete-fiscal-monitoring-hub` · task 1.5  
**Data:** 2026-07-15T18:45:33Z  
**Ambiente:** local (Docker Compose)

## Procedimento executado

1. `bash ./docker/ops/backup.sh backups`
2. `bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-20260715T184533Z`
3. `docker compose start horizon scheduler` (script de backup interrompe workers)
4. Tentativa `php artisan ops:backup-run --kind=full` via container `php` — **falhou** (`pg_dump` indisponível no image PHP; evita SUCCESS falso — comportamento esperado do `InstanceBackupService`)
5. `php artisan ops:backup-restore-drill --run=latest` — **SUCCESS** (valida manifesto/checksums de artefato já registrado em `instance_backup_runs`; drill sem master key)

## Resultado

| Etapa | Status |
|-------|--------|
| Backup completo (script Docker) | OK — `backups/nfse-backup-20260715T184533Z` |
| `postgres.sql.gz` checksum | OK |
| `vault.tar.gz` checksum | OK |
| Manifesto | Presente (`MANIFEST.txt`, `chave_mestra_incluida=nao`) |
| `ops:backup-run` (artisan no container php) | **FALHOU** — `pg_dump` ausente no container da app; use `docker/ops/backup.sh` em local |
| `ops:backup-restore-drill --run=latest` | OK (drill de metadados/checksums) |
| Restore destrutivo | **Não executado** nesta instância viva (runbook em `docs/ops/backup-restore.md`) |

## Preflight multi-tenant (task 1.4)

Após o drill:

```bash
docker compose exec -T php php artisan ops:preflight-tenant-isolation
# ou --json / --fail-on-issues
```

Resultado local 2026-07-15: **sem bloqueios** (avisos: offices sem membership ativa, `office_id` nulo em colunas nullable de auditoria, scan de vault limitado sem catálogo central, refs vault nulas em colunas opcionais).

## Conclusão

Gate de evidência de backup **satisfeito** com backup + verificação de artefatos **antes** das migrations do plano de controle do hub fiscal (assinaturas, memberships de plataforma, contrato SERPRO, etc.).

- Preferir **`docker/ops/backup.sh` + `restore.sh --verify-only`** no ambiente Compose local.
- `ops:backup-run` / `ops:backup-restore-drill` dependem de `pg_dump`/artefatos no path configurado (`BACKUP_DISK_ROOT`); no container `php` atual o dump completo deve usar o script ops.
- Restore destrutivo permanece no runbook operacional e deve ser repetido em ensaio antes de dados fiscais reais de piloto SERPRO.

## Comandos de referência

```bash
# Backup + verify-only (recomendado local)
bash ./docker/ops/backup.sh backups
bash ./docker/ops/restore.sh --verify-only backups/nfse-backup-<timestamp>

# Artisan (quando pg_dump/cliente DB estiverem no PATH do runtime)
docker compose exec -T php php artisan ops:backup-run --kind=full
docker compose exec -T php php artisan ops:backup-restore-drill --run=latest

# Preflight isolamento
docker compose exec -T php php artisan ops:preflight-tenant-isolation --json --fail-on-issues
```
