## Context

`FiscalSnapshotController::assertCanRead` exige `CurrentOffice::realMembership()` ativo **antes** de consultar `TenantAuthorization`. Em modo `platform_privileged`, o papel efetivo é `ADMIN` e `allows(FiscalMonitoringView)` retorna true para `PLATFORM_ADMIN`, mas `realMembership()` é null quando a conta não tem vínculo dual no Office — gate prematuro → 403.

Controllers irmãos (`ManualConsultController`, `PgdasdMonitoringController`, `CcmeiMonitoringController`) já usam só `authorization->allows(...)`.

## Goals / Non-Goals

**Goals:**

- Leitura de snapshots/findings/pending/evidence autorizada quando `TenantAuthorization` concede `fiscal.monitoring.view`.
- Platform-privileged sem membership dual deixa de ser barrado pelo gate de membership.
- Continuar fail-closed para ator ausente / sem permissão.

**Non-Goals:**

- Mudar resolução de Office / seleção privilegiada.
- Expandir poderes implícitos de `PLATFORM_ADMIN` fora do que `TenantAuthorization` já concede.
- Refatorar todos os controllers fiscais legados que ainda checam só `role()`.

## Decisions

1. **Remover o pré-check de `realMembership`** em `assertCanRead` — a autoridade canônica/legada já cobre membership inativa, office não operacional e platform-privileged.
2. **Manter target opcional** (`?Model $target`) para download de evidência (tenancy do artefato via `belongsToCurrentOffice`).
3. **Mensagem de 403** permanece a mesma (copy estável para a UI).

## Risks / Trade-offs

- **[Menos gate explícito]** Mitigação: `TenantAuthorization` já valida ator, office e membership no caminho membership; no privilegiado exige `isPlatformAdmin()`.
- **[Divergência residual]** Outros endpoints ainda checam só `role()` — fora do escopo; não piorar snapshots.

## Migration Plan

- Deploy API (opcache/Horizon reinício se necessário).
- Rollback: reverter o controller.
