## 1. Catálogo e overview

- [x] 1.1 Atualizar `client-fiscal-detail-navigation.ts`: ordem/labels canônicos, keys `dctfweb`/`mailbox`, hidden (`pending`, `runs`, `findings`, `renunciations`), MEI=`ccmei` regime-gated; derivar labels de `MONITORING_NAV_ITEMS` (N0)
- [x] 1.2 Helper de path ao trocar empresa (preserva seção visível / fallback overview) (N0)
- [x] 1.3 Sincronizar `client-monitoring-overview.ts` com labels/ordem/process keys canônicos (N1 ← 1.1)

## 2. UI rail e panels

- [x] 2.1 Header do `ClientFiscalAside`: combobox `FiscalClientPicker` + modo collapsed; `data-testid=monitoring-client-switcher` (N1 ← 1.2)
- [x] 2.2 Wire navegação do switcher em `[clientId].vue` (N1 ← 1.2, 2.1)
- [x] 2.3 Panels `dctfweb` e `mailbox` em `[clientId].vue`; redirect de seções ocultas incl. `pending` (N1 ← 1.1)

## 3. Testes e gates

- [x] 3.1 Atualizar/criar testes unitários: contrato rail ↔ `MONITORING_NAV_ITEMS`, hidden keys, MEI, switcher path helper, overview (N2 ← 1.1, 1.2, 1.3)
- [x] 3.2 Gate web: lint/typecheck/test da área tocada + `openspec validate` da change (N3 ← 2.*, 3.1)
