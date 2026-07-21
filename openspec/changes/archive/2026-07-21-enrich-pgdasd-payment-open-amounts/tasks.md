## 1. N0 — DTO GERAR_DAS

- [x] 1.1 Mapear `numeroDocumento` e `total`/`principal` em `DasGuideDto::fromIntegraBody` (+ teste unitário do DTO)
  - Depende de: change `pgdasd-pagamento-e-cnpj-cliente` @ marco `apply` (lista `payment_open_competencies` no código)

## 2. N1 — Enrich openPaymentCompetencies

- [x] 2.1 Em `PgdasdMonitoringQueryService::openPaymentCompetencies`, após `tax_guides`, resolver gaps via snapshot/evidência GERAR_DAS SUCCESS (batch, office-scoped; `total` → cents)
  - Depende de: 1.1
- [x] 2.2 Feature test: preferência `tax_guides`; fallback evidência; sem fonte → null; sem HTTP externo
  - Depende de: 2.1

## 3. N2 — Gates

- [x] 3.1 `vendor/bin/pint --test` + `php artisan test --filter=PgdasdPayment` (e filtro do DTO tocado)
  - Depende de: 2.2
- [x] 3.2 `npx @fission-ai/openspec@1.6.0 validate enrich-pgdasd-payment-open-amounts --strict` (+ `--specs --strict` se tocado main)
  - Depende de: 2.2
