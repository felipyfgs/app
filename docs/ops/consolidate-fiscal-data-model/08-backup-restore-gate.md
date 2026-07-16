# 1.8 Backup e restore coordenado

## Procedimento oficial

Fonte: `docs/ops/backup-restore.md` e `make backup` / `docker/ops/backup.sh`.

Componentes:

1. PostgreSQL (`postgres.sql.gz`)
2. Cofre de objetos (`vault.tar.gz`) — ciphertext apenas
3. **`VAULT_MASTER_KEY` fora do pacote** (procedimento offline)

## Pré-condições desta change

Antes de qualquer migration aditiva destrutiva de autoridade (fases 3+):

| Gate | Critério | Status local 2026-07-15 |
|------|----------|-------------------------|
| Stack saudável | postgres/php healthy | **OK** (compose up) |
| Backup executável | `make backup` gera manifesto + SHA256SUMS | **A executar na janela de apply** |
| Verify | `make backup-verify BACKUP=...` | **A executar** |
| Restore isolado | restore em instância/DB separado + smoke jornadas mínimas | **A executar** |
| Chave mestra | não presente em `backups/` | **Política confirmada na doc** |

## Evidência a anexar no apply real

Preencher na execução (não inventar):

```
BACKUP_DIR=
POSTGRES_SHA256=
VAULT_SHA256=
VERIFY_RESULT=
RESTORE_TARGET=
RESTORE_RESULT=
SMOKE_JOURNEYS= J1,J4,J6,J10
OPERATOR=
TIMESTAMP=
```

## Escopo mínimo de smoke pós-restore

1. Login + office context  
2. Listar clients do office  
3. Abrir documento / sha256 bate com baseline  
4. Dashboard fiscal carrega KPIs do office  
5. Nenhum segredo em claro nos logs do restore  

## Decisão 1.8 nesta sessão

- **Procedimento documentado e reafirmado** como gate bloqueante.
- **Ensaio completo backup→restore isolado** exige janela operacional e disco; fica como **checkpoint obrigatório** antes da fase 3 (migrations aditivas em dados reais) e novamente na 10.3.
- Para desenvolvimento local da harness (fase 2), dumps lógicos pontuais do schema (`pg_dump --schema-only`) são suficientes e **não substituem** o gate de restore.

### Comando de schema-only de apoio (dev)

```bash
docker exec -e PGPASSWORD=change-me app-postgres-1 \
  pg_dump -U nfse -d nfse --schema-only --no-owner \
  > "docs/ops/consolidate-fiscal-data-model/raw/schema-$(date -u +%Y%m%dT%H%M%SZ).sql"
```
