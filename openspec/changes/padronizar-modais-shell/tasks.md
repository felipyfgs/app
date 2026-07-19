## N0 — Fundação

- [x] 1.1 Criar `ShellModalFooter`, `ShellFormModal`, `ShellConfirmModal`, `ShellScrollableModal`, `ShellLoadingModalBody`
- [x] 1.2 Teste de kit `tests/unit/shell-modals.test.ts`

## N1 — Forms e confirms

- [x] 2.1 Migrar forms (SaveFilter, TeamAdd, DepartmentAdd, AssignCategories, ClientForm/Credential, CategoryManager, AssociateCategories, ManageSavedFilters, OfficeCredential)
- [x] 2.2 Migrar confirms simples (RecentRefresh, ClientCatalogList, Selection/Bulk, PendingSearch, etc.)
- [x] 2.3 Aplicar `ShellModalFooter` em FiscalMutation e SerproOwner

## N2 — Detail e inline

- [x] 3.1 Migrar detalhes/históricos para `ShellScrollableModal` (+ LoadingBody)
- [x] 3.2 Envolver UModal inline (templates, exports, closing, simples-mei, OfficeProfile, serpro pages)

## N3 — Gate

- [x] 4.1 Gate `tests/unit/shell-modals-migration-gate.test.ts`
- [x] 4.2 OpenSpec change `padronizar-modais-shell` / capability `panel-shell-modals`
