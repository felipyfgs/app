# Backup/restore antes de schema SVRS egress (task 2.10)

**Change:** `add-resilient-svrs-nfe55-outbound-xml-retrieval`  
**Data:** 2026-07-15

## Evidência

- Drill de backup/restore da instância já registrado em `docs/ops/ma-outbound-backup-drill-2026-07-15.md` (G0 MA outbound).
- Migration `2026_07_15_120000_create_svrs_egress_cohort_and_extend_attempts` é **aditiva** (tabela de coorte + colunas nullable em attempts/requests).
- `down()` não remove `dfe_documents`, aquisições nem XML de vault.

## Aplicação em ambiente com dados fiscais reais

1. Confirmar último backup bem-sucedido.
2. `php artisan migrate` (já aplicado em docker local).
3. Rollback de schema se necessário: `migrate:rollback` da batch — só remove metadados de governança.

Nenhuma chamada real ao portal SVRS foi feita nesta etapa.
