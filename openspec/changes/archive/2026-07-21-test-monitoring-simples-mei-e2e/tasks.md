## 1. N0 — Helpers e Feature HTTP da carteira

- [x] 1.1 Criar helper de seed (trait/factory helper) para office + operador/viewer + clientes SN/MEI + flag `simples_mei` habilitada, reutilizável nos Features da carteira
- [x] 1.2 Implementar Feature HTTP `overview`/`clients` PGDASD: sucesso, filtros (`situation`, client, `competence`, `send_status`), sort, paginação, isolamento de office, rejeição de `office_id` do client
  - Evidência: `php artisan test --filter=SimplesMeiPortfolioHttp`
- [x] 1.3 Estender/criar Feature de membership HTTP PGDASD: list, include, exclude; viewer negado em mutações; regime SN only
  - Evidência: `php artisan test --filter=MonitoringModuleMembership`

## 2. N1 — Feature HTTP consulta e comunicação

- [x] 2.1 Feature `POST /api/v1/fiscal/runs` + `GET /runs/{id}` para payload de consulta PGDAS-D da UI; `Http::fake` + `assertNothingSent`; job enfileirado ou estado terminal verificável
  - Depende de: 1.1
  - Evidência: `php artisan test --filter=PgdasdPortfolioConsultHttp`
- [x] 2.2 Feature comunicação PGDASD: preview, communications, PATCH preference (operador), POST send fail-closed; download autenticado sucesso + cross-tenant negado
  - Depende de: 1.1
  - Evidência: `php artisan test --filter=MonitoringCommunication`

## 3. N2 — Testes web behavioral da Portfolio

- [x] 3.1 Testes Vitest/Nuxt da carteira: carga overview+rows, filtros sincronizados com URL, seleção, consulta linha/bulk + pending/skeleton (`useSimplesMeiConsultPending`)
  - Evidência: `pnpm run test -- simples-nacional-portfolio-e2e`
- [x] 3.2 Testes membership associate/exclude e diferença de permissões viewer vs operador na Portfolio; reconciliar source-gate de comunicação se estiver obsoleto
  - Depende de: 3.1
  - Evidência: `pnpm run test -- monitoring-communication-informational`

## 4. N3 — E2E Playwright da rota

- [x] 4.1 Estender seed E2E se necessário (≥1 SN na carteira, ≥1 MEI fora do escopo) e criar/estender spec Playwright de `/monitoring/simples-mei`: operador (lista SN, sem MEI, sem 500), viewer (sem controles de sync/consulta), hosts externos bloqueados
  - Depende de: 3.2
  - Evidência: `pnpm run test:e2e -- monitoring` (local; fora do gate CI)
  - Nota: rota canônica atual é `/monitoring/simples`; legado `/monitoring/simples-mei` redireciona (coberto no spec).

## 5. N4 — Gates integrados

- [x] 5.1 Rodar gates API da área: `composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test` (filtros novos + suite tocada)
  - Depende de: 2.1, 2.2
  - Evidência: composer valid; pint PASS 5 files; 25 Feature tests PASS
- [x] 5.2 Rodar gates web da área: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (Playwright não entra no gate)
  - Depende de: 3.2, 4.1
  - Evidência: eslint limpo nos arquivos novos; `pnpm run test` 196 PASS; lint/typecheck globais do app já tinham erros pré-existentes fora do escopo desta change
- [x] 5.3 Validar OpenSpec da change: `npx @fission-ai/openspec@1.6.0 validate --changes --strict` (ou comando equivalente do repo) para `test-monitoring-simples-mei-e2e`
  - Depende de: 5.1, 5.2
  - Evidência: `openspec validate test-monitoring-simples-mei-e2e --type change --strict` → valid
