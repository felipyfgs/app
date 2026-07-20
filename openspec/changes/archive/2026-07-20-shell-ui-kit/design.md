## Context

O painel (`apps/web`) copia arquétipos do Nuxt UI Dashboard (`customers.vue`, settings, inbox) via skill `ui-archetype`, mas a anatomia ainda é markup repetido: `UDashboardPanel` + `UTable` + footer/paginação ad hoc. Já existem peças em `components/shell/` (`TableFooter`, `ListFilterToolbar`, `KpiStrip`, `SectionHeader`/`SectionCard`, …) e presets em `utils/table-ui.ts` (`LIST_TABLE_*`, `LIST_TABLE_PER_PAGE_ITEMS` 10/20/50). O único wrapper completo de grade é `ModuleDataTable` (só monitoramento).

Esta change fecha o **kit mínimo implantável**: chrome de página + lista admin. O catálogo maior (settings forms, modals, split inbox) fica como roadmap, não como escopo desta unidade.

**Justificativa de escopo (1 capability):** OpenSpec limita a 1–2 capabilities e ~sessão focada. Chrome + lista é o maior ROI e desbloqueia migração das ~14 pages com `UTable`. Settings/modal/split são changes C0/C1 posteriores sem conflito de contrato.

## Goals / Non-Goals

**Goals:**

- Contrato claro: página/domínio não montam anatomia de lista/chrome; consomem `Shell*`.
- Prefixo `Shell*` via pasta `components/shell/` (auto-import Nuxt); coexistência com arquétipo “Shell” = layout autenticado.
- `ShellDataTable` + footer/empty/per-page canônicos; `ModuleDataTable` compõe o DataTable.
- Chrome: `ShellPagePanel` / `ShellPageNavbar` / refresh / back / settings shell.
- Migração das listas principais + gate de regressão.

**Non-Goals:**

- Kit completo de FormModal / SplitWorkspace / FormSection (follow-ups).
- Unificar `ShellKpiStrip` vs `monitoring/KpiStrip` vs `home/*`.
- Infinite scroll genérico; cursor-docs (`Catalog`) além do necessário.
- Alterar APIs Laravel ou contratos fiscais.
- Rename para `Panel*` / `Ui*`.

## Decisions

### D1 — Prefixo `Shell*` (manter)

- **Escolha:** pasta `shell/` → auto-import `ShellDataTable`.
- **Alternativas:** `panel/` (`Panel*`) — mais claro semanticamente vs arquétipo layout; `ui/` — confunde com Nuxt UI `U*`.
- **Rationale:** 9 componentes já usam `Shell*`; custo de rename > benefício nesta change.

### D2 — `ShellDataTable` como miolo; footer já existente

- **Escolha:** `ShellDataTable` encapsula `UTable` + preset (`dashboard` | `monitoring-compact`) + `#empty` + `ShellTableFooter`.
- **Não** embute fetch, colunas de negócio nem toolbar (toolbar irmão no `#body`, como customers/clientes).
- **Alternativa:** um único `ShellListPage` monólito — rejeitada (menos flexível para ModuleTable / split futuro).

### D3 — ModuleDataTable compõe, não some

- Mantém mobile cards, column visibility, selection scope fiscal.
- Desktop grade → `ShellDataTable` (ou equivalente interno compartilhado).
- Evita duplicar footer/per-page.

### D4 — Defaults de paginação

- Default `itemsPerPage` / `perPage` = **20**; opções 10/20/50 (`LIST_TABLE_PER_PAGE_ITEMS`).
- Alinhar `ModuleTable` default 15 → 20.

### D5 — Escopo de migração nesta change

1. Fundação + chrome + DataTable/empty/load-error.
2. ModuleDataTable.
3. Lote: exports, closing, imports, work processes/templates, admin/offices, syncs, health, ClientCatalogList.
4. Gate unitário de contrato.

Fora: detalhe `monitoring/clients/[clientId]` (muitas mini-tabelas), reporting usage, docs Catalog cursor.

### D6 — `DashboardInfiniteTableLoader`

- Órfão na raiz. Remover ou não promover nesta change (só se algum piloto cursor precisar — então `ShellCursorLoadMore` em follow-up).

## Risks / Trade-offs

- **[Risk] Refactor ModuleDataTable quebra carteiras** → Mitigation: migrar composição primeiro; smoke/testes de layout existentes; manter props/emits públicos.
- **[Risk] Migração em massa diverge visualmente** → Mitigation: presets `table-ui` únicos; comparar com clientes como referência ouro.
- **[Risk] Pages mistas (metade migrada)** → Mitigation: gate só nas pages listadas; documentar regra no comentário de `table-ui.ts`.
- **[Trade-off] Kit incompleto (sem modal/split)** → Aceito: desbloqueia lista agora; follow-ups reutilizam o mesmo prefixo.
- **[Trade-off] Colisão semântica “Shell” arquétipo vs pasta** → Aceito; documentado no proposal/design.

## Migration Plan

1. Criar componentes shell (chrome + lista + LoadError) sem migrar pages.
2. Refatorar ModuleDataTable → composição; validar carteira piloto.
3. Migrar ClientCatalogList (referência) + 1 page admin (exports).
4. Migrar lote restante da lista do Impact.
5. Gate vitest + `pnpm run test:gate` focado nos testes tocados.
6. Rollback: reverter commits da change; pages antigas não dependem de API nova.

## Mapa de dependências

```text
C0 shell-ui-kit (panel-shell-kit)
  └─ desbloqueia (coordenada, pós-archive):
       shell-ui-settings (FormSection…)
       shell-ui-modals (FormModal…)
       shell-ui-split (SplitWorkspace…)
```

- Sem upstream ativo.
- Ownership: somente `apps/web` shell + pages de lista listadas; não editar contracts de outras changes ativas (MEI/orquestrador).

## Open Questions

- Nenhum bloqueante. Follow-ups de settings/modal/split serão changes separadas após archive desta.
