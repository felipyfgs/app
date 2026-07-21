## 1. N0 — Baseline e catálogo de casos de uso

- [x] 1.1 Reconciliar inventários de API e páginas com o working tree atual e fortalecer a paridade pelo conjunto exato de `método + URI` e arquivos/rotas, preservando notas de redirects
  - Evidência: `php artisan test --filter=SurfaceInventoryApiTest` e `pnpm run test -- tests/unit/surface-inventory.test.ts`
- [x] 1.2 Criar catálogo canônico das jornadas, atores, contextos de tenant/plataforma, grupos API, seções/páginas, integrações e evidências L0–L3; classificar toda superfície atual
  - Evidência: validação estrutural do catálogo no teste `use-case-testability-graph`

## 2. N1 — Grafo executável e gates

- [x] 2.1 Implementar gerador determinístico do grafo, snapshots com digest idêntico para API/web e relatório Markdown de cobertura/lacunas por jornada
  - Depende de: 1.1, 1.2
  - Evidência: `node scripts/generate-use-case-testability.mjs --check`
- [x] 2.2 Implementar gate PHPUnit que valide schema, digest, classificação integral das rotas, handlers e evidências API existentes
  - Depende de: 2.1
  - Evidência: `php artisan test --filter=UseCaseTestabilityGraphTest`
- [x] 2.3 Implementar gate Vitest que valide páginas exatas, jornadas críticas L1–L3, arquivos/âncoras de evidência e equivalência dos snapshots
  - Depende de: 2.1
  - Evidência: `pnpm run test -- tests/unit/use-case-testability-graph.test.ts`

## 3. N2 — Testabilidade das jornadas críticas

- [x] 3.1 Adicionar/estender Features HTTP para identidade/tenant, clientes, trabalho e monitoramento, com Sanctum, isolamento cross-office, papéis e providers fail-closed
  - Depende de: 2.2
  - Evidência: `php artisan test --filter=CriticalUseCaseJourneyApiTest`
- [x] 3.2 Adicionar behavioral Vitest das ligações UI/API e permissões das quatro jornadas críticas sem depender de navegador
  - Depende de: 2.3
  - Evidência: `pnpm run test -- tests/unit/critical-use-case-journeys.test.ts`
- [x] 3.3 Estender `FiscalMonitoringE2ESeeder` somente com fixtures determinísticas necessárias para clientes, tenant e trabalho, mantendo `APP_ENV=testing`
  - Depende de: 2.1
  - Evidência: `php artisan db:seed --class=FiscalMonitoringE2ESeeder` no stack E2E isolado
- [x] 3.4 Implementar Playwright das jornadas críticas com operador/viewer, troca de tenant, isolamento, controles read-only e bloqueio de todos os hosts externos
  - Depende de: 3.1, 3.2, 3.3
  - Evidência: `pnpm run test:e2e` (local, fora do CI) — 8 cenários aprovados

## 4. N3 — Gates integrados e levantamento final

- [x] 4.1 Executar gates API: `composer validate --strict --no-check-publish`, `vendor/bin/pint --test` e `php artisan test`
  - Depende de: 3.1, 3.3
  - Resultado: Pint global acusa 3 arquivos de produto já modificados; PHPUnit global tem 11 falhas preexistentes. Os 4 arquivos PHP desta change passam no Pint e os 8 testes focados passam com 60 assertions.
- [x] 4.2 Executar gates web: `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity` e `pnpm run test:artifacts`
  - Depende de: 3.2, 3.4
  - Resultado: lint/typecheck globais falham em arquivos de produto/testes paralelos; fidelity não encontra a matriz e artifacts não encontra o scanner. Lint focado passa; `generate`, os 224 testes Vitest e os 8 cenários Playwright passam.
- [x] 4.3 Validar Compose/OpenSpec e conferir que o relatório final lista totais, níveis e lacunas sem serviços MEI ou egress habilitado
  - Depende de: 4.1, 4.2
  - Evidência: `docker compose -f docker-compose.yml config --quiet` e `npx @fission-ai/openspec@1.6.0 validate mapear-grafo-testabilidade-e2e --type change --strict`
