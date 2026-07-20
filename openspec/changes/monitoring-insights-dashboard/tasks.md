## 1. N0 — Contrato API de insights

- [x] 1.1 Criar DTO/array público e `MonitoringInsightsQueryService` agregando pending, findings, RBT12, mailbox buckets, notifications, declarations absence, sitfis counters e obligations progress (excluir origem sintética; partial_errors)
- [x] 1.2 Criar `MonitoringInsightsController` + rota `GET /api/v1/fiscal/monitoring/insights` no grupo fiscal autenticado
- [x] 1.3 Feature test cobrindo shape 200, isolamento de tenant e falha parcial honesta
  - Evidência: `php artisan test --filter=MonitoringInsights`

## 2. N1 — Client Web tipos e API

- [x] 2.1 Adicionar tipos TS do payload de insights
  - Depende de: 1.1
- [x] 2.2 Expor `api.fiscal.monitoring.insights()` em `createFiscalApi.ts`
  - Depende de: 1.2, 2.1

## 3. N1 — Componentes de insights

- [x] 3.1 Criar cards `PendingCard`, `Rbt12ChartCard` (Unovis), `MailboxBucketsCard`
  - Depende de: 2.1
- [x] 3.2 Criar `NotificationsFeed`, `DeclarationsAbsenceCard`, `SitfisDonutCard`, `ObligationsProgressCard`
  - Depende de: 2.1

## 4. N2 — Página `/monitoring`

- [x] 4.1 Redesenhar `pages/monitoring/index.vue` (grid 8/4, KPIs, fail-closed, Manual Consult abaixo)
  - Depende de: 2.2, 3.1, 3.2
- [x] 4.2 Teste unit/fidelity do layout e copy honesta (RBT12, sem Excluído/SPED/sublimite falsos)
  - Depende de: 4.1
  - Evidência: `pnpm run test` filtrado na área

## 5. N3 — Gates integrados

- [x] 5.1 Gates API: pint --test + Feature MonitoringInsights
  - Depende de: 1.3
- [x] 5.2 Gates Web: lint + typecheck na área tocada
  - Depende de: 4.2
- [x] 5.3 `openspec validate --specs --strict` e validate da change ativa
  - Depende de: 4.2
