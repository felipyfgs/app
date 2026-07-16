# Dicionário PostgreSQL efetivo — consolidate-fiscal-data-model

**Gerado em:** 2026-07-16T00:55:50Z (UTC)
**Ambiente:** local Docker (`app-postgres-1`, database `nfse`)
**Tabelas:** 108
**Colunas:** 1865
**Linhas de FK (coluna):** 280
**ON DELETE CASCADE (linhas coluna):** 170
**ON DELETE SET NULL (linhas coluna):** 110
**ON DELETE outras:** 0
**CHECK constraints:** 0
**Sequências:** 103
**Índices (pg_indexes):** 429

> Fonte: `information_schema` + `pg_indexes` + `pg_constraint` + contagens exatas `count(*)`.
> Timestamps de aplicação são majoritariamente `timestamp without time zone` (não `timestamptz`).

## Volume por tabela

| Tabela | Linhas | Colunas | FKs (cols) | Uniques/PK |
|---|---:|---:|---:|---:|
| `audit_logs` | 1 | 12 | 2 | 1 |
| `cache` | 0 | 3 | 0 | 1 |
| `cache_locks` | 0 | 3 | 0 | 1 |
| `channel_sync_cursor_transitions` | 0 | 15 | 2 | 1 |
| `channel_sync_cursors` | 22 | 20 | 2 | 2 |
| `client_contacts` | 0 | 15 | 2 | 1 |
| `client_credentials` | 21 | 17 | 2 | 2 |
| `client_custom_fields` | 0 | 11 | 2 | 2 |
| `client_tax_regime_periods` | 18 | 14 | 2 | 2 |
| `clients` | 22 | 19 | 2 | 1 |
| `cte_coverage_snapshots` | 0 | 13 | 2 | 2 |
| `cte_documents` | 0 | 31 | 2 | 2 |
| `cte_events` | 0 | 13 | 3 | 2 |
| `dctfweb_darf_documents` | 6 | 16 | 6 | 2 |
| `dctfweb_declarations` | 6 | 18 | 3 | 2 |
| `dctfweb_evidence_versions` | 6 | 17 | 6 | 2 |
| `dctfweb_mutation_attempts` | 0 | 19 | 3 | 2 |
| `dfe_documents` | 8 | 12 | 1 | 2 |
| `document_acquisitions` | 0 | 21 | 7 | 2 |
| `document_import_batch_items` | 0 | 21 | 4 | 2 |
| `document_import_batches` | 0 | 29 | 4 | 3 |
| `document_interests` | 8 | 11 | 3 | 3 |
| `esocial_event_evidences` | 7 | 22 | 5 | 2 |
| `establishments` | 23 | 31 | 2 | 2 |
| `exports` | 4 | 14 | 2 | 1 |
| `failed_jobs` | 110 | 7 | 0 | 2 |
| `fgts_competence_statuses` | 4 | 22 | 6 | 2 |
| `fiscal_categories` | 10 | 14 | 0 | 2 |
| `fiscal_competences` | 18 | 14 | 3 | 2 |
| `fiscal_document_quarantine` | 0 | 25 | 5 | 2 |
| `fiscal_evidence_artifacts` | 34 | 17 | 2 | 2 |
| `fiscal_findings` | 10 | 15 | 4 | 2 |
| `fiscal_guide_stubs` | 11 | 18 | 2 | 1 |
| `fiscal_last_update_events` | 0 | 17 | 3 | 2 |
| `fiscal_monitoring_runs` | 173 | 38 | 8 | 2 |
| `fiscal_monitoring_schedules` | 61 | 19 | 4 | 2 |
| `fiscal_mutation_operation_events` | 0 | 11 | 3 | 1 |
| `fiscal_mutation_operations` | 0 | 43 | 3 | 2 |
| `fiscal_pending_items` | 8 | 21 | 7 | 2 |
| `fiscal_snapshots` | 147 | 19 | 5 | 1 |
| `instance_backup_runs` | 0 | 11 | 0 | 1 |
| `job_batches` | 0 | 10 | 0 | 1 |
| `jobs` | 0 | 7 | 0 | 1 |
| `ma_outbound_retrieval_requests` | 0 | 49 | 6 | 1 |
| `mailbox_access_events` | 1 | 10 | 4 | 1 |
| `mailbox_alerts` | 2 | 13 | 3 | 2 |
| `mailbox_attachments` | 5 | 13 | 2 | 2 |
| `mailbox_contributor_states` | 5 | 16 | 4 | 2 |
| `mailbox_messages` | 5 | 34 | 6 | 3 |
| `mdfe_documents` | 0 | 19 | 2 | 2 |
| `migrations` | 48 | 3 | 0 | 1 |
| `mit_apuracoes` | 6 | 16 | 3 | 2 |
| `nfe_documents` | 0 | 24 | 2 | 2 |
| `nfe_events` | 0 | 11 | 2 | 1 |
| `nfse_events` | 9 | 9 | 2 | 1 |
| `nfse_notes` | 8 | 22 | 2 | 2 |
| `office_autxml_enrollments` | 0 | 12 | 4 | 2 |
| `office_credentials` | 0 | 19 | 2 | 2 |
| `office_distribution_cursors` | 0 | 24 | 2 | 2 |
| `office_distribution_runs` | 0 | 19 | 3 | 1 |
| `office_fiscal_category_links` | 61 | 12 | 4 | 2 |
| `office_fiscal_identities` | 0 | 10 | 1 | 2 |
| `office_integration_tokens` | 0 | 14 | 3 | 2 |
| `office_serpro_authorization_events` | 0 | 10 | 2 | 1 |
| `office_serpro_authorizations` | 0 | 33 | 1 | 2 |
| `office_subscriptions` | 1 | 16 | 1 | 2 |
| `office_user` | 3 | 7 | 2 | 2 |
| `offices` | 2 | 7 | 0 | 2 |
| `outbound_capacity_snapshots` | 0 | 26 | 1 | 1 |
| `outbound_capture_profiles` | 0 | 26 | 4 | 2 |
| `outbound_capture_runs` | 0 | 22 | 4 | 1 |
| `outbound_monthly_readiness` | 0 | 15 | 3 | 2 |
| `outbound_number_states` | 0 | 23 | 4 | 2 |
| `outbound_series_cursors` | 0 | 27 | 3 | 2 |
| `outbound_xml_recovery_attempts` | 0 | 28 | 4 | 2 |
| `password_reset_tokens` | 0 | 3 | 0 | 1 |
| `personal_access_tokens` | 0 | 10 | 0 | 2 |
| `platform_memberships` | 0 | 6 | 1 | 2 |
| `serpro_api_usage_entries` | 5 | 25 | 4 | 2 |
| `serpro_api_usage_reservations` | 0 | 30 | 3 | 2 |
| `serpro_contracts` | 0 | 25 | 0 | 1 |
| `serpro_operation_catalog` | 32 | 13 | 0 | 2 |
| `serpro_price_tiers` | 4 | 12 | 1 | 1 |
| `serpro_price_versions` | 1 | 10 | 0 | 2 |
| `serpro_service_catalog_entries` | 321 | 27 | 0 | 2 |
| `serpro_usage_monthly_aggregates` | 1 | 17 | 1 | 2 |
| `serpro_usage_reconciliation_adjustments` | 0 | 9 | 2 | 1 |
| `serpro_usage_reconciliations` | 0 | 15 | 1 | 2 |
| `sessions` | 0 | 6 | 0 | 1 |
| `svrs_egress_cohort_states` | 0 | 15 | 0 | 2 |
| `sync_cursors` | 5 | 15 | 2 | 2 |
| `sync_runs` | 88 | 15 | 3 | 1 |
| `tax_deadline_calendar_versions` | 1 | 13 | 0 | 2 |
| `tax_deadline_rules` | 5 | 13 | 2 | 1 |
| `tax_delivery_evidences` | 5 | 16 | 4 | 1 |
| `tax_guide_download_tokens` | 0 | 8 | 3 | 2 |
| `tax_guide_payment_confirmations` | 0 | 17 | 4 | 3 |
| `tax_guide_versions` | 7 | 35 | 6 | 3 |
| `tax_guides` | 7 | 23 | 5 | 2 |
| `tax_installment_orders` | 6 | 22 | 4 | 2 |
| `tax_installment_parcels` | 24 | 20 | 5 | 3 |
| `tax_installment_payments` | 6 | 15 | 5 | 2 |
| `tax_obligation_definitions` | 5 | 16 | 0 | 2 |
| `tax_obligation_projections` | 64 | 24 | 8 | 2 |
| `tax_obligation_regime_rules` | 25 | 9 | 1 | 2 |
| `tax_obligation_versions` | 5 | 14 | 1 | 3 |
| `tax_proxy_powers` | 0 | 20 | 3 | 2 |
| `users` | 3 | 13 | 1 | 2 |

## Ações ON DELETE (por tabela filha, agrupado)

### CASCADE (170 referências de coluna)

Referências sensíveis (amostra filtrada): 48 de 170.

- `cte_coverage_snapshots.client_id → clients.id`
- `cte_coverage_snapshots.office_id → offices.id`
- `cte_documents.dfe_document_id → dfe_documents.id`
- `cte_documents.office_id → offices.id`
- `cte_events.dfe_document_id → dfe_documents.id`
- `cte_events.office_id → offices.id`
- `dfe_documents.office_id → offices.id`
- `document_acquisitions.dfe_document_id → dfe_documents.id`
- `document_acquisitions.office_id → offices.id`
- `document_import_batch_items.document_import_batch_id → document_import_batches.id`
- `document_import_batch_items.office_id → offices.id`
- `document_import_batches.created_by → users.id`
- `document_import_batches.office_id → offices.id`
- `document_interests.dfe_document_id → dfe_documents.id`
- `document_interests.establishment_id → establishments.id`
- `document_interests.office_id → offices.id`
- `fiscal_document_quarantine.office_id → offices.id`
- `fiscal_evidence_artifacts.run_id → fiscal_monitoring_runs.id`
- `fiscal_findings.run_id → fiscal_monitoring_runs.id`
- `fiscal_findings.snapshot_id → fiscal_snapshots.id`
- `fiscal_monitoring_runs.client_id → clients.id`
- `fiscal_monitoring_runs.office_id → offices.id`
- `fiscal_monitoring_schedules.client_id → clients.id`
- `fiscal_monitoring_schedules.office_id → offices.id`
- `fiscal_snapshots.client_id → clients.id`
- `fiscal_snapshots.office_id → offices.id`
- `fiscal_snapshots.run_id → fiscal_monitoring_runs.id`
- `mdfe_documents.dfe_document_id → dfe_documents.id`
- `mdfe_documents.office_id → offices.id`
- `nfe_documents.dfe_document_id → dfe_documents.id`
- `nfe_documents.office_id → offices.id`
- `nfe_events.dfe_document_id → dfe_documents.id`
- `nfe_events.office_id → offices.id`
- `nfse_events.dfe_document_id → dfe_documents.id`
- `nfse_events.office_id → offices.id`
- `nfse_notes.dfe_document_id → dfe_documents.id`
- `nfse_notes.office_id → offices.id`
- `serpro_api_usage_entries.office_id → offices.id`
- `serpro_api_usage_reservations.office_id → offices.id`
- `tax_guide_download_tokens.office_id → offices.id`
- `tax_guide_download_tokens.tax_guide_version_id → tax_guide_versions.id`
- `tax_guide_download_tokens.user_id → users.id`
- `tax_guide_payment_confirmations.office_id → offices.id`
- `tax_guide_payment_confirmations.tax_guide_id → tax_guides.id`
- `tax_guide_versions.office_id → offices.id`
- `tax_guide_versions.tax_guide_id → tax_guides.id`
- `tax_guides.client_id → clients.id`
- `tax_guides.office_id → offices.id`

### SET NULL (110 referências de coluna)

Referências sensíveis (amostra filtrada): 58 de 110.

- `audit_logs.office_id → offices.id`
- `audit_logs.user_id → users.id`
- `cte_events.cte_document_id → cte_documents.id`
- `dctfweb_evidence_versions.run_id → fiscal_monitoring_runs.id`
- `document_acquisitions.document_import_batch_item_id → document_import_batch_items.id`
- `document_acquisitions.establishment_id → establishments.id`
- `document_acquisitions.ma_outbound_retrieval_request_id → ma_outbound_retrieval_requests.id`
- `document_acquisitions.office_distribution_cursor_id → office_distribution_cursors.id`
- `document_acquisitions.outbound_number_state_id → outbound_number_states.id`
- `document_import_batch_items.dfe_document_id → dfe_documents.id`
- `document_import_batch_items.establishment_id → establishments.id`
- `document_import_batches.client_id → clients.id`
- `document_import_batches.establishment_id → establishments.id`
- `esocial_event_evidences.run_id → fiscal_monitoring_runs.id`
- `fgts_competence_statuses.run_id → fiscal_monitoring_runs.id`
- `fgts_competence_statuses.snapshot_id → fiscal_snapshots.id`
- `fiscal_document_quarantine.document_import_batch_item_id → document_import_batch_items.id`
- `fiscal_document_quarantine.office_distribution_cursor_id → office_distribution_cursors.id`
- `fiscal_document_quarantine.promoted_dfe_document_id → dfe_documents.id`
- `fiscal_document_quarantine.resolved_by → users.id`
- `fiscal_last_update_events.directed_run_id → fiscal_monitoring_runs.id`
- `fiscal_monitoring_runs.competence_id → fiscal_competences.id`
- `fiscal_monitoring_runs.fiscal_category_id → fiscal_categories.id`
- `fiscal_monitoring_runs.last_update_event_id → fiscal_last_update_events.id`
- `fiscal_monitoring_runs.parent_run_id → fiscal_monitoring_runs.id`
- `fiscal_monitoring_runs.schedule_id → fiscal_monitoring_schedules.id`
- `fiscal_monitoring_runs.triggered_by → users.id`
- `fiscal_monitoring_schedules.category_link_id → office_fiscal_category_links.id`
- `fiscal_monitoring_schedules.fiscal_category_id → fiscal_categories.id`
- `fiscal_pending_items.run_id → fiscal_monitoring_runs.id`
- `fiscal_pending_items.snapshot_id → fiscal_snapshots.id`
- `fiscal_snapshots.competence_id → fiscal_competences.id`
- `fiscal_snapshots.evidence_artifact_id → fiscal_evidence_artifacts.id`
- `ma_outbound_retrieval_requests.dfe_document_id → dfe_documents.id`
- `mailbox_contributor_states.last_dte_run_id → fiscal_monitoring_runs.id`
- `mailbox_contributor_states.last_list_run_id → fiscal_monitoring_runs.id`
- `mailbox_messages.first_run_id → fiscal_monitoring_runs.id`
- `mailbox_messages.last_run_id → fiscal_monitoring_runs.id`
- `outbound_number_states.dfe_document_id → dfe_documents.id`
- `serpro_api_usage_entries.client_id → clients.id`
- `serpro_api_usage_entries.price_version_id → serpro_price_versions.id`
- `serpro_api_usage_entries.reservation_id → serpro_api_usage_reservations.id`
- `serpro_api_usage_reservations.client_id → clients.id`
- `serpro_api_usage_reservations.price_version_id → serpro_price_versions.id`
- `tax_delivery_evidences.run_id → fiscal_monitoring_runs.id`
- `tax_guide_payment_confirmations.recorded_by → users.id`
- `tax_guide_payment_confirmations.tax_guide_version_id → tax_guide_versions.id`
- `tax_guide_versions.confirmed_by_user_id → users.id`
- `tax_guide_versions.issued_by → users.id`
- `tax_guide_versions.replaces_version_id → tax_guide_versions.id`
- `tax_guide_versions.superseded_by_version_id → tax_guide_versions.id`
- `tax_guides.created_by → users.id`
- `tax_guides.current_version_id → tax_guide_versions.id`
- `tax_guides.establishment_id → establishments.id`
- `tax_installment_orders.run_id → fiscal_monitoring_runs.id`
- `tax_installment_orders.snapshot_id → fiscal_snapshots.id`
- `tax_installment_parcels.tax_guide_id → tax_guides.id`
- `tax_installment_payments.run_id → fiscal_monitoring_runs.id`

## Detalhe das tabelas prioritárias (colunas, chaves, FKs, índices)

### `clients` (22 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('clients_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `legal_name` | varchar/varchar | NO | `` |
| `root_cnpj` | varchar/varchar | NO | `` |
| `notes` | text/text | YES | `` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `deleted_at` | timestamp/timestamp | YES | `` |
| `display_name` | varchar/varchar | YES | `` |
| `legal_nature_code` | varchar/varchar | YES | `` |
| `legal_nature_name` | varchar/varchar | YES | `` |
| `company_size_code` | varchar/varchar | YES | `` |
| `company_size_name` | varchar/varchar | YES | `` |
| `inactive_reason` | text/text | YES | `` |
| `registration_source` | varchar/varchar | NO | `'LEGACY'::character varying` |
| `registration_refreshed_at` | timestamp/timestamp | YES | `` |
| `matrix_client_id` | bigint/int8 | YES | `` |
| `tax_regime` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `clients_pkey` (id)

**FKs:**
- `matrix_client_id` → `clients.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `clients_office_id_legal_name_index`: `CREATE INDEX clients_office_id_legal_name_index ON public.clients USING btree (office_id, legal_name)`
- `clients_office_id_matrix_client_id_index`: `CREATE INDEX clients_office_id_matrix_client_id_index ON public.clients USING btree (office_id, matrix_client_id)`
- `clients_office_id_root_cnpj_index`: `CREATE INDEX clients_office_id_root_cnpj_index ON public.clients USING btree (office_id, root_cnpj)`
- `clients_pkey`: `CREATE UNIQUE INDEX clients_pkey ON public.clients USING btree (id)`

### `establishments` (23 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('establishments_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `cnpj` | varchar/varchar | NO | `` |
| `trade_name` | varchar/varchar | YES | `` |
| `is_matrix` | boolean/bool | NO | `false` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `deleted_at` | timestamp/timestamp | YES | `` |
| `registration_status` | varchar/varchar | NO | `'UNKNOWN'::character varying` |
| `registration_status_at` | date/date | YES | `` |
| `registration_status_reason` | varchar/varchar | YES | `` |
| `activity_started_at` | date/date | YES | `` |
| `main_cnae_code` | varchar/varchar | YES | `` |
| `main_cnae_name` | varchar/varchar | YES | `` |
| `address_postal_code` | varchar/varchar | YES | `` |
| `address_street_type` | varchar/varchar | YES | `` |
| `address_street` | varchar/varchar | YES | `` |
| `address_number` | varchar/varchar | YES | `` |
| `address_complement` | varchar/varchar | YES | `` |
| `address_district` | varchar/varchar | YES | `` |
| `address_city` | varchar/varchar | YES | `` |
| `address_city_ibge_code` | varchar/varchar | YES | `` |
| `address_state` | varchar/varchar | YES | `` |
| `address_country` | varchar/varchar | YES | `` |
| `public_email` | varchar/varchar | YES | `` |
| `public_phone` | varchar/varchar | YES | `` |
| `capture_enabled` | boolean/bool | NO | `true` |
| `registration_source` | varchar/varchar | NO | `'LEGACY'::character varying` |
| `registration_refreshed_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `establishments_pkey` (id)
- UNIQUE: `establishments_office_id_cnpj_unique` (office_id,cnpj)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `establishments_client_id_is_active_index`: `CREATE INDEX establishments_client_id_is_active_index ON public.establishments USING btree (client_id, is_active)`
- `establishments_office_id_cnpj_unique`: `CREATE UNIQUE INDEX establishments_office_id_cnpj_unique ON public.establishments USING btree (office_id, cnpj)`
- `establishments_one_matrix_per_client`: `CREATE UNIQUE INDEX establishments_one_matrix_per_client ON public.establishments USING btree (client_id) WHERE ((is_matrix = true) AND (deleted_at IS NULL))`
- `establishments_pkey`: `CREATE UNIQUE INDEX establishments_pkey ON public.establishments USING btree (id)`

### `client_credentials` (21 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('client_credentials_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `status` | varchar/varchar | NO | `` |
| `subject_name` | varchar/varchar | NO | `` |
| `holder_cnpj` | varchar/varchar | NO | `` |
| `fingerprint_sha256` | varchar/varchar | NO | `` |
| `valid_from` | timestamptz/timestamptz | NO | `` |
| `valid_to` | timestamptz/timestamptz | NO | `` |
| `vault_object_id` | varchar/varchar | NO | `` |
| `activated_at` | timestamptz/timestamptz | YES | `` |
| `superseded_at` | timestamptz/timestamptz | YES | `` |
| `expires_alert_30` | boolean/bool | NO | `false` |
| `expires_alert_7` | boolean/bool | NO | `false` |
| `expires_alert_1` | boolean/bool | NO | `false` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `client_credentials_pkey` (id)
- UNIQUE: `client_credentials_client_id_fingerprint_sha256_status_unique` (client_id,fingerprint_sha256,status)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `client_credentials_client_id_fingerprint_sha256_status_unique`: `CREATE UNIQUE INDEX client_credentials_client_id_fingerprint_sha256_status_unique ON public.client_credentials USING btree (client_id, fingerprint_sha256, status)`
- `client_credentials_client_id_status_index`: `CREATE INDEX client_credentials_client_id_status_index ON public.client_credentials USING btree (client_id, status)`
- `client_credentials_office_id_valid_to_index`: `CREATE INDEX client_credentials_office_id_valid_to_index ON public.client_credentials USING btree (office_id, valid_to)`
- `client_credentials_pkey`: `CREATE UNIQUE INDEX client_credentials_pkey ON public.client_credentials USING btree (id)`

### `client_contacts` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('client_contacts_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `name` | varchar/varchar | NO | `` |
| `role` | varchar/varchar | YES | `` |
| `email` | varchar/varchar | YES | `` |
| `phone` | varchar/varchar | YES | `` |
| `is_whatsapp` | boolean/bool | NO | `false` |
| `is_primary` | boolean/bool | NO | `false` |
| `receives_alerts` | boolean/bool | NO | `false` |
| `notes` | text/text | YES | `` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `deleted_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `client_contacts_pkey` (id)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `client_contacts_client_id_is_active_index`: `CREATE INDEX client_contacts_client_id_is_active_index ON public.client_contacts USING btree (client_id, is_active)`
- `client_contacts_office_id_client_id_index`: `CREATE INDEX client_contacts_office_id_client_id_index ON public.client_contacts USING btree (office_id, client_id)`
- `client_contacts_one_primary_active_per_client`: `CREATE UNIQUE INDEX client_contacts_one_primary_active_per_client ON public.client_contacts USING btree (client_id) WHERE ((is_primary = true) AND (is_active = true) AND (deleted_at IS NULL))`
- `client_contacts_pkey`: `CREATE UNIQUE INDEX client_contacts_pkey ON public.client_contacts USING btree (id)`

### `dfe_documents` (8 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('dfe_documents_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `sha256` | varchar/varchar | NO | `` |
| `document_type` | varchar/varchar | NO | `` |
| `schema_version` | varchar/varchar | YES | `` |
| `access_key` | varchar/varchar | YES | `` |
| `vault_object_id` | varchar/varchar | NO | `` |
| `byte_size` | integer/int4 | NO | `` |
| `parse_status` | varchar/varchar | NO | `'OK'::character varying` |
| `parse_alert` | text/text | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `dfe_documents_pkey` (id)
- UNIQUE: `dfe_documents_office_id_sha256_unique` (office_id,sha256)

**FKs:**
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `dfe_documents_office_id_access_key_index`: `CREATE INDEX dfe_documents_office_id_access_key_index ON public.dfe_documents USING btree (office_id, access_key)`
- `dfe_documents_office_id_sha256_unique`: `CREATE UNIQUE INDEX dfe_documents_office_id_sha256_unique ON public.dfe_documents USING btree (office_id, sha256)`
- `dfe_documents_pkey`: `CREATE UNIQUE INDEX dfe_documents_pkey ON public.dfe_documents USING btree (id)`

### `document_acquisitions` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('document_acquisitions_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `dfe_document_id` | bigint/int8 | NO | `` |
| `access_key` | varchar/varchar | YES | `` |
| `source` | varchar/varchar | NO | `` |
| `channel` | varchar/varchar | YES | `` |
| `sha256` | varchar/varchar | NO | `` |
| `is_canonical` | boolean/bool | NO | `true` |
| `bytes_diverge_from_canonical` | boolean/bool | NO | `false` |
| `quarantine_reason` | varchar/varchar | YES | `` |
| `establishment_id` | bigint/int8 | YES | `` |
| `ma_outbound_retrieval_request_id` | bigint/int8 | YES | `` |
| `outbound_number_state_id` | bigint/int8 | YES | `` |
| `metadata` | json/json | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `nsu` | bigint/int8 | YES | `` |
| `office_distribution_cursor_id` | bigint/int8 | YES | `` |
| `document_import_batch_item_id` | bigint/int8 | YES | `` |
| `artifact_quality` | varchar/varchar | YES | `` |
| `signature_result` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `document_acquisitions_pkey` (id)
- UNIQUE: `document_acquisitions_doc_source_sha` (dfe_document_id,source,sha256)

**FKs:**
- `dfe_document_id` → `dfe_documents.id` ON DELETE CASCADE
- `document_import_batch_item_id` → `document_import_batch_items.id` ON DELETE SET NULL
- `establishment_id` → `establishments.id` ON DELETE SET NULL
- `ma_outbound_retrieval_request_id` → `ma_outbound_retrieval_requests.id` ON DELETE SET NULL
- `office_distribution_cursor_id` → `office_distribution_cursors.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE
- `outbound_number_state_id` → `outbound_number_states.id` ON DELETE SET NULL

**Índices:**
- `document_acquisitions_batch_item`: `CREATE INDEX document_acquisitions_batch_item ON public.document_acquisitions USING btree (document_import_batch_item_id)`
- `document_acquisitions_doc_source_sha`: `CREATE UNIQUE INDEX document_acquisitions_doc_source_sha ON public.document_acquisitions USING btree (dfe_document_id, source, sha256)`
- `document_acquisitions_office_id_access_key_index`: `CREATE INDEX document_acquisitions_office_id_access_key_index ON public.document_acquisitions USING btree (office_id, access_key)`
- `document_acquisitions_office_id_source_index`: `CREATE INDEX document_acquisitions_office_id_source_index ON public.document_acquisitions USING btree (office_id, source)`
- `document_acquisitions_office_nsu`: `CREATE INDEX document_acquisitions_office_nsu ON public.document_acquisitions USING btree (office_id, nsu)`
- `document_acquisitions_office_quality`: `CREATE INDEX document_acquisitions_office_quality ON public.document_acquisitions USING btree (office_id, artifact_quality)`
- `document_acquisitions_office_source_channel`: `CREATE INDEX document_acquisitions_office_source_channel ON public.document_acquisitions USING btree (office_id, source, channel)`
- `document_acquisitions_pkey`: `CREATE UNIQUE INDEX document_acquisitions_pkey ON public.document_acquisitions USING btree (id)`

### `document_interests` (8 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('document_interests_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `dfe_document_id` | bigint/int8 | NO | `` |
| `establishment_id` | bigint/int8 | NO | `` |
| `nsu` | bigint/int8 | YES | `` |
| `environment` | varchar/varchar | NO | `` |
| `fiscal_role` | varchar/varchar | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `channel` | varchar/varchar | NO | `'NFSE_ADN'::character varying` |
| `direction` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `document_interests_pkey` (id)
- UNIQUE: `document_interests_doc_estab_role_channel_unique` (dfe_document_id,establishment_id,fiscal_role,channel)
- UNIQUE: `document_interests_estab_env_ch_nsu_role_unique` (establishment_id,environment,channel,nsu,fiscal_role)

**FKs:**
- `dfe_document_id` → `dfe_documents.id` ON DELETE CASCADE
- `establishment_id` → `establishments.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `document_interests_doc_estab_role_channel_unique`: `CREATE UNIQUE INDEX document_interests_doc_estab_role_channel_unique ON public.document_interests USING btree (dfe_document_id, establishment_id, fiscal_role, channel)`
- `document_interests_estab_channel_nsu`: `CREATE INDEX document_interests_estab_channel_nsu ON public.document_interests USING btree (establishment_id, channel, nsu)`
- `document_interests_estab_env_ch_nsu_role_unique`: `CREATE UNIQUE INDEX document_interests_estab_env_ch_nsu_role_unique ON public.document_interests USING btree (establishment_id, environment, channel, nsu, fiscal_role)`
- `document_interests_office_direction`: `CREATE INDEX document_interests_office_direction ON public.document_interests USING btree (office_id, direction)`
- `document_interests_office_role`: `CREATE INDEX document_interests_office_role ON public.document_interests USING btree (office_id, fiscal_role)`
- `document_interests_pkey`: `CREATE UNIQUE INDEX document_interests_pkey ON public.document_interests USING btree (id)`

### `sync_cursors` (5 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('sync_cursors_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `establishment_id` | bigint/int8 | NO | `` |
| `environment` | varchar/varchar | NO | `` |
| `last_nsu` | bigint/int8 | NO | `'0'::bigint` |
| `status` | varchar/varchar | NO | `'IDLE'::character varying` |
| `consecutive_decode_failures` | integer/int4 | NO | `0` |
| `attempts` | integer/int4 | NO | `0` |
| `next_sync_at` | timestamptz/timestamptz | YES | `` |
| `last_success_at` | timestamptz/timestamptz | YES | `` |
| `locked_at` | timestamptz/timestamptz | YES | `` |
| `lock_owner` | varchar/varchar | YES | `` |
| `last_error` | text/text | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `sync_cursors_pkey` (id)
- UNIQUE: `sync_cursors_establishment_id_environment_unique` (establishment_id,environment)

**FKs:**
- `establishment_id` → `establishments.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `sync_cursors_establishment_id_environment_unique`: `CREATE UNIQUE INDEX sync_cursors_establishment_id_environment_unique ON public.sync_cursors USING btree (establishment_id, environment)`
- `sync_cursors_pkey`: `CREATE UNIQUE INDEX sync_cursors_pkey ON public.sync_cursors USING btree (id)`
- `sync_cursors_status_next_sync_at_index`: `CREATE INDEX sync_cursors_status_next_sync_at_index ON public.sync_cursors USING btree (status, next_sync_at)`

### `channel_sync_cursors` (22 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('channel_sync_cursors_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `establishment_id` | bigint/int8 | NO | `` |
| `environment` | varchar/varchar | NO | `` |
| `source` | varchar/varchar | NO | `` |
| `channel` | varchar/varchar | NO | `` |
| `last_nsu` | bigint/int8 | NO | `'0'::bigint` |
| `max_nsu_seen` | bigint/int8 | YES | `` |
| `status` | varchar/varchar | NO | `'IDLE'::character varying` |
| `last_cstat` | varchar/varchar | YES | `` |
| `last_xmotivo` | varchar/varchar | YES | `` |
| `consecutive_decode_failures` | integer/int4 | NO | `0` |
| `attempts` | integer/int4 | NO | `0` |
| `next_sync_at` | timestamptz/timestamptz | YES | `` |
| `last_success_at` | timestamptz/timestamptz | YES | `` |
| `locked_at` | timestamptz/timestamptz | YES | `` |
| `lock_owner` | varchar/varchar | YES | `` |
| `last_error` | text/text | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `channel_sync_cursors_pkey` (id)
- UNIQUE: `channel_sync_cursors_unique` (establishment_id,environment,source,channel)

**FKs:**
- `establishment_id` → `establishments.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `channel_sync_cursors_office_id_channel_index`: `CREATE INDEX channel_sync_cursors_office_id_channel_index ON public.channel_sync_cursors USING btree (office_id, channel)`
- `channel_sync_cursors_pkey`: `CREATE UNIQUE INDEX channel_sync_cursors_pkey ON public.channel_sync_cursors USING btree (id)`
- `channel_sync_cursors_status_next_sync_at_index`: `CREATE INDEX channel_sync_cursors_status_next_sync_at_index ON public.channel_sync_cursors USING btree (status, next_sync_at)`
- `channel_sync_cursors_unique`: `CREATE UNIQUE INDEX channel_sync_cursors_unique ON public.channel_sync_cursors USING btree (establishment_id, environment, source, channel)`

### `office_distribution_cursors` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('office_distribution_cursors_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `office_fiscal_identity_id` | bigint/int8 | NO | `` |
| `interested_root_cnpj` | varchar/varchar | NO | `` |
| `query_cnpj` | varchar/varchar | NO | `` |
| `environment` | varchar/varchar | NO | `` |
| `channel` | varchar/varchar | NO | `'NFE_AUTXML_DISTDFE'::character varying` |
| `last_nsu` | bigint/int8 | NO | `'0'::bigint` |
| `max_nsu_seen` | bigint/int8 | YES | `` |
| `status` | varchar/varchar | NO | `'IDLE'::character varying` |
| `last_cstat` | varchar/varchar | YES | `` |
| `last_xmotivo` | varchar/varchar | YES | `` |
| `consecutive_decode_failures` | integer/int4 | NO | `0` |
| `attempts` | integer/int4 | NO | `0` |
| `activated_at` | timestamptz/timestamptz | YES | `` |
| `next_sync_at` | timestamptz/timestamptz | YES | `` |
| `last_success_at` | timestamptz/timestamptz | YES | `` |
| `last_heartbeat_at` | timestamptz/timestamptz | YES | `` |
| `locked_at` | timestamptz/timestamptz | YES | `` |
| `lock_owner` | varchar/varchar | YES | `` |
| `external_consumer_status` | varchar/varchar | YES | `` |
| `last_error` | text/text | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `office_distribution_cursors_pkey` (id)
- UNIQUE: `office_distribution_cursors_stream_unique` (office_id,interested_root_cnpj,environment,channel)

**FKs:**
- `office_fiscal_identity_id` → `office_fiscal_identities.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `office_distribution_cursors_office_channel_status`: `CREATE INDEX office_distribution_cursors_office_channel_status ON public.office_distribution_cursors USING btree (office_id, channel, status)`
- `office_distribution_cursors_office_id_channel_index`: `CREATE INDEX office_distribution_cursors_office_id_channel_index ON public.office_distribution_cursors USING btree (office_id, channel)`
- `office_distribution_cursors_pkey`: `CREATE UNIQUE INDEX office_distribution_cursors_pkey ON public.office_distribution_cursors USING btree (id)`
- `office_distribution_cursors_status_next_sync_at_index`: `CREATE INDEX office_distribution_cursors_status_next_sync_at_index ON public.office_distribution_cursors USING btree (status, next_sync_at)`
- `office_distribution_cursors_stream_unique`: `CREATE UNIQUE INDEX office_distribution_cursors_stream_unique ON public.office_distribution_cursors USING btree (office_id, interested_root_cnpj, environment, channel)`

### `ma_outbound_retrieval_requests` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('ma_outbound_retrieval_requests_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `outbound_capture_profile_id` | bigint/int8 | NO | `` |
| `establishment_id` | bigint/int8 | NO | `` |
| `environment` | varchar/varchar | NO | `` |
| `model` | varchar/varchar | NO | `` |
| `direction` | varchar/varchar | NO | `'OUT'::character varying` |
| `competence` | varchar/varchar | NO | `` |
| `status` | varchar/varchar | NO | `'PENDING'::character varying` |
| `mode` | varchar/varchar | NO | `'ASSISTED'::character varying` |
| `external_ref` | varchar/varchar | YES | `` |
| `requested_at` | timestamptz/timestamptz | YES | `` |
| `expires_at` | timestamptz/timestamptz | YES | `` |
| `ready_at` | timestamptz/timestamptz | YES | `` |
| `ingested_at` | timestamptz/timestamptz | YES | `` |
| `files_expected` | integer/int4 | YES | `` |
| `files_ingested` | integer/int4 | NO | `0` |
| `last_error` | text/text | YES | `` |
| `created_by` | bigint/int8 | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `origin` | varchar/varchar | NO | `'MA_OFFICIAL_PACKAGE'::character varying` |
| `access_key` | varchar/varchar | YES | `` |
| `outbound_number_state_id` | bigint/int8 | YES | `` |
| `recovery_status` | varchar/varchar | YES | `` |
| `failure_reason` | varchar/varchar | YES | `` |
| `attempt_count` | smallint/int2 | NO | `'0'::smallint` |
| `next_attempt_at` | timestamptz/timestamptz | YES | `` |
| `correlation_id` | varchar/varchar | YES | `` |
| `sha256` | varchar/varchar | YES | `` |
| `dfe_document_id` | bigint/int8 | YES | `` |
| `due_at` | timestamptz/timestamptz | YES | `` |
| `target_at` | timestamptz/timestamptz | YES | `` |
| `deadline_source` | varchar/varchar | YES | `` |
| `urgency_band` | varchar/varchar | YES | `` |
| `deadline_status` | varchar/varchar | YES | `` |
| `svrs_transaction_count` | smallint/int2 | NO | `'0'::smallint` |
| `planned_at` | timestamptz/timestamptz | YES | `` |
| `dispatched_at` | timestamptz/timestamptz | YES | `` |
| `accommodation_until` | timestamptz/timestamptz | YES | `` |
| `captured_at` | timestamptz/timestamptz | YES | `` |
| `captured_before_due` | boolean/bool | YES | `` |
| `capture_source` | varchar/varchar | YES | `` |
| `root_cnpj` | varchar/varchar | YES | `` |
| `capacity_at_risk` | boolean/bool | NO | `false` |
| `slot_key` | varchar/varchar | YES | `` |
| `source_selected` | varchar/varchar | YES | `` |
| `exchanges_reserved` | smallint/int2 | YES | `` |
| `exchanges_consumed` | smallint/int2 | YES | `` |

**Chaves:**
- PRIMARY KEY: `ma_outbound_retrieval_requests_pkey` (id)

**FKs:**
- `created_by` → `users.id` ON DELETE SET NULL
- `dfe_document_id` → `dfe_documents.id` ON DELETE SET NULL
- `establishment_id` → `establishments.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE
- `outbound_capture_profile_id` → `outbound_capture_profiles.id` ON DELETE CASCADE
- `outbound_number_state_id` → `outbound_number_states.id` ON DELETE SET NULL

**Índices:**
- `ma_outbound_retrieval_requests_establishment_id_competence_mode`: `CREATE INDEX ma_outbound_retrieval_requests_establishment_id_competence_mode ON public.ma_outbound_retrieval_requests USING btree (establishment_id, competence, model)`
- `ma_outbound_retrieval_requests_office_id_status_index`: `CREATE INDEX ma_outbound_retrieval_requests_office_id_status_index ON public.ma_outbound_retrieval_requests USING btree (office_id, status)`
- `ma_outbound_retrieval_requests_pkey`: `CREATE UNIQUE INDEX ma_outbound_retrieval_requests_pkey ON public.ma_outbound_retrieval_requests USING btree (id)`
- `ma_retrieval_active_svrs_unique`: `CREATE UNIQUE INDEX ma_retrieval_active_svrs_unique ON public.ma_outbound_retrieval_requests USING btree (office_id, outbound_capture_profile_id, access_key, origin) WHERE (((origin)::text = 'SVRS_PORTAL_BY_KEY'::text) AND (access_key IS NOT NULL) AND (recovery_status IS NOT NULL) AND ((recovery_status)::text <> ALL ((ARRAY['CAPTURED'::character varying, 'NOT_AVAILABLE_VISIBLE'::character varying, 'BLOCKED'::character varying, 'RESOLVED_BY_OTHER_SOURCE'::character varying])::text[])))`
- `ma_retrieval_office_access_key_idx`: `CREATE INDEX ma_retrieval_office_access_key_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, access_key)`
- `ma_retrieval_office_comp_band_idx`: `CREATE INDEX ma_retrieval_office_comp_band_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, competence, urgency_band)`
- `ma_retrieval_office_due_band_idx`: `CREATE INDEX ma_retrieval_office_due_band_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, due_at, urgency_band)`
- `ma_retrieval_office_next_attempt_idx`: `CREATE INDEX ma_retrieval_office_next_attempt_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, next_attempt_at)`
- `ma_retrieval_office_next_band_idx`: `CREATE INDEX ma_retrieval_office_next_band_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, next_attempt_at, urgency_band)`
- `ma_retrieval_office_origin_status_idx`: `CREATE INDEX ma_retrieval_office_origin_status_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, origin, recovery_status)`
- `ma_retrieval_office_root_model_idx`: `CREATE INDEX ma_retrieval_office_root_model_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, root_cnpj, model)`
- `ma_retrieval_office_slot_key_idx`: `CREATE INDEX ma_retrieval_office_slot_key_idx ON public.ma_outbound_retrieval_requests USING btree (office_id, slot_key)`
- `ma_retrieval_slot_attempt_unique`: `CREATE UNIQUE INDEX ma_retrieval_slot_attempt_unique ON public.ma_outbound_retrieval_requests USING btree (office_id, access_key, origin, svrs_transaction_count) WHERE (((origin)::text = 'SVRS_PORTAL_BY_KEY'::text) AND (access_key IS NOT NULL) AND (recovery_status IS NOT NULL) AND ((recovery_status)::text <> ALL ((ARRAY['CAPTURED'::character varying, 'NOT_AVAILABLE_VISIBLE'::character varying, 'BLOCKED'::character varying, 'RESOLVED_BY_OTHER_SOURCE'::character varying])::text[])))`

### `outbound_xml_recovery_attempts` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('outbound_xml_recovery_attempts_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `ma_outbound_retrieval_request_id` | bigint/int8 | NO | `` |
| `outbound_capture_profile_id` | bigint/int8 | NO | `` |
| `outbound_number_state_id` | bigint/int8 | YES | `` |
| `access_key` | varchar/varchar | NO | `` |
| `correlation_id` | varchar/varchar | NO | `` |
| `attempt_number` | smallint/int2 | NO | `` |
| `result` | varchar/varchar | NO | `` |
| `failure_reason` | varchar/varchar | YES | `` |
| `transport_outcome` | varchar/varchar | YES | `` |
| `http_status` | smallint/int2 | YES | `` |
| `parser_version` | varchar/varchar | YES | `` |
| `get_latency_ms` | integer/int4 | YES | `` |
| `post_latency_ms` | integer/int4 | YES | `` |
| `total_latency_ms` | integer/int4 | YES | `` |
| `sanitized_detail` | varchar/varchar | YES | `` |
| `sha256` | varchar/varchar | YES | `` |
| `started_at` | timestamptz/timestamptz | NO | `` |
| `finished_at` | timestamptz/timestamptz | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `model` | varchar/varchar | YES | `` |
| `origin` | varchar/varchar | YES | `` |
| `cohort_id` | varchar/varchar | YES | `` |
| `exchanges_reserved` | smallint/int2 | YES | `` |
| `exchanges_consumed` | smallint/int2 | YES | `` |
| `reservation_id` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `outbound_xml_recovery_attempts_pkey` (id)
- UNIQUE: `outbound_xml_recovery_attempt_num_unique` (ma_outbound_retrieval_request_id,attempt_number)

**FKs:**
- `ma_outbound_retrieval_request_id` → `ma_outbound_retrieval_requests.id` ON DELETE CASCADE
- `office_id` → `offices.id` ON DELETE CASCADE
- `outbound_capture_profile_id` → `outbound_capture_profiles.id` ON DELETE CASCADE
- `outbound_number_state_id` → `outbound_number_states.id` ON DELETE SET NULL

**Índices:**
- `outbound_xml_attempt_cohort_created_idx`: `CREATE INDEX outbound_xml_attempt_cohort_created_idx ON public.outbound_xml_recovery_attempts USING btree (cohort_id, created_at)`
- `outbound_xml_attempt_office_origin_result_idx`: `CREATE INDEX outbound_xml_attempt_office_origin_result_idx ON public.outbound_xml_recovery_attempts USING btree (office_id, origin, result)`
- `outbound_xml_recovery_attempt_num_unique`: `CREATE UNIQUE INDEX outbound_xml_recovery_attempt_num_unique ON public.outbound_xml_recovery_attempts USING btree (ma_outbound_retrieval_request_id, attempt_number)`
- `outbound_xml_recovery_attempts_office_id_access_key_index`: `CREATE INDEX outbound_xml_recovery_attempts_office_id_access_key_index ON public.outbound_xml_recovery_attempts USING btree (office_id, access_key)`
- `outbound_xml_recovery_attempts_office_id_correlation_id_index`: `CREATE INDEX outbound_xml_recovery_attempts_office_id_correlation_id_index ON public.outbound_xml_recovery_attempts USING btree (office_id, correlation_id)`
- `outbound_xml_recovery_attempts_office_id_result_created_at_inde`: `CREATE INDEX outbound_xml_recovery_attempts_office_id_result_created_at_inde ON public.outbound_xml_recovery_attempts USING btree (office_id, result, created_at)`
- `outbound_xml_recovery_attempts_pkey`: `CREATE UNIQUE INDEX outbound_xml_recovery_attempts_pkey ON public.outbound_xml_recovery_attempts USING btree (id)`

### `serpro_service_catalog_entries` (321 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('serpro_service_catalog_entries_id_seq'::regclass)` |
| `catalog_version` | integer/int4 | NO | `` |
| `environment` | varchar/varchar | NO | `` |
| `solution_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | NO | `` |
| `label` | varchar/varchar | NO | `` |
| `is_mutating` | boolean/bool | NO | `false` |
| `is_enabled` | boolean/bool | NO | `true` |
| `required_proxy_power` | varchar/varchar | YES | `` |
| `billable_class` | varchar/varchar | NO | `` |
| `cache_ttl_seconds` | integer/int4 | YES | `` |
| `rate_limit_per_minute` | integer/int4 | YES | `` |
| `coverage` | varchar/varchar | NO | `'KNOWN'::character varying` |
| `metadata` | json/json | YES | `` |
| `effective_from` | timestamp/timestamp | YES | `` |
| `effective_to` | timestamp/timestamp | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `operation_key` | varchar/varchar | YES | `` |
| `id_sistema` | varchar/varchar | YES | `` |
| `id_servico` | varchar/varchar | YES | `` |
| `versao_sistema` | varchar/varchar | YES | `` |
| `functional_route` | varchar/varchar | YES | `` |
| `official_state` | varchar/varchar | YES | `` |
| `platform_support` | varchar/varchar | YES | `` |
| `dados_mode` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `serpro_service_catalog_entries_pkey` (id)
- UNIQUE: `serpro_catalog_unique_op` (catalog_version,environment,solution_code,service_code,operation_code)

**Índices:**
- `serpro_catalog_operation_key_idx`: `CREATE INDEX serpro_catalog_operation_key_idx ON public.serpro_service_catalog_entries USING btree (operation_key)`
- `serpro_catalog_unique_op`: `CREATE UNIQUE INDEX serpro_catalog_unique_op ON public.serpro_service_catalog_entries USING btree (catalog_version, environment, solution_code, service_code, operation_code)`
- `serpro_service_catalog_entries_environment_is_enabled_index`: `CREATE INDEX serpro_service_catalog_entries_environment_is_enabled_index ON public.serpro_service_catalog_entries USING btree (environment, is_enabled)`
- `serpro_service_catalog_entries_pkey`: `CREATE UNIQUE INDEX serpro_service_catalog_entries_pkey ON public.serpro_service_catalog_entries USING btree (id)`
- `serpro_service_catalog_entries_solution_code_service_code_index`: `CREATE INDEX serpro_service_catalog_entries_solution_code_service_code_index ON public.serpro_service_catalog_entries USING btree (solution_code, service_code)`

### `serpro_operation_catalog` (32 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('serpro_operation_catalog_id_seq'::regclass)` |
| `system_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | NO | `` |
| `consumption_class` | varchar/varchar | NO | `` |
| `is_essential` | boolean/bool | NO | `false` |
| `effective_from` | timestamptz/timestamptz | NO | `` |
| `effective_to` | timestamptz/timestamptz | YES | `` |
| `label` | varchar/varchar | YES | `` |
| `notes` | text/text | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `operation_key` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `serpro_operation_catalog_pkey` (id)
- UNIQUE: `serpro_op_catalog_unique` (system_code,service_code,operation_code,effective_from)

**Índices:**
- `serpro_op_catalog_lookup_idx`: `CREATE INDEX serpro_op_catalog_lookup_idx ON public.serpro_operation_catalog USING btree (system_code, service_code, operation_code, effective_from, effective_to)`
- `serpro_op_catalog_operation_key_idx`: `CREATE INDEX serpro_op_catalog_operation_key_idx ON public.serpro_operation_catalog USING btree (operation_key)`
- `serpro_op_catalog_unique`: `CREATE UNIQUE INDEX serpro_op_catalog_unique ON public.serpro_operation_catalog USING btree (system_code, service_code, operation_code, effective_from)`
- `serpro_operation_catalog_pkey`: `CREATE UNIQUE INDEX serpro_operation_catalog_pkey ON public.serpro_operation_catalog USING btree (id)`

### `serpro_api_usage_entries` (5 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('serpro_api_usage_entries_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `reservation_id` | bigint/int8 | YES | `` |
| `idempotency_key` | varchar/varchar | NO | `` |
| `client_id` | bigint/int8 | YES | `` |
| `contributor_ref` | varchar/varchar | YES | `` |
| `system_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | NO | `` |
| `consumption_class` | varchar/varchar | NO | `` |
| `quantity` | integer/int4 | NO | `1` |
| `result` | varchar/varchar | NO | `` |
| `correlation_id` | varchar/varchar | YES | `` |
| `price_version_id` | bigint/int8 | YES | `` |
| `estimated_cost_micros` | bigint/int8 | YES | `` |
| `is_billable_attempt` | boolean/bool | NO | `true` |
| `latency_ms` | integer/int4 | YES | `` |
| `http_status` | smallint/int2 | YES | `` |
| `shadow_mode` | boolean/bool | NO | `true` |
| `occurred_at` | timestamptz/timestamptz | NO | `` |
| `created_at` | timestamptz/timestamptz | NO | `CURRENT_TIMESTAMP` |
| `operation_key` | varchar/varchar | YES | `` |
| `request_tag` | varchar/varchar | YES | `` |
| `functional_route` | varchar/varchar | YES | `` |
| `is_simulated` | boolean/bool | NO | `false` |

**Chaves:**
- PRIMARY KEY: `serpro_api_usage_entries_pkey` (id)
- UNIQUE: `serpro_api_usage_entries_idempotency_key_unique` (idempotency_key)

**FKs:**
- `client_id` → `clients.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE
- `price_version_id` → `serpro_price_versions.id` ON DELETE SET NULL
- `reservation_id` → `serpro_api_usage_reservations.id` ON DELETE SET NULL

**Índices:**
- `serpro_api_usage_entries_correlation_id_index`: `CREATE INDEX serpro_api_usage_entries_correlation_id_index ON public.serpro_api_usage_entries USING btree (correlation_id)`
- `serpro_api_usage_entries_idempotency_key_unique`: `CREATE UNIQUE INDEX serpro_api_usage_entries_idempotency_key_unique ON public.serpro_api_usage_entries USING btree (idempotency_key)`
- `serpro_api_usage_entries_office_id_consumption_class_occurred_a`: `CREATE INDEX serpro_api_usage_entries_office_id_consumption_class_occurred_a ON public.serpro_api_usage_entries USING btree (office_id, consumption_class, occurred_at)`
- `serpro_api_usage_entries_office_id_occurred_at_index`: `CREATE INDEX serpro_api_usage_entries_office_id_occurred_at_index ON public.serpro_api_usage_entries USING btree (office_id, occurred_at)`
- `serpro_api_usage_entries_office_id_service_code_occurred_at_ind`: `CREATE INDEX serpro_api_usage_entries_office_id_service_code_occurred_at_ind ON public.serpro_api_usage_entries USING btree (office_id, service_code, occurred_at)`
- `serpro_api_usage_entries_pkey`: `CREATE UNIQUE INDEX serpro_api_usage_entries_pkey ON public.serpro_api_usage_entries USING btree (id)`
- `serpro_api_usage_entries_price_version_id_index`: `CREATE INDEX serpro_api_usage_entries_price_version_id_index ON public.serpro_api_usage_entries USING btree (price_version_id)`
- `serpro_usage_operation_key_idx`: `CREATE INDEX serpro_usage_operation_key_idx ON public.serpro_api_usage_entries USING btree (operation_key)`
- `serpro_usage_request_tag_idx`: `CREATE INDEX serpro_usage_request_tag_idx ON public.serpro_api_usage_entries USING btree (request_tag)`

### `serpro_usage_monthly_aggregates` (1 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('serpro_usage_monthly_aggregates_id_seq'::regclass)` |
| `scope` | varchar/varchar | NO | `` |
| `office_id` | bigint/int8 | YES | `` |
| `period_year` | smallint/int2 | NO | `` |
| `period_month` | smallint/int2 | NO | `` |
| `system_code` | varchar/varchar | YES | `` |
| `service_code` | varchar/varchar | YES | `` |
| `consumption_class` | varchar/varchar | YES | `` |
| `aggregate_key` | varchar/varchar | NO | `` |
| `entry_count` | bigint/int8 | NO | `'0'::bigint` |
| `total_quantity` | bigint/int8 | NO | `'0'::bigint` |
| `total_estimated_cost_micros` | bigint/int8 | NO | `'0'::bigint` |
| `unknown_class_count` | bigint/int8 | NO | `'0'::bigint` |
| `billable_attempt_count` | bigint/int8 | NO | `'0'::bigint` |
| `recomputed_at` | timestamptz/timestamptz | NO | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `serpro_usage_monthly_aggregates_pkey` (id)
- UNIQUE: `serpro_usage_monthly_aggregates_aggregate_key_unique` (aggregate_key)

**FKs:**
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `serpro_usage_monthly_aggregates_aggregate_key_unique`: `CREATE UNIQUE INDEX serpro_usage_monthly_aggregates_aggregate_key_unique ON public.serpro_usage_monthly_aggregates USING btree (aggregate_key)`
- `serpro_usage_monthly_aggregates_office_id_period_year_period_mo`: `CREATE INDEX serpro_usage_monthly_aggregates_office_id_period_year_period_mo ON public.serpro_usage_monthly_aggregates USING btree (office_id, period_year, period_month)`
- `serpro_usage_monthly_aggregates_pkey`: `CREATE UNIQUE INDEX serpro_usage_monthly_aggregates_pkey ON public.serpro_usage_monthly_aggregates USING btree (id)`
- `serpro_usage_monthly_aggregates_scope_period_year_period_month_`: `CREATE INDEX serpro_usage_monthly_aggregates_scope_period_year_period_month_ ON public.serpro_usage_monthly_aggregates USING btree (scope, period_year, period_month)`

### `fiscal_monitoring_runs` (173 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('fiscal_monitoring_runs_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `fiscal_category_id` | bigint/int8 | YES | `` |
| `competence_id` | bigint/int8 | YES | `` |
| `schedule_id` | bigint/int8 | YES | `` |
| `last_update_event_id` | bigint/int8 | YES | `` |
| `system_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | NO | `` |
| `trigger` | varchar/varchar | NO | `` |
| `idempotency_key` | varchar/varchar | NO | `` |
| `status` | varchar/varchar | NO | `'QUEUED'::character varying` |
| `result` | varchar/varchar | YES | `` |
| `situation` | varchar/varchar | NO | `'UNKNOWN'::character varying` |
| `coverage` | varchar/varchar | NO | `'UNKNOWN'::character varying` |
| `mutability` | varchar/varchar | NO | `'READ_ONLY'::character varying` |
| `attempt` | integer/int4 | NO | `1` |
| `parent_run_id` | bigint/int8 | YES | `` |
| `correlation_id` | varchar/varchar | YES | `` |
| `progress_cursor` | varchar/varchar | YES | `` |
| `progress` | json/json | YES | `` |
| `items_processed` | integer/int4 | NO | `0` |
| `pages_processed` | integer/int4 | NO | `0` |
| `skip_reason` | varchar/varchar | YES | `` |
| `error_code` | varchar/varchar | YES | `` |
| `error_message` | varchar/varchar | YES | `` |
| `lease_owner` | varchar/varchar | YES | `` |
| `locked_at` | timestamptz/timestamptz | YES | `` |
| `triggered_by` | bigint/int8 | YES | `` |
| `started_at` | timestamptz/timestamptz | YES | `` |
| `finished_at` | timestamptz/timestamptz | YES | `` |
| `requeued_at` | timestamptz/timestamptz | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `source_provenance` | varchar/varchar | YES | `` |
| `verification_state` | varchar/varchar | YES | `` |
| `operation_key` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `fiscal_monitoring_runs_pkey` (id)
- UNIQUE: `fmr_office_idempotency_uq` (office_id,idempotency_key)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `competence_id` → `fiscal_competences.id` ON DELETE SET NULL
- `fiscal_category_id` → `fiscal_categories.id` ON DELETE SET NULL
- `last_update_event_id` → `fiscal_last_update_events.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE
- `parent_run_id` → `fiscal_monitoring_runs.id` ON DELETE SET NULL
- `schedule_id` → `fiscal_monitoring_schedules.id` ON DELETE SET NULL
- `triggered_by` → `users.id` ON DELETE SET NULL

**Índices:**
- `fiscal_monitoring_runs_pkey`: `CREATE UNIQUE INDEX fiscal_monitoring_runs_pkey ON public.fiscal_monitoring_runs USING btree (id)`
- `fiscal_monitoring_runs_provenance_idx`: `CREATE INDEX fiscal_monitoring_runs_provenance_idx ON public.fiscal_monitoring_runs USING btree (source_provenance)`
- `fmr_office_client_sys_idx`: `CREATE INDEX fmr_office_client_sys_idx ON public.fiscal_monitoring_runs USING btree (office_id, client_id, system_code, service_code)`
- `fmr_office_competence_idx`: `CREATE INDEX fmr_office_competence_idx ON public.fiscal_monitoring_runs USING btree (office_id, competence_id)`
- `fmr_office_idempotency_uq`: `CREATE UNIQUE INDEX fmr_office_idempotency_uq ON public.fiscal_monitoring_runs USING btree (office_id, idempotency_key)`
- `fmr_office_status_created_idx`: `CREATE INDEX fmr_office_status_created_idx ON public.fiscal_monitoring_runs USING btree (office_id, status, created_at)`
- `fmr_status_locked_idx`: `CREATE INDEX fmr_status_locked_idx ON public.fiscal_monitoring_runs USING btree (status, locked_at)`

### `fiscal_snapshots` (147 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('fiscal_snapshots_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `run_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `competence_id` | bigint/int8 | YES | `` |
| `evidence_artifact_id` | bigint/int8 | YES | `` |
| `system_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | YES | `` |
| `situation` | varchar/varchar | NO | `` |
| `coverage` | varchar/varchar | NO | `` |
| `version` | integer/int4 | NO | `1` |
| `is_current` | boolean/bool | NO | `true` |
| `normalized` | json/json | YES | `` |
| `observed_at` | timestamptz/timestamptz | NO | `` |
| `created_at` | timestamptz/timestamptz | NO | `CURRENT_TIMESTAMP` |
| `source_provenance` | varchar/varchar | YES | `` |
| `verification_state` | varchar/varchar | YES | `` |
| `operation_key` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `fiscal_snapshots_pkey` (id)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `competence_id` → `fiscal_competences.id` ON DELETE SET NULL
- `evidence_artifact_id` → `fiscal_evidence_artifacts.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE
- `run_id` → `fiscal_monitoring_runs.id` ON DELETE CASCADE

**Índices:**
- `fiscal_snapshots_pkey`: `CREATE UNIQUE INDEX fiscal_snapshots_pkey ON public.fiscal_snapshots USING btree (id)`
- `fiscal_snapshots_provenance_idx`: `CREATE INDEX fiscal_snapshots_provenance_idx ON public.fiscal_snapshots USING btree (source_provenance)`
- `fs_current_lookup_idx`: `CREATE INDEX fs_current_lookup_idx ON public.fiscal_snapshots USING btree (office_id, client_id, system_code, service_code, is_current)`
- `fs_office_comp_current_idx`: `CREATE INDEX fs_office_comp_current_idx ON public.fiscal_snapshots USING btree (office_id, competence_id, is_current)`
- `fs_office_run_idx`: `CREATE INDEX fs_office_run_idx ON public.fiscal_snapshots USING btree (office_id, run_id)`

### `tax_guides` (7 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('tax_guides_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `client_id` | bigint/int8 | NO | `` |
| `establishment_id` | bigint/int8 | YES | `` |
| `system_code` | varchar/varchar | NO | `` |
| `service_code` | varchar/varchar | NO | `` |
| `operation_code` | varchar/varchar | NO | `'EMITIR_GUIA'::character varying` |
| `competence_period_key` | varchar/varchar | YES | `` |
| `debit_ref` | varchar/varchar | YES | `` |
| `logical_key` | varchar/varchar | NO | `` |
| `current_version_id` | bigint/int8 | YES | `` |
| `payment_status` | varchar/varchar | NO | `'UNKNOWN'::character varying` |
| `payment_confirmed_at` | timestamptz/timestamptz | YES | `` |
| `payment_source` | varchar/varchar | YES | `` |
| `payment_external_id` | varchar/varchar | YES | `` |
| `amount_cents` | bigint/int8 | YES | `` |
| `currency` | varchar/varchar | NO | `'BRL'::character varying` |
| `due_at` | timestamptz/timestamptz | YES | `` |
| `identifier_code` | varchar/varchar | YES | `` |
| `created_by` | bigint/int8 | YES | `` |
| `metadata` | json/json | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `tax_guides_pkey` (id)
- UNIQUE: `tg_office_logical_uq` (office_id,logical_key)

**FKs:**
- `client_id` → `clients.id` ON DELETE CASCADE
- `created_by` → `users.id` ON DELETE SET NULL
- `current_version_id` → `tax_guide_versions.id` ON DELETE SET NULL
- `establishment_id` → `establishments.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE

**Índices:**
- `tax_guides_pkey`: `CREATE UNIQUE INDEX tax_guides_pkey ON public.tax_guides USING btree (id)`
- `tg_office_client_pay_idx`: `CREATE INDEX tg_office_client_pay_idx ON public.tax_guides USING btree (office_id, client_id, payment_status)`
- `tg_office_due_idx`: `CREATE INDEX tg_office_due_idx ON public.tax_guides USING btree (office_id, due_at)`
- `tg_office_logical_uq`: `CREATE UNIQUE INDEX tg_office_logical_uq ON public.tax_guides USING btree (office_id, logical_key)`
- `tg_office_sys_svc_idx`: `CREATE INDEX tg_office_sys_svc_idx ON public.tax_guides USING btree (office_id, system_code, service_code)`

### `tax_guide_versions` (7 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('tax_guide_versions_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `tax_guide_id` | bigint/int8 | NO | `` |
| `version_number` | integer/int4 | NO | `` |
| `is_current` | boolean/bool | NO | `false` |
| `emission_status` | varchar/varchar | NO | `'PENDING'::character varying` |
| `replaces_version_id` | bigint/int8 | YES | `` |
| `superseded_by_version_id` | bigint/int8 | YES | `` |
| `identifier_code` | varchar/varchar | YES | `` |
| `amount_cents` | bigint/int8 | YES | `` |
| `currency` | varchar/varchar | NO | `'BRL'::character varying` |
| `due_at` | timestamptz/timestamptz | YES | `` |
| `valid_until` | timestamptz/timestamptz | YES | `` |
| `content_sha256` | varchar/varchar | YES | `` |
| `vault_object_id` | varchar/varchar | YES | `` |
| `content_type` | varchar/varchar | YES | `` |
| `byte_size` | bigint/int8 | NO | `'0'::bigint` |
| `idempotency_key` | varchar/varchar | NO | `` |
| `correlation_id` | varchar/varchar | YES | `` |
| `usage_reservation_id` | bigint/int8 | YES | `` |
| `remote_protocol` | varchar/varchar | YES | `` |
| `risk_level` | varchar/varchar | NO | `'HIGH'::character varying` |
| `confirmation_summary` | json/json | YES | `` |
| `confirmed_by_user_id` | bigint/int8 | YES | `` |
| `confirmed_at` | timestamptz/timestamptz | YES | `` |
| `issued_by` | bigint/int8 | YES | `` |
| `sent_at` | timestamptz/timestamptz | YES | `` |
| `finished_at` | timestamptz/timestamptz | YES | `` |
| `reconcile_after` | timestamptz/timestamptz | YES | `` |
| `reconcile_attempts` | smallint/int2 | NO | `'0'::smallint` |
| `error_code` | varchar/varchar | YES | `` |
| `error_message` | varchar/varchar | YES | `` |
| `metadata` | json/json | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `tax_guide_versions_pkey` (id)
- UNIQUE: `tgv_office_guide_ver_uq` (office_id,tax_guide_id,version_number)
- UNIQUE: `tgv_office_idem_uq` (office_id,idempotency_key)

**FKs:**
- `confirmed_by_user_id` → `users.id` ON DELETE SET NULL
- `issued_by` → `users.id` ON DELETE SET NULL
- `office_id` → `offices.id` ON DELETE CASCADE
- `replaces_version_id` → `tax_guide_versions.id` ON DELETE SET NULL
- `superseded_by_version_id` → `tax_guide_versions.id` ON DELETE SET NULL
- `tax_guide_id` → `tax_guides.id` ON DELETE CASCADE

**Índices:**
- `tax_guide_versions_pkey`: `CREATE UNIQUE INDEX tax_guide_versions_pkey ON public.tax_guide_versions USING btree (id)`
- `tgv_office_guide_current_idx`: `CREATE INDEX tgv_office_guide_current_idx ON public.tax_guide_versions USING btree (office_id, tax_guide_id, is_current)`
- `tgv_office_guide_ver_uq`: `CREATE UNIQUE INDEX tgv_office_guide_ver_uq ON public.tax_guide_versions USING btree (office_id, tax_guide_id, version_number)`
- `tgv_office_idem_uq`: `CREATE UNIQUE INDEX tgv_office_idem_uq ON public.tax_guide_versions USING btree (office_id, idempotency_key)`
- `tgv_office_status_recon_idx`: `CREATE INDEX tgv_office_status_recon_idx ON public.tax_guide_versions USING btree (office_id, emission_status, reconcile_after)`

### `offices` (2 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('offices_id_seq'::regclass)` |
| `name` | varchar/varchar | NO | `` |
| `slug` | varchar/varchar | NO | `` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `deadline_timezone` | varchar/varchar | YES | `` |

**Chaves:**
- PRIMARY KEY: `offices_pkey` (id)
- UNIQUE: `offices_slug_unique` (slug)

**Índices:**
- `offices_pkey`: `CREATE UNIQUE INDEX offices_pkey ON public.offices USING btree (id)`
- `offices_slug_unique`: `CREATE UNIQUE INDEX offices_slug_unique ON public.offices USING btree (slug)`

### `office_user` (3 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('office_user_id_seq'::regclass)` |
| `office_id` | bigint/int8 | NO | `` |
| `user_id` | bigint/int8 | NO | `` |
| `role` | varchar/varchar | NO | `` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `office_user_pkey` (id)
- UNIQUE: `office_user_office_id_user_id_unique` (office_id,user_id)

**FKs:**
- `office_id` → `offices.id` ON DELETE CASCADE
- `user_id` → `users.id` ON DELETE CASCADE

**Índices:**
- `office_user_office_id_user_id_unique`: `CREATE UNIQUE INDEX office_user_office_id_user_id_unique ON public.office_user USING btree (office_id, user_id)`
- `office_user_pkey`: `CREATE UNIQUE INDEX office_user_pkey ON public.office_user USING btree (id)`
- `office_user_user_id_is_active_index`: `CREATE INDEX office_user_user_id_is_active_index ON public.office_user USING btree (user_id, is_active)`

### `users` (3 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('users_id_seq'::regclass)` |
| `name` | varchar/varchar | NO | `` |
| `email` | varchar/varchar | NO | `` |
| `email_verified_at` | timestamp/timestamp | YES | `` |
| `password` | varchar/varchar | NO | `` |
| `remember_token` | varchar/varchar | YES | `` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |
| `two_factor_secret` | text/text | YES | `` |
| `two_factor_recovery_codes` | text/text | YES | `` |
| `two_factor_confirmed_at` | timestamp/timestamp | YES | `` |
| `is_active` | boolean/bool | NO | `true` |
| `selected_office_id` | bigint/int8 | YES | `` |

**Chaves:**
- PRIMARY KEY: `users_pkey` (id)
- UNIQUE: `users_email_unique` (email)

**FKs:**
- `selected_office_id` → `offices.id` ON DELETE SET NULL

**Índices:**
- `users_email_unique`: `CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email)`
- `users_pkey`: `CREATE UNIQUE INDEX users_pkey ON public.users USING btree (id)`

### `platform_memberships` (0 linhas)

| Coluna | Tipo | Null | Default |
|---|---|---|---|
| `id` | bigint/int8 | NO | `nextval('platform_memberships_id_seq'::regclass)` |
| `user_id` | bigint/int8 | NO | `` |
| `role` | varchar/varchar | NO | `` |
| `is_active` | boolean/bool | NO | `true` |
| `created_at` | timestamp/timestamp | YES | `` |
| `updated_at` | timestamp/timestamp | YES | `` |

**Chaves:**
- PRIMARY KEY: `platform_memberships_pkey` (id)
- UNIQUE: `platform_memberships_user_id_role_unique` (user_id,role)

**FKs:**
- `user_id` → `users.id` ON DELETE CASCADE

**Índices:**
- `platform_memberships_pkey`: `CREATE UNIQUE INDEX platform_memberships_pkey ON public.platform_memberships USING btree (id)`
- `platform_memberships_user_id_is_active_index`: `CREATE INDEX platform_memberships_user_id_is_active_index ON public.platform_memberships USING btree (user_id, is_active)`
- `platform_memberships_user_id_role_unique`: `CREATE UNIQUE INDEX platform_memberships_user_id_role_unique ON public.platform_memberships USING btree (user_id, role)`

