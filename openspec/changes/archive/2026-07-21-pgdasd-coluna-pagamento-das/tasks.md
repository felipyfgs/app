## 1. N0 — Domínio API

- [x] 1.1 Criar enum `PgdasdDasPaymentState` com `label()` e resolver de precedência (PA esperado + `payment_located`)
- [x] 1.2 Enriquecer batch do `PgdasdMonitoringQueryService` com `payment_state` (+ reason/counts leves)
- [x] 1.3 Teste unitário PHP do resolver de pagamento

## 2. N1 — Carteira web

- [x] 2.1 Types + meta/labels em `pgdasd.ts` / `fiscal-modules.ts`
  Depende de: 1.2
- [x] 2.2 Coluna Pagamento em `pgdasd-table.ts` (após RBT12) com skeleton de consult pending
  Depende de: 2.1
- [x] 2.3 Teste unitário web das labels/meta
  Depende de: 2.1

## 3. N2 — Gates

- [x] 3.1 Pint + php artisan test (filtro pagamento/monitoring PGDAS)
  Depende de: 1.3
- [x] 3.2 Vitest unitário + validate OpenSpec da change
  Depende de: 2.3
