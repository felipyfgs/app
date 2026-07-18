## N0 — Fundação

- [x] 1. Criar `useListFilterQuery` (serialize/deserialize `q`, filtros, page, per_page, sort, sort_direction) + testes unitários
- [x] 2. Extrair `ListFilterToolbar` a partir de `MonitoringModuleToolbar` (busca, chips, Limpar, presets via `useSavedListPresets`, refresh, slots)
- [x] 3. Refatorar `MonitoringModuleToolbar` / portfolio para consumir o shell + sync URL
- [x] 4. Unificar KPI: clients `index` usa strip de tabs/contadores (não grid `UPageCard` como filtro) + URL sync

## N1 — Lacunas UX

- [x] 5. Migrar `DocsFilters` / workspace para chips + `ListFilterToolbar`; remover fluxo “Aplicar” legado; alinhar URL ao composable
- [x] 6. Work queue: chips para department/assignee/client/scope; alinhar processes/calendar ao vocabulário de query
- [x] 7. Closing e Health: preservar query na URL; alinhar toolbar ao shell (Health pode ser Filter Lite documentado: prioridade + tipo)

## N2 — Backend e varredura

- [x] 8. Aceitar alias `direction` ↔ `sort_direction` nas listas ad hoc relevantes + teste
- [x] 9. Varredura: settings/admin permanecem Filter Lite; exports/syncs sem toolbar de lista indevida

## N3 — Gates

- [x] 10. Rodar `pnpm run test:gate` no frontend e testes backend tocados; corrigir regressões
