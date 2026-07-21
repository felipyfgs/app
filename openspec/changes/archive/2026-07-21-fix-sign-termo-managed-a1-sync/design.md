## Context

`SignTermoWithManagedA1Job::handle` exige 5 deps; a chamada síncrona em onboarding passa só 4 e na ordem errada.

## Goals / Non-Goals

**Goals:** Assinatura síncrona do Termo sem TypeError; persistência de runs BLOCKED sem estourar varchar(80).

**Non-Goals:** Migrar schema de `skip_reason`; redesign do onboarding.

## Decisions

1. Usar `SignTermoWithManagedA1Job::dispatchSync(...)` — Laravel resolve `handle()` via container.
2. Truncar `skip_reason` com `mb_substr(..., 0, 80)` em `FiscalSnapshotPersistence::finalizeRun`.

## Risks / Trade-offs

- [Mensagem truncada no skip_reason] → Mitigação: `error_message` já guarda até 500 chars.
