## Why

As listas do painel usam paradigmas inconsistentes de filtro (chips + KPI no monitoramento, formulário com “Aplicar” em documentos, tabs sem chips na fila de trabalho, deep-link apagado em closing/health). Isso impede compartilhar estado via URL, duplica CRUD de presets e degrada a UX operacional. O padrão de Parcelamentos (KPI + busca + chips + presets + Exibir) já existe e deve ser o contrato único.

## What Changes

- Introduzir o contrato **padrão ouro** de filtros de lista: faixa KPI/tabs (quando houver) + `ListFilterToolbar` (busca, chips `DataTableFilter*`, Limpar, Salvar/Filtros salvos, refresh, slot Exibir).
- Tornar **sync de URL obrigatório** em listas operacionais (`q`, filtros estruturados, paginação, sort).
- Documentar **Filter Lite** (busca ± 1 select) para settings/admin e listas só-busca.
- Extrair shell compartilhado a partir de `MonitoringModuleToolbar`; unificar CRUD de presets em `useSavedListPresets`.
- Migrar Documentos, Work queue, Closing/Health e Clients (KPI) para o padrão; alinhar aliases de sort no backend (`sort_direction` / `direction`).
- **Não** alterar schema de `saved_list_filters`; **não** habilitar SERPRO live nem mutações fiscais.

## Capabilities

### New Capabilities

- `list-filters-ux`: contrato de UX e estado de filtros de listas do painel — padrão ouro, Filter Lite, sync de URL, integração com presets (`/api/v1/list-filters`) e faixas KPI como filtro de resumo.

### Modified Capabilities

- (nenhuma — main specs ainda sem capability de filtros de lista.)

## Impact

- **Frontend:** `data-table-filter/*`, novo `ListFilterToolbar` / `useListFilterQuery`, `ModuleToolbar`, `clients/index`, `docs/Filters`, `WorkQueueWorkspace`, `closing`/`health`, KPI strips.
- **Backend:** aliases de sort em listas ad hoc; API de presets inalterada (payload opaco por `surface`).
- **Fora de escopo:** rewrite de Form Requests, TanStack column filters do template, exports/syncs como toolbar de lista, SERPRO live.

### Dependências entre changes

- **Nível:** C0
- **Bases estáveis:** main specs / archive (incl. `schema-conventions`); núcleo `DataTableFilter*` e API `list-filters` já em produção.
- **Depende de:** nenhuma
- **Capability/contrato:** `list-filters-ux` (nova)
- **Marco exigido:** n/a
- **Relação:** n/a
- **Desbloqueia:** mudanças futuras de UI de lista que consumam o shell padronizado
- **Paralelismo:** pode avançar em paralelo com changes SERPRO/monitoramento que não toquem toolbars de lista
