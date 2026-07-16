# 1.2 Inventário de migrations

**Data:** 2026-07-15  
**Ambiente local:** `php artisan migrate:status` via `app-php-1`

## Resumo

| Status | Quantidade |
|--------|------------|
| Ran | 48 |
| Pending | 7 |
| Arquivos em `backend/database/migrations` | 55 |

> Há **55 arquivos** e **55 nomes** no status (48+7). Duas migrations compartilham o timestamp `2026_07_15_060000_*` (outbound deadline + cte extend) — ambas Ran.

## Pendentes (não aplicar às cegas nesta change)

| Migration | Origem provável | Ação recomendada antes da consolidação |
|-----------|-----------------|----------------------------------------|
| `2026_07_16_100100_add_serpro_usage_request_tag_and_route` | align-serpro | **Revisar redundância** com `100000` (colunas `request_tag`/`functional_route`/`is_simulated` já existem no PG). Preferir migration no-op explícita ou pré-condição com diagnóstico (task 2.5). |
| `2026_07_16_300000_add_office_timezone_for_operations` | operational processes | Fora do agregado canônico fiscal; pode rodar em paralelo **depois** do inventário, sem misturar no backfill fiscal. |
| `2026_07_16_300100_create_work_departments_and_membership_link` | operational | Idem |
| `2026_07_16_300200_create_process_templates_tables` | operational | Idem |
| `2026_07_16_300300_create_process_generation_tables` | operational | Idem |
| `2026_07_16_300400_create_operational_processes_and_tasks` | operational | Idem |
| `2026_07_16_300500_create_operational_comments_evidences_exports` | operational | Idem |

### Resolução adotada (1.2)

1. **Congelar baseline schema** = estado Ran atual (48 migrations), documentado em `schema-dictionary.md`.
2. **Não reescrever** histórico Ran.
3. **Migrations condicionais históricas** (lista abaixo) permanecem no histórico; **novas** migrations desta change **proíbem** `hasTable`/`hasColumn`/`try-catch` silencioso (task 2.5).
4. **Redundância 100100:** registrar como débito a resolver na fase 2/6 (pré-condição explícita: se coluna existe, abortar com mensagem ou no-op versionado auditável — nunca silencioso em código novo).
5. **Pending 300x:** não bloqueiam o inventário; se forem aplicadas em outro ambiente antes do apply desta change, **regenerar** dicionário (1.3) e baseline (1.6).

## Migrations Ran (ordem de batch local = 1)

```
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_07_13_230904_add_two_factor_columns_to_users_table
2026_07_13_230904_create_personal_access_tokens_table
2026_07_13_231000_create_offices_and_memberships_tables
2026_07_13_232000_create_clients_and_establishments_tables
2026_07_13_233000_create_client_credentials_table
2026_07_13_234000_create_sync_and_documents_tables
2026_07_13_235000_create_exports_table
2026_07_14_000100_create_audit_logs_table
2026_07_14_120000_expand_client_registration_schema
2026_07_14_130000_create_client_custom_fields_table
2026_07_14_140000_client_one_cnpj_drop_root_unique
2026_07_14_150000_add_matrix_client_id_to_clients
2026_07_14_160000_add_tax_regime_to_clients
2026_07_14_170000_create_instance_backup_runs_table
2026_07_14_180308_enrich_nfse_notes_projection_fields
2026_07_14_210000_create_channel_sync_cursors_and_nfe_projections
2026_07_14_240000_add_direction_to_fiscal_projections
2026_07_15_010000_create_cte_mdfe_projections
2026_07_15_020000_create_mdfe_documents_table
2026_07_15_030000_create_ma_outbound_capture_tables
2026_07_15_040000_create_office_autxml_and_import_tables
2026_07_15_050000_create_svrs_nfce_xml_recovery_tables
2026_07_15_060000_add_outbound_deadline_scheduling_schema
2026_07_15_060000_extend_cte_roles_quality_and_office_cursor
2026_07_15_061000_create_office_integration_tokens_table
2026_07_15_120000_create_svrs_egress_cohort_and_extend_attempts
2026_07_15_200000_create_platform_memberships_and_office_subscriptions
2026_07_15_200100_add_selected_office_id_to_users_table
2026_07_15_210000_create_serpro_contracts_table
2026_07_15_210100_create_serpro_service_catalog_table
2026_07_15_210200_create_office_serpro_authorizations_table
2026_07_15_210300_create_tax_proxy_powers_table
2026_07_15_220000_create_serpro_api_usage_ledger_tables
2026_07_15_230000_create_fiscal_monitoring_core_tables
2026_07_15_240000_create_simples_mei_projection_tables
2026_07_15_241000_create_dctfweb_mit_tables
2026_07_15_242000_create_tax_installment_monitoring_tables
2026_07_15_243000_seed_sitfis_emit_operation_catalog
2026_07_15_244000_create_mailbox_tables
2026_07_15_245000_create_tax_declaration_monitoring_tables
2026_07_15_246000_create_tax_guide_tables
2026_07_15_247000_create_esocial_fgts_monitoring_tables
2026_07_15_248000_create_fiscal_mutation_operations_tables
2026_07_15_250000_create_channel_sync_cursor_transitions_table
2026_07_16_100000_add_serpro_official_coordinates_and_provenance
```

## Migrations com tolerância condicional (`hasTable` / `hasColumn` / `try`)

Arquivos que usam guards silenciosos (legado — **não repetir** em migrations novas desta change):

| Arquivo | Padrão |
|---------|--------|
| `2026_07_14_120000_expand_client_registration_schema.php` | hasColumn |
| `2026_07_14_150000_add_matrix_client_id_to_clients.php` | hasColumn |
| `2026_07_14_160000_add_tax_regime_to_clients.php` | hasColumn |
| `2026_07_15_040000_create_office_autxml_and_import_tables.php` | hasColumn |
| `2026_07_15_060000_add_outbound_deadline_scheduling_schema.php` | condicional |
| `2026_07_15_060000_extend_cte_roles_quality_and_office_cursor.php` | hasTable/hasColumn intensivo |
| `2026_07_15_120000_create_svrs_egress_cohort_and_extend_attempts.php` | condicional |
| `2026_07_15_241000_create_dctfweb_mit_tables.php` | hasTable |
| `2026_07_15_242000_create_tax_installment_monitoring_tables.php` | condicional |
| `2026_07_15_243000_seed_sitfis_emit_operation_catalog.php` | condicional |
| `2026_07_15_244000_create_mailbox_tables.php` | condicional |
| `2026_07_15_246000_create_tax_guide_tables.php` | hasTable + try/catch em FKs |
| `2026_07_16_100000_add_serpro_official_coordinates_and_provenance.php` | hasTable/hasColumn |
| `2026_07_16_100100_add_serpro_usage_request_tag_and_route.php` | hasTable/hasColumn (pending) |
| `2026_07_16_300000_add_office_timezone_for_operations.php` | hasColumn + try |

## Ambientes

| Ambiente | Como inventariar | Status nesta sessão |
|----------|------------------|---------------------|
| Local Docker | `php artisan migrate:status` | **Documentado** |
| Homolog / prod | Mesmo comando + dump de `migrations` | **Pendente de coleta operacional** — gate de apply em ambiente real exige reexecutar este inventário |

Até haver dump de homolog/prod, o baseline **canônico de desenvolvimento** é o local. Apply produtivo **bloqueado** sem inventário daquele ambiente (alinhado ao design §12–13).
