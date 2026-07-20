## 1. N0 — Inventário canônico (L0)

- [x] 1.1 Gerar/commitar `artifacts/api-routes.json`, `artifacts/web-pages.json`, `artifacts/summary.json` a partir de `route:list` + glob `app/pages`
- [x] 1.2 Gate API: teste que confere totais (e amostra de URIs) do inventário vs `artisan route:list`
  - Depende de: 1.1
  - Evidência: `php artisan test --filter=SurfaceInventory`
- [x] 1.3 Gate Web: teste que confere total de pages + flags redirect vs inventário
  - Depende de: 1.1
  - Evidência: `pnpm run test -- tests/unit/surface-inventory`

## 2. N1 — Unit fail-closed L2 (API)

- [x] 2.1 Unit: `SerproKillSwitchService`
- [x] 2.2 Unit: quantity limit `QUANTITY_LIMIT_NOT_CONFIGURED`
- [x] 2.3 Unit/Feature fino: `IntegraEligibilityService` / `AUTHORIZATION_MISSING`
- [x] 2.4 Unit: `ReceitaPortalProvider` transport → `PORTAL_UNAVAILABLE` (monitoring)
- [x] 2.5 Unit: `ManualConsultEligibilityGate`
- [x] 2.6 Unit: `PgmeiDividaAtiva24Codec` (+ projector mínimo se trivial)
  - Evidência: filtros SerproKillSwitch|Quantity|IntegraEligibility|ReceitaPortal|ManualConsult|Pgmei

## 3. N1 — Behavioral + Nuxt L3 (Web)

- [x] 3.1 Behavioral: `app/utils/pgmei.ts`
- [x] 3.2 Behavioral: `pgdasd.ts` / `pgdasd-action-items.ts`
- [x] 3.3 Behavioral: `monitoring-actions.ts`
- [x] 3.4 Behavioral: `serpro-selectors.ts` + `serpro-navigation.ts`
- [x] 3.5 Behavioral: smoke `useMonitoringWorkspace.ts`
- [x] 3.6 Dois `*.nuxt.test.ts` âncora (ex. detalhe fiscal monitoring + superfície serpro/admin ou ModuleDataTable)
  - Depende de: 1.3
  - Evidência: `pnpm run test` incluindo projeto nuxt

## 4. N2 — Smoke por cluster API (L1)

- [x] 4.1 Feature smoke cluster **fiscal** (GET+POST representativos; auth/tenant)
  - Depende de: 1.2
- [x] 4.2 Feature smoke cluster **serpro+mei**
- [x] 4.3 Feature smoke cluster **office+auth+onboarding**
- [x] 4.4 Feature smoke cluster **clients+documents**
- [x] 4.5 Feature smoke cluster **monitoring+platform**
- [x] 4.6 Feature smoke cluster **work+outbound**
  - Evidência: `php artisan test --filter=SurfaceSmoke`

## 5. N2 — Cobertura de seções UI ativas (L1/L3 complemento)

- [x] 5.1 Gate/behavioral: seções **clients** + **conta** (tabs/navigation utils já existentes estendidos)
- [x] 5.2 Behavioral: utils **dctfweb** / **sitfis-table** (monitoring restante)
- [x] 5.3 Gate: páginas **admin/serpro** listadas no inventário e ligadas a `serpro-navigation`
  - Depende de: 1.3, 3.4

## 6. N3 — Gates integrados

- [x] 6.1 API: pint nos tocados + `php artisan test --filter='SurfaceInventory|SurfaceSmoke|SerproKillSwitch|Quantity|IntegraEligibility|ReceitaPortal|ManualConsult|Pgmei'`
  - Depende de: 2.6, 4.6
- [x] 6.2 Web: `pnpm run test` (unit + nuxt novos) 
  - Depende de: 3.6, 5.3
- [x] 6.3 `npx @fission-ai/openspec@1.6.0 validate cobrir-testes-unitarios-criticos --type change --strict`
  - Depende de: 1.1, 6.1, 6.2
