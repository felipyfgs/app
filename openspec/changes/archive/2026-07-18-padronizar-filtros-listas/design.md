## Contexto

O painel já possui o núcleo `DataTableFilter*` (chips), presets via `/api/v1/list-filters` e o padrão visual de Parcelamentos (`MonitoringKpiStrip` + `MonitoringModuleToolbar`). Documentos, Work queue, Closing/Health e parte de Clients divergem (form “Aplicar”, ausência de chips, URL apagada, KPI em cards). Esta change é **transversal de UX de lista** (justificativa da exceção ao limite de 1 capability): um único contrato `list-filters-ux` governa shell, URL e migração das superfícies, evitando contracts fragmentados.

## Objetivos / Não objetivos

**Objetivos:**

- Extrair `ListFilterToolbar` reutilizável e `useListFilterQuery` para sync de URL.
- Unificar CRUD de presets em `useSavedListPresets`.
- Unificar KPI de resumo como filtro (strip de tabs/contadores).
- Migrar Docs, Work queue, Closing/Health e Clients para o padrão ouro (ou Filter Lite onde couber).
- Aceitar alias `direction` ↔ `sort_direction` no backend de listas ad hoc.

**Não objetivos:**

- Alterar schema/API de `saved_list_filters` além do uso de surfaces existentes.
- Introduzir Form Requests genéricos ou Spatie QueryBuilder.
- Migrar settings/admin para chips (permanecem Filter Lite).
- SERPRO live, mutações fiscais, outbound.

## Decisões

### D1 — Shell único a partir do ModuleToolbar

`ListFilterToolbar` concentra busca, `DataTableFilterRoot`, Limpar, Salvar/menus, refresh e slots (Exibir, export). `MonitoringModuleToolbar` torna-se adapter fino (mapeamento `MonitoringFilterValue` ↔ models) ou é substituído pelo shell direto.

### D2 — URL canônica

Query keys estáveis: `q`, campos de filtro (mesmo nome da API), `page`, `per_page`, `sort`, `sort_direction`. Valores multi usam CSV. Aplicar preset atualiza URL. Reload/share restaura estado.

### D3 — KPI = filtro de um eixo

A faixa de resumo altera o mesmo eixo que o chip correspondente (ex. `situation` / triage). Contadores agregam com os demais filtros ativos, excluindo só o eixo do KPI.

### D4 — Filter Lite

Settings team/departments, admin offices e templates só-busca: busca ± um select, sem chips/presets obrigatórios.

### D5 — Backend mínimo

Aceitar `direction` como alias de `sort_direction` (e vice-versa) nas listas que divergem; sem DTO universal nesta change.

## Riscos / Trade-offs

- **URL longa** com muitos chips: aceitável; não comprimir.
- **Migração Docs:** remove “Aplicar” (filtros passam a ser live como monitoring) — alinhado ao padrão ouro.
- **Work queue:** filtros já na API/query; expor chips pode revelar UX incompleta — completo nesta change.

## Migração

1. Fundação (shell + URL + presets unificados + KPI clients).
2. Docs → Work → Closing/Health.
3. Aliases backend + varredura Filter Lite.
4. Gates frontend/backend.

## Open Questions

- (nenhuma — decisões do plano confirmadas: padrão Parcelamentos + URL obrigatória em listas operacionais.)
