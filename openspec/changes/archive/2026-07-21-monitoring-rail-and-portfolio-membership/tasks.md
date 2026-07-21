## 1. N0 — Rail e CCMEI

- [x] 1.1 Filtrar seções ocultas no catálogo do rail (`client-fiscal-detail-navigation`) e deep-link → overview.
- [x] 1.2 Gate CCMEI por `clientIsMei` no rail + overview; alinhar `client-monitoring-overview`.
- [x] 1.3 Testes unitários/fidelity do catálogo filtrado e gate CCMEI.

## 2. N0 — API de membership (opt-out)

- [x] 2.1 Migration `office_monitoring_module_exclusions` (office_id, client_id, module_key, submodule nullable, unique).
- [x] 2.2 Serviço + rotas tenant-scoped include/exclude/list; rejeitar include fora do regime.
- [x] 2.3 Aplicar exclusões em `ModulePortfolioQueryService::scopedClientIdsQuery` (e scheduler se compartilhado).
- [x] 2.4 Feature tests API (exclude some da lista; include reinstaura; tenancy).

## 3. N1 — UI membership e redirect

- [x] 3.1 Modal “Associar clientes” (buscar, incluir, excluir) nas toolbars das carteiras.
  Depende de: 2.2
- [x] 3.2 Ação “Excluir” no dropdown de linha (PGDASD, PGMEI, DCTFWeb e demais carteiras Module*).
  Depende de: 2.2
- [x] 3.3 Redirect pós-create: SN → PGDASD; MEI → PGMEI; outros → ficha CRM.
- [x] 3.4 Testes web do modal/ações/redirect + fidelity.
  Depende de: 3.1, 3.2, 3.3

## 4. N2 — Gates

- [x] 4.1 Gates API (`pint --test`, `php artisan test` filtros) + web (`pnpm` lint/typecheck/test) + `openspec validate` da change.
  Depende de: 1.3, 2.4, 3.4
