## Why

O onboarding do escritório em Produção falha ao assinar o Termo: `OfficeSerproOnboardingService` chama `SignTermoWithManagedA1Job::handle()` sem injetar `OfficeCredentialResolver`, passando `AuditLogger` na 4ª posição (TypeError). Em seguida, runs fiscais quebram ao persistir `skip_reason` > 80 chars.

## What Changes

- Refatorar a invocação síncrona do job para DI completa (`dispatchSync` / `app()->call`).
- Truncar `skip_reason` (e alinhar com `error_message`) ao persistir runs.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `office-serpro-auto-onboarding`: assinatura síncrona do Termo com A1 gerenciado usa DI correta do job.

## Impact

- `OfficeSerproOnboardingService`, `FiscalSnapshotPersistence`
- Testes unitários do job / onboarding se existirem

### Non-goals

- Não mudar o fluxo assíncrono `SignTermoWithManagedA1Job::dispatch`.
- Não ampliar coluna `skip_reason` neste PR (só truncate).

### Dependências entre changes

- Nível: `C0`
- Depende de: nenhuma
- Desbloqueia: onboarding A1 do office em PRODUCTION no stack local
