## 1. N0 — Agregação do débito do PA

- [x] 1.1 Em `PgdasdMonitoringQueryService::openPaymentCompetencies`, trocar soma por máximo dos `amount_cents` resolvidos dos DAS unpaid do PA (manter null se algum unpaid sem valor)
- [x] 1.2 Teste Feature: PA com N DAS unpaid do mesmo facial → `amount_cents` = facial único (não N×)
  - Evidência: `php artisan test --filter=PgdasdPaymentOpenCompetencies`

## 2. N1 — UI débito + gates

- [x] 2.1 Em `PaymentValue.vue`, aplicar `text-error` (ou equivalente semântico) ao valor monetário das linhas unpaid; `—` neutro
  - Depende de: 1.1
- [x] 2.2 `vendor/bin/pint --test` nos PHP tocados; `pnpm run test -- tests/unit/pgdasd.test.ts` (ou teste do componente); `openspec validate --changes --strict` (change `pgdasd-debito-pa-sem-somar-reemissoes`)
  - Depende de: 1.1, 1.2, 2.1
