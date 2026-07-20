## 1. N0 — Fundação do kit lista

- [x] 1.1 Criar `ShellDataTable` em `apps/web/app/components/shell/DataTable.vue` (UTable + presets `table-ui` + empty slot + `ShellTableFooter`; emits `update:page` / `update:itemsPerPage`)
- [x] 1.2 Criar `ShellListEmpty` e `ShellLoadError` (estados empty/filtered/error + retry)
- [x] 1.3 Criar helpers `ShellBulkActionBar`, `ShellFilterToolbarLite`, `ShellSortableHeader`, `ShellRowActions`
- [x] 1.4 Documentar em comentário de `table-ui.ts` a regra «lista = shell; page não monta UTable»; alinhar default `ModuleTable` perPage 15 → 20
- [x] 1.5 Atualizar `tests/unit/list-table-layout.test.ts` para o contrato `ShellDataTable` + per-page 10/20/50

## 2. N1 — Chrome de página

- [x] 2.1 Criar `ShellPagePanel` e `ShellPageNavbar` (UDashboardPanel + navbar canônica com slots)
- [x] 2.2 Criar `ShellNavbarRefresh` e `ShellNavbarBack`
- [x] 2.3 Criar `ShellSettingsShell` (body settings + `DashboardContent` + SectionNavigation opcional)
- [x] 2.4 Evidência: smoke visual ou assert de presença dos componentes shell no auto-import / arquivo

## 3. N2 — Composição fiscal + piloto

- [x] 3.1 Refatorar `ModuleDataTable` para compor `ShellDataTable` (manter mobile cards, selection, column visibility, emits públicos)
- [x] 3.2 Migrar `ClientCatalogList` para `ShellDataTable` + chrome shell onde couber
- [x] 3.3 Migrar `pages/exports.vue` como piloto de page lista
- [x] 3.4 Testes unitários tocados (list-table-layout, clients-table) verdes

## 4. N3 — Migração do lote de listas

- [x] 4.1 Migrar `closing.vue`, `docs/imports/index.vue`, `docs/imports/[id].vue`
- [x] 4.2 Migrar `work/processes/index.vue`, `work/templates/index.vue`
- [x] 4.3 Migrar `admin/offices/index.vue`, `syncs.vue`, `health.vue`
- [x] 4.4 Gate: asserts de que as superfícies migradas não contêm `<UTable` na lista principal

## 5. N4 — Gates integrados

- [x] 5.1 Rodar `pnpm exec vitest run` nos testes de contrato/lista tocados em `apps/web`
- [x] 5.2 Checklist de aceite: carteira fiscal (ModuleTable) + clientes + exports com footer/per-page/paginação alinhados; sem regressão óbvia de layout
- [x] 5.3 Remover ou deixar documentado o órfão `DashboardInfiniteTableLoader` (sem promover infinite nesta change)
