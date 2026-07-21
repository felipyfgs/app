## 1. N0 — Schema e parser

- [x] 1.1 Migration aditiva em `pgdasd_operations`: `amount_cents`, `amount_source`, `amount_parser_version`, `amount_resolved_at` (+ FK opcional ao artefato se couber sem fricção)
  - Depende de: change `enrich-pgdasd-payment-open-amounts` @ marco `apply`
- [x] 1.2 Implementar `PgdasdExtratoDasAmountParser` (seção 6: `Número` + `Total` → cents) + testes unitários com fixture de texto real
  - Depende de: 1.1

## 2. N1 — Ingest Integra

- [x] 2.1 No pós-consulta CONSEXTRATO (`PgdasdPostConsultService` / caminho documental): parsear PDF e upsert amount na operação DAS matching
  - Depende de: 1.2
- [x] 2.2 No pós-consulta GERAR_DAS: persistir total estruturado na operação (`amount_source=GERAR_DAS`)
  - Depende de: 1.1
- [x] 2.3 Feature test ingest extrato → `pgdasd_operations.amount_cents` (vault fake / bytes fixture)
  - Depende de: 2.1

## 3. N1 — Portfolio + gap

- [x] 3.1 `openPaymentCompetencies`: ler `pgdasd_operations.amount_cents` após `tax_guides`; manter agregação fail-closed; sem Integra/pdftotext no GET
  - Depende de: 1.1
- [x] 3.2 Enfileirar CONSEXTRATO pós-MONITOR para DAS unpaid sem `amount_cents` (idempotência/rate-limit do pipeline RBT12)
  - Depende de: 2.1
- [x] 3.3 Feature test portfolio com operação persistida → `payment_open_competencies` com cents; `Http::assertNothingSent()`
  - Depende de: 3.1

## 4. N2 — Gates

- [x] 4.1 `vendor/bin/pint --test` + `php artisan test` filtros migration/parser/post-consult/portfolio
  - Depende de: 2.3, 3.3
- [x] 4.2 `npx @fission-ai/openspec@1.6.0 validate persist-pgdasd-operation-das-amount --strict`
  - Depende de: 3.3
