# Worktree snapshot 2026-07-15T22:23:34-03:00

## Branch
main

## Status short
 M backend/app/Enums/DocumentAcquisitionSource.php
 M backend/app/Http/Controllers/Api/V1/ClientController.php
 M backend/app/Http/Controllers/Api/V1/Work/OperationalProcessController.php
 M backend/app/Http/Controllers/Api/V1/Work/OperationalTaskController.php
 M backend/app/Models/Client.php
 M backend/app/Models/Concerns/BelongsToOffice.php
 M backend/app/Providers/AppServiceProvider.php
 M backend/app/Services/Adn/DistributionPageProcessor.php
 M backend/app/Services/Clients/CreateClientWithEstablishment.php
 M backend/app/Services/Sefaz/DistDfePageProcessor.php
 M backend/app/Services/Serpro/Catalog/OperationCoordinateResolver.php
 M backend/app/Support/LogSanitizer.php
 M backend/database/factories/OfficeFactory.php
 M backend/database/migrations/2026_07_16_300400_create_operational_processes_and_tasks.php
 M backend/phpunit.xml
 M backend/routes/api.php
 M backend/tests/Feature/Clients/ClientEstablishmentTest.php
 M backend/tests/Feature/Work/OperationalWorkCoreTest.php
 M backend/tests/Support/ApiSecretScanner.php
 M docs/ops/consolidate-fiscal-data-model/02-migrations-inventory.md
 M frontend/app/composables/useApi.ts
 M frontend/app/pages/index.vue
 M frontend/app/pages/login.vue
 M frontend/app/pages/two-factor-challenge.vue
 M frontend/app/utils/navigation.ts
 M frontend/app/utils/permissions.ts
 M frontend/tests/e2e/support/api-fixtures.ts
 M frontend/tests/security/scan-artifacts.mjs
 M frontend/tests/unit/navigation.test.ts
 M openspec/changes/add-operational-process-management/tasks.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/design.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/proposal.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/specs/frontend-dashboard-experience/spec.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/tasks.md
 M openspec/changes/consolidate-fiscal-data-model/tasks.md
?? backend/app/Console/Commands/FiscalModelBackfillCommand.php
?? backend/app/Console/Commands/FiscalModelReconcileCommand.php
?? backend/app/Console/Commands/FiscalModelSecretScanCommand.php
?? backend/app/Console/Commands/FiscalModelSerproReconcileCommand.php
?? backend/app/Console/Commands/FiscalModelShadowVerifyCommand.php
?? backend/app/Console/Commands/WorkCleanupCommand.php
?? backend/app/Services/FiscalDataModel/
?? backend/app/Services/Work/OperationalTaskStructureService.php
?? backend/app/Services/Work/OperationalTimelineQuery.php
?? backend/app/Services/Work/OperationalVaultCleanupService.php
?? backend/app/Support/FiscalDataModel/
?? backend/config/fiscal_data_model.php
?? backend/database/migrations/2026_07_16_400000_create_fiscal_model_migration_harness_tables.php
?? backend/database/migrations/2026_07_16_400100_add_office_id_composite_candidate_keys.php
?? backend/database/migrations/2026_07_16_400200_add_client_root_partial_unique_and_checks.php
?? backend/database/migrations/2026_07_16_400300_document_acquisition_source_keys_and_junction.php
?? backend/database/migrations/2026_07_16_400400_restrict_delete_on_fiscal_evidence_fks.php
?? backend/database/migrations/2026_07_16_400500_create_outbound_recovery_cases_tables.php
?? backend/database/migrations/2026_07_16_400600_serpro_canonical_operation_and_split_aggregates.php
?? backend/database/migrations/2026_07_16_400700_internal_state_checks_and_snapshot_current.php
?? backend/database/migrations/2026_07_16_400800_tax_guide_one_current_version_and_payment_check.php
?? backend/database/migrations/2026_07_16_400900_dedupe_fiscal_snapshot_current.php
?? backend/database/seeders/OperationalWorkDemoSeeder.php
?? backend/tests/Feature/Clients/CanonicalClientEstablishmentTest.php
?? backend/tests/Feature/FiscalDataModel/
?? backend/tests/Feature/Work/OperationalGenerationConcurrencyTest.php
?? backend/tests/Feature/Work/OperationalWorkSmokeCrossTenantTest.php
?? backend/tests/Unit/FiscalDataModel/
?? docs/ops/archive/2026-07-16/
?? docs/ops/consolidate-fiscal-data-model/10-harness-and-job-versioning.md
?? docs/ops/consolidate-fiscal-data-model/11-enum-classification.md
?? docs/ops/consolidate-fiscal-data-model/12-functionality-evidence-matrix.md
?? docs/ops/consolidate-fiscal-data-model/final-gate-report-2026-07-16.md
?? docs/ops/operational-process-migration-strategy.md
?? docs/ops/operational-process-pilot-notes.md
?? frontend/app/components/home/WorkKpisBlock.vue
?? frontend/app/components/notes/CteCatalogContext.vue
?? frontend/app/pages/admin/departments.vue
?? frontend/app/pages/work/
?? frontend/app/types/work.ts
?? frontend/tests/e2e/support/work-fixtures.ts
?? frontend/tests/e2e/work-module.spec.ts
?? frontend/tests/unit/work-permissions.test.ts
?? frontend/tests/unit/work-states.test.ts
?? openspec/changes/integrate-cte-into-document-catalog/
?? openspec/changes/refactor-complete-dashboard-ui-ux/

## Por família (paths modificados/não rastreados)

### backend (outras changes — NÃO sobrescrever nesta change)
 M backend/app/Enums/DocumentAcquisitionSource.php
 M backend/app/Http/Controllers/Api/V1/ClientController.php
 M backend/app/Http/Controllers/Api/V1/Work/OperationalProcessController.php
 M backend/app/Http/Controllers/Api/V1/Work/OperationalTaskController.php
 M backend/app/Models/Client.php
 M backend/app/Models/Concerns/BelongsToOffice.php
 M backend/app/Providers/AppServiceProvider.php
 M backend/app/Services/Adn/DistributionPageProcessor.php
 M backend/app/Services/Clients/CreateClientWithEstablishment.php
 M backend/app/Services/Sefaz/DistDfePageProcessor.php
 M backend/app/Services/Serpro/Catalog/OperationCoordinateResolver.php
 M backend/app/Support/LogSanitizer.php
 M backend/database/factories/OfficeFactory.php
 M backend/database/migrations/2026_07_16_300400_create_operational_processes_and_tasks.php
 M backend/phpunit.xml
 M backend/routes/api.php
 M backend/tests/Feature/Clients/ClientEstablishmentTest.php
 M backend/tests/Feature/Work/OperationalWorkCoreTest.php
 M backend/tests/Support/ApiSecretScanner.php
?? backend/app/Console/Commands/FiscalModelBackfillCommand.php
?? backend/app/Console/Commands/FiscalModelReconcileCommand.php
?? backend/app/Console/Commands/FiscalModelSecretScanCommand.php
?? backend/app/Console/Commands/FiscalModelSerproReconcileCommand.php
?? backend/app/Console/Commands/FiscalModelShadowVerifyCommand.php
?? backend/app/Console/Commands/WorkCleanupCommand.php
?? backend/app/Services/FiscalDataModel/
?? backend/app/Services/Work/OperationalTaskStructureService.php
?? backend/app/Services/Work/OperationalTimelineQuery.php
?? backend/app/Services/Work/OperationalVaultCleanupService.php
?? backend/app/Support/FiscalDataModel/
?? backend/config/fiscal_data_model.php
?? backend/database/migrations/2026_07_16_400000_create_fiscal_model_migration_harness_tables.php
?? backend/database/migrations/2026_07_16_400100_add_office_id_composite_candidate_keys.php
?? backend/database/migrations/2026_07_16_400200_add_client_root_partial_unique_and_checks.php
?? backend/database/migrations/2026_07_16_400300_document_acquisition_source_keys_and_junction.php
?? backend/database/migrations/2026_07_16_400400_restrict_delete_on_fiscal_evidence_fks.php
?? backend/database/migrations/2026_07_16_400500_create_outbound_recovery_cases_tables.php
?? backend/database/migrations/2026_07_16_400600_serpro_canonical_operation_and_split_aggregates.php
?? backend/database/migrations/2026_07_16_400700_internal_state_checks_and_snapshot_current.php
?? backend/database/migrations/2026_07_16_400800_tax_guide_one_current_version_and_payment_check.php
?? backend/database/migrations/2026_07_16_400900_dedupe_fiscal_snapshot_current.php
?? backend/database/seeders/OperationalWorkDemoSeeder.php
?? backend/tests/Feature/Clients/CanonicalClientEstablishmentTest.php
?? backend/tests/Feature/FiscalDataModel/
?? backend/tests/Feature/Work/OperationalGenerationConcurrencyTest.php
?? backend/tests/Feature/Work/OperationalWorkSmokeCrossTenantTest.php
?? backend/tests/Unit/FiscalDataModel/

### frontend shell/nav/auth
 M frontend/app/pages/index.vue
 M frontend/app/pages/login.vue
 M frontend/app/pages/two-factor-challenge.vue
 M frontend/app/utils/navigation.ts
 M frontend/app/utils/permissions.ts
 M frontend/app/composables/useApi.ts
 M frontend/app/pages/index.vue
 M frontend/app/pages/login.vue
 M frontend/app/pages/two-factor-challenge.vue
 M frontend/app/utils/navigation.ts
 M frontend/app/utils/permissions.ts

### frontend work (add-operational-process-management)
?? frontend/app/components/home/WorkKpisBlock.vue
?? frontend/app/pages/work/
?? frontend/app/types/work.ts
?? frontend/tests/e2e/support/work-fixtures.ts
?? frontend/tests/e2e/work-module.spec.ts
?? frontend/tests/unit/work-permissions.test.ts
?? frontend/tests/unit/work-states.test.ts

### frontend outros
 M frontend/app/composables/useApi.ts
?? frontend/app/components/home/WorkKpisBlock.vue
?? frontend/app/components/notes/CteCatalogContext.vue
?? frontend/app/pages/admin/departments.vue

### openspec changes concorrentes
 M openspec/changes/add-operational-process-management/tasks.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/design.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/proposal.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/specs/frontend-dashboard-experience/spec.md
 M openspec/changes/complete-cte-capture-with-distdfe-autxml-and-import/tasks.md
 M openspec/changes/consolidate-fiscal-data-model/tasks.md
?? openspec/changes/integrate-cte-into-document-catalog/
?? openspec/changes/refactor-complete-dashboard-ui-ux/

### docs
 M docs/ops/consolidate-fiscal-data-model/02-migrations-inventory.md
?? docs/ops/archive/2026-07-16/
?? docs/ops/consolidate-fiscal-data-model/10-harness-and-job-versioning.md
?? docs/ops/consolidate-fiscal-data-model/11-enum-classification.md
?? docs/ops/consolidate-fiscal-data-model/12-functionality-evidence-matrix.md
?? docs/ops/consolidate-fiscal-data-model/final-gate-report-2026-07-16.md
?? docs/ops/operational-process-migration-strategy.md
?? docs/ops/operational-process-pilot-notes.md
