## Why

O painel tem dezenas de modais (`*Modal.vue` e `UModal` inline) com anatomia divergente: botões no `#body` vs `#footer`, faixas `flex justify-end` ad hoc e detalhes densos com scroll improvisado. Após o kit de listas (`shell-ui-kit`), falta fechar as cascas `Shell*` de modal para forms, confirms e detalhes usarem o mesmo contrato Nuxt UI.

## What Changes

- Introduzir cascas em `apps/web/app/components/shell/`: `ShellModalFooter`, `ShellFormModal`, `ShellConfirmModal`, `ShellScrollableModal`, `ShellLoadingModalBody`.
- Migrar forms canônicos (SaveFilter, Team/Department, AssignCategories, ClientForm/Credential, etc.) para `ShellFormModal` com footer Cancel/Submit.
- Migrar confirms simples para `ShellConfirmModal`; confirms reforçados (FiscalMutation, SerproOwner) mantêm body de domínio e passam a usar só `ShellModalFooter`.
- Migrar detalhes/históricos (ClientDetail, Docs Detail, Regime/Defis/History, CommunicationModals, etc.) para `ShellScrollableModal` (+ `ShellLoadingModalBody` quando há fetch).
- Envolver `UModal` inline em pages (templates, exports, closing, simples-mei, OfficeProfile, serpro leves) sem mudar regra de negócio.
- Gate/teste de contrato: superfícies migradas não montam `<UModal` raiz; footers Cancel/Submit usam `ShellModalFooter`.

## Capabilities

### New Capabilities

- `panel-shell-modals`: contrato das cascas `Shell*` de modal (form, confirm, scrollable, footer, loading body); regra de consumo; confirmações reforçadas de domínio; gate de não-regressão.

### Modified Capabilities

- _(nenhuma)_

## Impact

- **Código:** `apps/web/app/components/shell/{ModalFooter,FormModal,ConfirmModal,ScrollableModal,LoadingModalBody}.vue`; `*Modal.vue` e pages com `UModal` inline listadas no design; testes `shell-modals*.test.ts`.
- **API / backend:** nenhuma.
- **Dependências:** Nuxt UI `UModal` / `UButton` / `USkeleton`; auto-import `Shell*`.
- **Non-goals:** unificar Slideover/inbox (`ShellDetailPanel`); alterar copy legal / TOTP de mutação fiscal; redesign visual além da anatomia de slots.

### Dependências entre changes

- **Nível:** `C1`
- **Bases estáveis:** template `@ 0f30c09`; Nuxt UI modals
- **Depende de:** `shell-ui-kit` (prefixo/pasta `Shell*`, convenção de kit)
- **Capability/contrato:** `panel-shell-kit` (consumo do mesmo namespace; esta change adiciona `panel-shell-modals`)
- **Marco exigido:** `apply` (kit de listas/shell já aplicado)
- **Relação:** `coordenada` (não bloqueia listas; compartilha pasta `components/shell/`)
- **Desbloqueia:** follow-up `shell-ui-split` / Slideover
- **Paralelismo:** pode avançar em paralelo com changes de domínio que não toquem os mesmos `*Modal.vue`
