## Why

O painel autentica-se com Nuxt UI + arquétipos do template, mas a anatomia de página e lista ainda é reinventada em dezenas de `pages/` e componentes de domínio (`UTable`, navbar, footer, empty, skeletons). Isso gera UI inconsistente (paginação, densidade, empty/error) e impede padronização. Agora, com `components/shell/` já parcialmente estabelecido (`ShellTableFooter`, `ShellListFilterToolbar`, etc.) e o contrato de lista (per-page 10/20/50), falta fechar o kit reutilizável para as páginas pararem de montar tabela/chrome na mão.

## What Changes

- Introduzir o contrato do **kit `Shell*`** em `apps/web/app/components/shell/`: páginas e domínio consomem; não montam `UTable` / navbar / footer / empty de lista do zero.
- Criar componentes de **chrome de página**: `ShellPagePanel`, `ShellPageNavbar`, `ShellNavbarRefresh`, `ShellNavbarBack`, `ShellSettingsShell` (casca settings).
- Criar componentes de **lista admin**: `ShellDataTable` (UTable + presets `table-ui` + empty + `ShellTableFooter`), `ShellListEmpty`, `ShellListSkeleton`, `ShellBulkActionBar`, `ShellFilterToolbarLite`, helpers `ShellSortableHeader` / `ShellRowActions`.
- Criar feedback mínimo de lista: `ShellLoadError` (e adoção de empty tipado).
- Refatorar `ModuleDataTable` para **compor** `ShellDataTable` (carteiras fiscais sem regressão visual).
- Migrar listas piloto e depois o lote principal (exports, closing, imports, work, admin/offices, syncs, health, `ClientCatalogList`) para o kit.
- Gate/teste de contrato: pages migradas não contêm `<UTable` nem paginação inventada.
- Manter prefixo **`Shell*`** (pasta `shell/` + auto-import Nuxt); não usar `U*` nem `Base*`.
- **Não** inclui nesta change: FormSection/modals genéricos, SplitWorkspace/mestre–detalhe, EntityIdentityHeader — ficam changes follow-up.

## Capabilities

### New Capabilities

- `panel-shell-kit`: contrato e componentes reutilizáveis `Shell*` para chrome de página autenticada e lista admin (tabela, toolbar, footer, empty/error, bulk bar); regra de consumo por pages/domínio; adoção e gate de não-regressão.

### Modified Capabilities

- _(nenhuma — `openspec/specs/` sem capabilities permanentes relevantes a este contrato)_

## Impact

- **Código:** `apps/web/app/components/shell/*` (novos + adoção dos existentes); `apps/web/app/components/monitoring/ModuleDataTable.vue`; listas em `pages/` (exports, closing, docs/imports, work/*, admin/offices, syncs, health) e `components/clients/ClientCatalogList.vue`; utils `table-ui.ts`; testes em `apps/web/tests/unit/`.
- **API / backend:** nenhuma.
- **Dependências:** Nuxt 4 auto-import por pasta; Nuxt UI (`UTable`, `UPagination`, `UDashboardPanel`, etc.); template `@ 0f30c09` via skill ui-archetype.
- **Non-goals:** SERPRO live, mutações fiscais, outbound, parecer jurídico; unificação dos três sistemas de KPI (`shell` / `monitoring` / `home`); infinite scroll genérico; rename em massa para `Panel*`; kit completo de settings/modal/split (changes futuras).

### Dependências entre changes

- **Nível:** `C0`
- **Bases estáveis:** template de referência `@ 0f30c09`; componentes shell já existentes (`TableFooter`, `ListFilterToolbar`, …); presets `table-ui.ts`
- **Depende de:** nenhuma
- **Capability/contrato:** `panel-shell-kit` (nova)
- **Marco exigido:** n/a
- **Relação:** n/a
- **Desbloqueia:** changes futuras de settings-shell, modal-shell e split-workspace (consumo do mesmo prefixo/pasta)
- **Paralelismo:** pode avançar em paralelo com changes de domínio fiscal que não toquem anatomia de lista nas mesmas pages
