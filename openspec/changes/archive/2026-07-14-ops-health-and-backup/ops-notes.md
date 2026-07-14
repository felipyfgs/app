# Runbook — backup diário, chave mestra e restore drill

## Escopo

Backup **da instância** (PostgreSQL + cofre cifrado). Não é multi-escritório SaaS.
A `VAULT_MASTER_KEY` **nunca** entra no artefato comum, no manifesto, na API nem nos logs.

## Configuração

| Variável | Default | Notas |
|----------|---------|--------|
| `BACKUP_DISK_ROOT` | `storage/app/backups` | Fora do webroot; modo restrito no host |
| `BACKUP_RETENTION_RUNS` | `7` | Quantos runs de backup manter no disco |
| `BACKUP_SCHEDULE_ENABLED` | `false` | `true` só em ambientes que devem rodar diário |
| `VAULT_DISK_ROOT` | `/var/vault` | Objetos já em envelope cifrado |
| `VAULT_MASTER_KEY` | — | **Custódia offline**, procedimento separado |

Também existe o fluxo host `make backup` / `docker/ops/backup.sh` (manutenção + dump completo + vault).
Os comandos Artisan gravam metadados em `instance_backup_runs` para o painel e a inbox.

Preferência de dump SQL: `pg_dump` no container PHP (`postgresql-client` na imagem).
Se o binário estiver ausente, o comando grava inventário lógico (contagens) e marca o componente — para artefato de produção use `make backup` ou reconstrua a imagem PHP.

## Backup diário (recomendado em produção)

1. Confirme espaço em disco e que `BACKUP_DISK_ROOT` não é webroot.
2. Habilite o scheduler da app **e** `BACKUP_SCHEDULE_ENABLED=true`, **ou** dispare via cron:

   ```bash
   docker compose exec -T php php artisan ops:backup-run --kind=full
   ```

3. Verifique no painel (Home / Administração) o último `SUCCESS` e o alerta de atraso (>24h).
4. Copie o diretório do run para mídia offline **sem** incluir a chave mestra.

## Custódia offline da `VAULT_MASTER_KEY`

- Gere e armazene a chave **fora** do banco, do tarball e de `backups/`.
- Documente versão (`VAULT_MASTER_KEY_VERSION`) junto à chave no cofre físico/HSM/procedimento do escritório.
- Sem a chave, dumps de vault são **irrecuperáveis** (envelope).
- Nunca cole a chave em ticket, chat, manifesto JSON ou variável de CI pública.

## Restore drill (ensaio, não produção destrutiva)

Valida integridade do último artefato **sem** exigir a master key (default):

```bash
docker compose exec -T php php artisan ops:backup-restore-drill --run=latest
# ou
docker compose exec -T php php artisan ops:backup-restore-drill --run=<id>
```

O drill grava `kind=restore_drill` com `SUCCESS`/`FAILED`. Falha de checksum **não** apaga o registro do backup original.

Restore real de produção: use `docs/ops/backup-restore.md` e `make restore` com confirmação explícita, chave mestra do procedimento offline e janela de manutenção.

## O que o painel mostra

- Último backup `SUCCESS`, se está `stale` (>24h) ou `never`.
- Último restore drill (status + horário).
- **Sem** botão de restore na UI; **sem** download anônimo de artefatos.

## Segurança

Proibido em manifesto/API/logs: `VAULT_MASTER_KEY`, PFX, senha A1, PEM, XML fiscal, `vault_object_id`.
