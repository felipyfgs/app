## Context

O kit `Shell*` de listas (`panel-shell-kit`) já existe em `apps/web/app/components/shell/`. Modais ainda usavam `UModal` direto com footers inconsistentes. Referência ouro de form: `SaveFilterModal` (body + `#footer` Cancel ghost + Submit primary com loading/disabled).

## Goals / Non-Goals

**Goals**

- Cascas reutilizáveis Form / Confirm / Scrollable + Footer + LoadingBody.
- Migrar `*Modal.vue` e usos inline cobertos pelo inventário do plano.
- Gate Vitest impede regressão de `<UModal` raiz e footer ad hoc Cancel/Submit.
- Confirms reforçados (TOTP/frase) permanecem no domínio com `ShellModalFooter`.

**Non-Goals**

- Slideover / mailbox / work-queue (`ShellDetailPanel`).
- Mudança de copy legal ou fluxo TOTP fiscal.
- Redesign visual além da anatomia de slots Nuxt UI.

## Decisions

1. **Prefixo `Shell*`** (não `U*`): alinhado ao kit existente e auto-import por pasta.
2. **ShellFormModal** encapsula `UModal` + slots `#body` / `#footer`; footer default = `ShellModalFooter`.
3. **ShellConfirmModal** para cancel/confirm simples; `tone=danger` → botão `error`.
4. **ShellScrollableModal** para detalhe/histórico com `max-h` + body `overflow-y-auto`; footers complexos (ClientDetail, Docs Detail) podem permanecer customizados.
5. **Domínio reforçado** (`FiscalMutationConfirmModal`, `SerproOwnerConfirmModal`): mantém `UModal` + body; só o footer vira `ShellModalFooter`.
6. **Ondas W0–W5**: fundação → forms → confirms → detail → inline pages → gate/OpenSpec.

## Risks / Trade-offs

- [Scroll interno vs body shell] → Mitigação: preservar `content-class` legado; aceitar leve diferença de scroll em modais densos.
- [Form com validação UForm] → Mitigação: `requestSubmit()` via id do form a partir do footer.
- [Confirms com body informativo] → Mitigação: slot `#body` no `ShellConfirmModal`.

## Migration Plan

1. Criar cascas + teste de kit.
2. Migrar por ondas sem alterar emits/API.
3. Gate Vitest + change OpenSpec.
4. Rollback: reverter arquivos Vue; cascas são aditivas.

## Open Questions

- Nenhuma bloqueante; Slideover fica em change follow-up.
