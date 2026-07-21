## 1. N0 — API listagem office-wide

- [x] 1.1 Generalizar `ClientGuidesQueryService::paginate` para `?int $clientId` + `payment_counters` no retorno
- [x] 1.2 `TaxGuideController::index` sempre usa o serviço unificado
- [x] 1.3 Teste office-wide: DAS sem tax_guides → total > 0 + payment_counters

## 2. N1 — Frontend central

- [x] 2.1 `guides.vue`: KPI a partir de payment_counters; mapear KPI→payment_status
  Depende de: 1.2
- [x] 2.2 Ações seguras para IDs virtuais (cliente / documento)
  Depende de: 1.2

## 3. N2 — Gates

- [x] 3.1 Testes + pint + `openspec validate --change fix-monitoring-guides-central-empty --strict`
  Depende de: 1.3, 2.1, 2.2
