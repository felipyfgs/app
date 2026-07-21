## 1. N0 — Contrato FE (tipos e filtros)

- [x] 1.1 Estender `MonitoringFilterValue` / `FiscalModulePortfolioFilters` com `sendStatus`; helpers em `monitoring-filters.ts` (normalize, option keys, assinatura, itens Enviado/Não enviado)
- [x] 1.2 URL (`MONITORING_LIST_QUERY_SCHEMA`) + presets (`saved-list-filters`) para `sendStatus`

## 2. N1 — API send_status (PGDASD)

- [x] 2.1 Parse `send_status` em `ModulePortfolioFilters::fromRequest`
- [x] 2.2 Aplicar filtro em `ModulePortfolioQueryService` só para `simples_mei` + PGDASD (agregação alinhada a `PgdasdCommunicationService`)
  - Depende de: 2.1
- [x] 2.3 Feature test: lista PGDASD filtra sent / not_sent; MEI ignora ou não expõe
  - Depende de: 2.2

## 3. N1 — UI Simples Nacional

- [x] 3.1 `Portfolio.vue` ramo PGDASD: `filterConfig` com Situação · Cliente · Competência · Envio; ramo PGMEI intacto
  - Depende de: 1.1
- [x] 3.2 `useFiscalModulePortfolio`: mapear `sendStatus` → `send_status` em clients/overview
  - Depende de: 1.1, 2.1

## 4. N2 — Gates

- [x] 4.1 Testes FE de filterConfig PGDASD / MEI inalterado; openspec validate da change
  - Depende de: 3.1, 2.3
- [x] 4.2 Gates: API (pint + filter test) e web (lint/typecheck/test relevantes)
  - Depende de: 4.1
