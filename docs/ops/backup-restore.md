# Backup e restauração

## Componentes

1. **PostgreSQL** — fonte de verdade transacional
2. **Cofre de objetos** (`VAULT_DISK_ROOT`) — PFX/XML criptografados (inclui finalidades SERPRO globais e tenant-scoped: contratante, OAuth, tokens, Termo, A1 do Autor)
3. **`VAULT_MASTER_KEY`** — procedimento **separado**, nunca no mesmo backup do banco

## Pré-condições

- A stack deve estar em execução com `php` e `postgres` saudáveis.
- O destino deve estar em disco protegido, com espaço para o banco e todo o cofre.
- A `VAULT_MASTER_KEY` e sua versão devem estar guardadas em procedimento offline separado. Não copie a chave para `backups/`.

## Backup

```bash
make backup
```

`docker/ops/backup.sh` coloca o Laravel em manutenção, para Scheduler/Horizon caso estejam ativos, cria um dump lógico consistente e lê o cofre diretamente do volume `vault_data`. A aplicação é retomada mesmo se o backup falhar.

Cada diretório `backups/nfse-backup-AAAAMMDDTHHMMSSZ/` contém:

- `postgres.sql.gz`;
- `vault.tar.gz`, somente com objetos já criptografados;
- `SHA256SUMS`;
- `MANIFEST.txt`, que declara explicitamente a ausência da chave mestra.

Diretórios recebem modo `0700` e arquivos, `0600`. Antes de copiar o conjunto para outro meio, mantenha todos esses arquivos juntos.

## Verificação

```bash
make backup-verify BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ
```

Essa etapa valida manifesto, checksums, compactação e caminhos do arquivo do cofre. Faça uma restauração de ensaio antes de armazenar dados fiscais reais e repita o teste periodicamente.

## Restauração

1. Confirme que o `backend/.env` contém a mesma `VAULT_MASTER_KEY` e `VAULT_MASTER_KEY_VERSION` usadas na origem, obtidas do cofre offline.
2. Valide o conjunto com `make backup-verify`.
3. Execute a restauração destrutiva:

   ```bash
   make restore BACKUP=backups/nfse-backup-AAAAMMDDTHHMMSSZ CONFIRM_RESTORE=SIM
   ```

4. O script coloca o Laravel em manutenção, para os processos de aplicação, recria o banco, substitui o conteúdo do volume do cofre e reaplica ownership/permissões privadas.
5. Valide autenticação, `php artisan about` e a leitura de um objeto de teste antes de liberar usuários e workers.

Se a restauração falhar, os serviços de aplicação permanecem parados e o modo de manutenção continua ativo para evitar operação sobre um estado parcial. Corrija a causa, repita a restauração e só então execute `docker compose start php`, `docker compose exec php php artisan up` e os demais serviços.

Sem a chave mestra, os objetos do cofre são irrecuperáveis.
