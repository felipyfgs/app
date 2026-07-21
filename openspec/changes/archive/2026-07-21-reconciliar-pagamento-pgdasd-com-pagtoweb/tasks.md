## 1. N0 — Persistência e codec PAGTOWEB

- [x] 1.1 Migration aditiva em `pgdasd_operations`: `pagtoweb_payment_status`, `pagtoweb_verified_at`, `pagtoweb_paid_at`, `pagtoweb_amount_cents`, refs de run/item; model fillable/casts
- [x] 1.2 Estender `PagtowebPaymentListCodec`/adapter para `numeroDocumentoLista` (lotes ≤100/página) e matching por `document_digest` HMAC sem persistir número em claro
- [x] 1.3 Serviço de aplicação da evidência: DAS retornado → `PAID` + valor/`paid_at`; DAS do lote ausente na resposta → `NOT_FOUND` + `verified_at`; tenancy office-scoped

## 2. N1 — Orquestração e read model

- [x] 2.1 Pós-MONITOR PGDAS produtivo: enfileirar reconciliação PAGTOWEB (gaps sem `PAID` / cobertura negativa vencida), checando poder `00004` + kill switch + idempotência; sem enqueue → operações ficam sem cobertura (`UNVERIFIED`)
  - Depende de: 1.1, 1.2, 1.3
  - Depende de: change `pgdasd-pa-pago-qualquer-das` @ marco `apply`
- [x] 2.2 Backfill limitado (job/comando) para clientes ativos com DAS conhecidos; rate limit / budget SERPRO
  - Depende de: 1.1, 1.2, 1.3
- [x] 2.3 Atualizar `PgdasdDasPaymentStateResolver` à precedência PAGTOWEB (PAID permanente → UNPAID só com NOT_FOUND completo+TTL → UNVERIFIED)
  - Depende de: 1.1
- [x] 2.4 Atualizar `openPaymentCompetencies` para listar só PAs com cobertura negativa fresca; valor preferindo `pagtoweb_amount_cents` depois fallbacks locais; `Http`/Integra zero no GET
  - Depende de: 1.1, 2.3

## 3. N1 — Testes de evidência e falhas

- [x] 3.1 Unit codec/matching: lista por `numeroDocumentoLista`; digest casa com DAS; lote parcial marca só os consultados
  - Depende de: 1.2, 1.3
  - Evidência: `php artisan test --filter=Pagtoweb` (ou filtro específico do codec/projector)
- [x] 3.2 Unit resolver: PAID com um match; UNPAID com todos NOT_FOUND frescos; UNVERIFIED se parcial, TTL vencido, sem cobertura ou sem auth simulada
  - Depende de: 2.3
  - Evidência: `php artisan test --filter=PgdasdDasPaymentStateResolverTest`
- [x] 3.3 Feature portfolio: PA pago no PAGTOWEB ausente de `payment_open_competencies`; PA NOT_FOUND fresco entra; sem cobertura não entra; `Http::assertNothingSent()`
  - Depende de: 2.4
  - Evidência: `php artisan test --filter=PgdasdPaymentOpenCompetenciesTest`
- [x] 3.4 Feature/unit enqueue: sem poder `00004` não despacha; idempotência de lote; erro SERPRO não grava UNPAID falso
  - Depende de: 2.1
  - Evidência: teste Feature do pós-MONITOR / reconcile service

## 4. N2 — Gates

- [x] 4.1 `vendor/bin/pint --test` nos arquivos tocados + suite PHPUnit dos filtros acima
  - Depende de: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4
- [x] 4.2 Se payload/copy web mudar: `pnpm run lint` + `pnpm run typecheck` + teste unitário popover; senão N/A documentado
  - N/A: contrato e copy web preservados; a change alterou apenas persistência/orquestração/read model da API.
  - Depende de: 2.4
- [x] 4.3 `openspec validate --changes --strict` (change `reconciliar-pagamento-pgdasd-com-pagtoweb`)
  - Depende de: 1.1, 2.3, 2.4, 3.2, 3.3
