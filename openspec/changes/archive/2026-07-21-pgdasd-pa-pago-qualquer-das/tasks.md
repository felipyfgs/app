## 1. N0 — Resolver e competências

- [x] 1.1 Atualizar `PgdasdDasPaymentStateResolver`: precedência “any `payment_located=true` → PAID” antes de UNPAID; cobrir PA misto pago+false
- [x] 1.2 Atualizar `PgdasdMonitoringQueryService::openPaymentCompetencies`: excluir `period_key` com ao menos um DAS `payment_located=true`; montante só sobre DAS `false` do PA restante
- [x] 1.3 Testes Unit do resolver (PA misto → PAID; só false → UNPAID) e Feature/portfolio da lista de competências (PA misto ausente da lista)

## 2. N1 — Gates

- [x] 2.1 Rodar testes afetados (`php artisan test` filtros Pgdasd payment/resolver/open competencies) e `vendor/bin/pint --test` nos arquivos tocados
  - Depende de: 1.1, 1.2, 1.3
- [x] 2.2 `npx @fission-ai/openspec@1.6.0 validate --changes --strict` (change `pgdasd-pa-pago-qualquer-das`)
  - Depende de: 1.1, 1.2, 1.3
