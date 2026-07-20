# panel-shell-modals Specification

## Purpose
TBD - created by archiving change padronizar-modais-shell. Update Purpose after archive.
## Requirements
### Requirement: Cascas Shell de modal existem e são auto-importadas

O sistema SHALL fornecer em `apps/web/app/components/shell/` os componentes `ModalFooter`, `FormModal`, `ConfirmModal`, `ScrollableModal` e `LoadingModalBody`, consumíveis como `ShellModalFooter`, `ShellFormModal`, `ShellConfirmModal`, `ShellScrollableModal` e `ShellLoadingModalBody` via auto-import Nuxt. Páginas e domínio MUST NOT inventar faixa de footer Cancel/Submit com `flex justify-end gap-2` ad hoc nas superfícies migradas; MUST usar `ShellModalFooter` (direto ou via casca).

#### Scenario: Auto-import FormModal

- **WHEN** um template referencia `<ShellFormModal>`
- **THEN** o componente resolve sem import manual a partir de `components/shell/FormModal.vue`

#### Scenario: Footer canônico

- **WHEN** `ShellModalFooter` é renderizado com props default
- **THEN** exibe Cancelar (ghost/neutral) e botão de submit primary alinhados à direita com gap-2

### Requirement: ShellFormModal padroniza modal de formulário

O sistema SHALL expor `ShellFormModal` com `v-model:open`, `title`, `description` opcional, `contentClass`, slots `#default` (trigger), `#body`, `#footer`, e footer default Cancel/Submit com `loading` / `disabled` / emits `cancel` + `submit`.

#### Scenario: Submit com loading

- **WHEN** `loading` é true
- **THEN** o botão de submit mostra estado de loading e o cancel fica desabilitado

### Requirement: ShellConfirmModal para confirmação simples

O sistema SHALL expor `ShellConfirmModal` com `tone` `neutral` | `danger` (danger → cor `error` no confirm), labels de cancel/confirm, `loading`, emit `confirm`, e slot `#body` opcional para alerta/contexto. Confirmações reforçadas com TOTP/frase MUST NOT ser convertidas nesta casca genérica; MUST permanecer no domínio usando `ShellModalFooter`.

#### Scenario: Tom danger

- **WHEN** `tone="danger"`
- **THEN** o botão de confirmação usa cor `error`

#### Scenario: Domínio reforçado

- **WHEN** o modal é `FiscalMutationConfirmModal` ou `SerproOwnerConfirmModal`
- **THEN** o body legal/TOTP/frase permanece no componente de domínio
- **AND** o footer usa `ShellModalFooter`

### Requirement: ShellScrollableModal para detalhe e histórico

O sistema SHALL expor `ShellScrollableModal` com limite de altura (`max-h`) e body com `overflow-y-auto`. Estados de carregamento densos MUST preferir `ShellLoadingModalBody` (skeletons) em vez de texto solto «Carregando…» quando a superfície for migrada.

#### Scenario: Body scrollável

- **WHEN** o conteúdo do body excede a altura útil
- **THEN** o body do modal permite scroll vertical sem perder o footer sticky do UModal

### Requirement: Gate de contrato impede regressão

O sistema SHALL manter testes automatizados que verificam: (1) existência das cascas shell de modal; (2) superfícies form/confirm/detail migradas listadas no gate NÃO montam `<UModal` como raiz; (3) slots `#footer` Cancel/Submit usam `ShellModalFooter` (exceto footers complexos documentados e domínio reforçado).

#### Scenario: Gate form/confirm

- **WHEN** o teste `shell-modals-migration-gate` executa
- **THEN** cada arquivo da lista FORM_OR_CONFIRM contém `ShellFormModal` ou `ShellConfirmModal` e não contém `<UModal`

