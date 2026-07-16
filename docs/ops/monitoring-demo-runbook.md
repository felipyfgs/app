# Runbook — ambiente demo de monitoramento fiscal

**Change:** `complete-monitoring-visual-fixtures`  
**Complementa:** `backend/docs/ops/fiscal-monitoring-demo.md` (detalhe de seeder e perfil `.env`)

Dataset sintético do hub **somente** para `local` / `testing`. Sem validade fiscal.  
**Nunca** roda em produção (guard de ambiente + slug).

## Origem dos dados

| Item | Valor |
|------|--------|
| Office | slug `demo` (`FISCAL_DEMO_OFFICE_SLUG`) |
| Sentinela (isolamento) | slug `demo-sentinel` |
| Ambientes | `local`, `testing` |
| Data-âncora | `DEMO_FISCAL_ANCHOR_AT` (default `2026-06-15T12:00:00-03:00`) |
| Manifesto | `Database\Seeders\Demo\FiscalDemoManifest` — ~18 clientes sintéticos |
| Marcadores | notes/`metadata` com `[demo-fixture]`; `correlation_id` prefixo `DEMO_` |
| Proveniência API | `data_origin=DEMO` / `is_synthetic=true` no office demo |
| Frontend e2e | `frontend/tests/e2e/support/monitoring-fixtures.ts` (mocks Playwright, sem DB) |

Conteúdos de evidência/mailbox/guias levam a marca **DEMONSTRAÇÃO — SEM VALIDADE FISCAL**.

## Reset / recriação

```bash
# Docker Compose (serviço php)
docker compose exec -T php php artisan fiscal:demo-seed

# Host com PHP apontando para o mesmo .env local
php artisan fiscal:demo-seed
```

- Idempotente: purga **apenas** fixtures marcadas no office `demo` e recria.
- Outros tenants **não** são tocados (inclui sentinela).
- Contagens impressas são sanitizadas (sem `vault_object_id`, tokens, material criptográfico).

Rodar **duas vezes** e comparar contagens (clients, runs, snapshots, mailbox, guides, installments, declarations, sentinel_office) — devem estabilizar.

### Contagens estáveis (local, 2026-07-15)

| métrica | valor |
|---------|------:|
| clients | 18 |
| runs | 50 |
| snapshots | 24 |
| mailbox_messages | 5 |
| guides | 7 |
| installment_orders | 6 |
| declarations | 64 |
| sentinel_office | 1 |

### Gap de schema local (tax_guides legado)

Se o seeder falhar com `column "establishment_id" of relation "tax_guides" does not exist`, o banco tem schema antigo de `tax_guides` (migration `246000` com `if (!Schema::hasTable)` não recria). Em **local**:

```bash
docker compose exec -T php php artisan tinker --execute="
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema;
if (Schema::hasTable('tax_guides') && !Schema::hasColumn('tax_guides', 'logical_key')) {
  DB::statement('DROP TABLE IF EXISTS tax_guide_payment_confirmations CASCADE');
  DB::statement('DROP TABLE IF EXISTS tax_guide_download_tokens CASCADE');
  DB::statement('DROP TABLE IF EXISTS tax_guide_versions CASCADE');
  DB::statement('DROP TABLE IF EXISTS tax_guides CASCADE');
  DB::table('migrations')->where('migration', 'like', '%246000_create_tax_guide%')->delete();
}
"
docker compose exec -T php php artisan migrate --force
docker compose exec -T php php artisan fiscal:demo-seed
```

Não usar CASCADE em produção com dados reais.

## Produção sem demo (10.6)

| Controle | Comportamento |
|----------|----------------|
| `APP_ENV=production` | `DemoEnvironmentGuard` e `fiscal:demo-seed` recusam execução |
| `FISCAL_DEMO_*` / `DEMO_*` em prod | Ignorados pelo guard; origem sempre `LIVE` |
| Bundle frontend | Build estático Nuxt **não** inclui `tests/e2e` nem manifesto PHP |
| Preflight mutações | Office demo → `DEMO_MODE` (bloqueio explícito) |
| Testes | `FiscalMonitoringDemoSeederTest::test_production_nao_carrega_seeder`, `FiscalDataOriginResolverTest` |

Produção vazia permanece vazia; erro de API não ativa fallback demo.

## Limitações FGTS

Cobertura **parcial** via eSocial (S-1299 / S-5003).

- `guide_status` e `payment_status` = `UNSUPPORTED` (sem API pública FGTS Digital M2M) — refletido nos estados da tabela/detalhe, sem banner permanente na UI
- Sem scraping, CAPTCHA, portal ou Gov.br

## Diagnóstico

```bash
docker compose exec -T php php artisan fiscal:demo-seed
# Esperado (ordem de grandeza): clients≈18, runs/snapshots > 0, sentinel_office=1

# Isolamento: mesmo CNPJ no demo-sentinel não aparece na carteira do office demo
# Proveniência: overview/clients com data_origin=DEMO no slug demo

# Backend fiscal
docker compose exec -T php php artisan test --filter=Fiscal

# Frontend: varredura de artefatos / fixtures
cd frontend && pnpm test:artifacts && pnpm exec vitest run tests/unit/monitoring-secrets-scan.test.ts
```

## Segurança

O seeder e as fixtures e2e **não** criam/exibem:

- PFX, senha, PEM, chave privada
- Consumer Secret / tokens SERPRO / Termo assinado
- XML fiscal real
- Contrato SERPRO sintético
- `vault_object_id` em API pública

## Ver também

- Perfil `.env` local: `backend/docs/ops/fiscal-monitoring-demo.md`
- Matriz de fidelidade UI: `docs/ops/monitoring-template-fidelity-matrix.md`
- Baseline da change: `docs/ops/complete-monitoring-visual-fixtures-baseline.md`
