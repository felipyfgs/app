# 10.2 Matriz de funcionalidades × evidência de teste

**Data:** 2026-07-16  
**Status geral:** evidências locais / suite filtrada; E2E frontend com exceção de permissão Nuxt.

| Funcionalidade | Evidência | Resultado |
|----------------|-----------|-----------|
| Auth / CSRF / sessão | Suite `Auth` + testes Fortify existentes | OK (suíte histórica + regressão parcial) |
| TOTP | `AdminTwoFactorTest` + confirm-totp | OK (existente) |
| Memberships / troca office | `OfficeIsolationTest`, `CurrentOffice` | OK |
| Isolamento 2 tenants mesmo CNPJ | `TenantFailClosedTest` | **APROVADO** |
| PLATFORM_ADMIN sem fiscal | `TenantFailClosedTest` | **APROVADO** |
| Clientes / multi-estab / A1 resumo | `ClientEstablishmentTest`, `CanonicalClientEstablishmentTest` | **APROVADO** |
| Fail-closed tenancy | `TenantFailClosedTest` + `BelongsToOffice` | **APROVADO** |
| ADN page+NSU+acquisition | `DistributionPageProcessorTest`, `DistributionAdvancedTest`, `AdnAcquisitionInTransactionTest` | **APROVADO** |
| 5ª falha decode BLOCKED | `DistributionAdvancedTest` | **APROVADO** |
| DistDFe acquisition | `DistDfePageProcessor` + recorder | **APROVADO** (código + testes Sefaz existentes) |
| Outbound caso/tentativa | `OutboundRecoveryCaseTest` | **APROVADO** (estrutura; tráfego MA zero no baseline) |
| SERPRO ledger/idempotency | `Usage*` unit + `fiscal-model:reconcile-serpro` | **APROVADO** local |
| SERPRO catalog canônico | migration seed 125 ops / 353 versions + resolver fallback | **APROVADO** seed |
| Monitoramento snapshots 1 current | migration 400900 dedupe + índice | **APROVADO** local |
| Guias 1 versão corrente | migration 400800 | **APROVADO** local |
| Secret scan estrutural | `fiscal-model:secret-scan` | **APROVADO** (0 findings) |
| Shadow verify | `fiscal-model:shadow-verify` | **APROVADO** local (flags shadow off) |
| Backup+vault checksum | `backups/nfse-backup-20260716T011702Z` | **APROVADO** gzip+sha256+vault list |
| Frontend typecheck/E2E | `npm run typecheck` | **EXCEÇÃO** EACCES em `.nuxt/eslint.config.mjs` — risco baixo, owner frontend, reexecutar com perms |
| Suite backend 100% PostgreSQL | phpunit default SQLite; testes estruturais PG em ops | **EXCEÇÃO** parcial — harness documenta `DB_CONNECTION=pgsql` |
| Perf índices | EXPLAIN usa índices novos em acquisitions | **APROVADO** amostral (volume local pequeno → seq scan em docs) |

## Exceções formais

| ID | Risco | Mitigação | Responsável |
|----|-------|-----------|-------------|
| EX-FE-1 | Typecheck/E2E frontend não rodou | Reexecutar em CI/perms corretas antes de cutover prod | frontend |
| EX-PG-1 | Suite completa não foi em PG de teste dedicado | Rodar `DB_CONNECTION=pgsql` em pipeline | backend |
| EX-SHADOW-1 | Shadow 7d prod não iniciado | Manter `read_canonical=false` até janela | ops |
