# Harness de migrations, reconciliação e versionamento de jobs (fase 2)

## Componentes

| Peça | Local |
|------|--------|
| Config / flags de corte | `backend/config/fiscal_data_model.php` |
| Cutover adapter | `App\Support\FiscalDataModel\FiscalModelCutover` |
| Pré-condições de migration | `App\Support\FiscalDataModel\MigrationPrecondition` |
| Backfill | `fiscal-model:backfill` + `FiscalModelBackfillService` |
| Reconcile | `fiscal-model:reconcile` + `FiscalModelReconcileService` |
| Tabelas | `fiscal_model_migration_maps`, `fiscal_model_backfill_checkpoints` |
| Migration | `2026_07_16_400000_create_fiscal_model_migration_harness_tables.php` |

## Flags por agregado

- `write_canonical` (default **true**): escritas no serviço canônico  
- `read_canonical` (default **false**): corte de leitura  
- `shadow_verify` (default **false**): comparação legado×canônico  
- `FISCAL_MODEL_KILL_SWITCH=true`: força leitura legado **sem** apagar escritas novas  
- Coorte: `*_OFFICE_ALLOWLIST` / `*_ALLOW_ALL_OFFICES` (leitura/shadow exigem coorte explícita)

Agregados: `tenancy_cadastro`, `documentos_cursores`, `outbound`, `serpro`, `monitoramento_guias`.

## Comandos

```bash
php artisan fiscal-model:backfill tenancy_cadastro --dry-run --json
php artisan fiscal-model:backfill tenancy_cadastro --json
php artisan fiscal-model:reconcile --json
php artisan fiscal-model:reconcile tenancy_cadastro --json
```

Exit code ≠ 0 se rejeições/ambiguidades (backfill) ou divergências não aprovadas (reconcile).

## Versionamento de jobs (2.7)

- Config: `fiscal_data_model.job_payload_version` (env `FISCAL_MODEL_JOB_PAYLOAD_VERSION`, default 1).
- Helper: `FiscalModelCutover::versionJobPayload($array)` adiciona `fiscal_model_payload_version`.
- Antes de cada corte de agregado: drenar filas Horizon do canal afetado **ou** adaptar consumer para aceitar versão atual e legada.
- Job sem versão (`jobPayloadIsCurrent(null) === false`) deve ser re-enfileirado ou tratado como legado.

### Estratégia de drenagem

1. Pausar scheduler do fluxo afetado  
2. `horizon:status` / filas vazias no canal  
3. Bump `job_payload_version` se o contrato do payload mudou  
4. Retomar scheduler com writers canônicos  

## Testes PostgreSQL (2.1 / 2.4)

Default CI usa SQLite in-memory. Para suíte estrutural:

```bash
# Exemplo — DB de teste isolado no Postgres do compose
DB_CONNECTION=pgsql DB_DATABASE=nfse_test \
  php artisan test --filter=FiscalModel
```

Criar `nfse_test` vazio antes do migrate-from-zero. Upgrade path: restaurar dump sanitizado do schema baseline e rodar migrations pendentes.

## Migrations novas (2.5)

Usar apenas:

```php
MigrationPrecondition::tableExists(...);
MigrationPrecondition::columnMissing(...);
```

Proibido em código **novo** desta change: `if (Schema::hasTable)` / `hasColumn` / `try-catch` que engole divergência.
