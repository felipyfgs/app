## Why

`GET /api/v1/fiscal/snapshots` devolve 403 «Sem permissão para monitoramento fiscal» para `PLATFORM_ADMIN` em modo privilegiado sem membership dual no Office selecionado — mesmo quando `TenantAuthorization` já autoriza `fiscal.monitoring.view`. O detalhe do cliente no painel fica inutilizável.

## What Changes

- Alinhar `FiscalSnapshotController::assertCanRead` ao padrão dos demais controllers fiscais: decidir só via `TenantAuthorization::allows(FiscalMonitoringView)` (e target opcional), sem exigir `realMembership()` prévio.
- Cobrir com teste de feature o cenário platform-privileged sem membership dual.

## Capabilities

### New Capabilities

- `fiscal-monitoring-snapshot-read-auth`: autorização de leitura de snapshots/findings/pending/evidence do núcleo de monitoramento fiscal, inclusive em contexto platform-privileged.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- API: `FiscalSnapshotController` (`assertCanRead`).
- Teste Feature: feature cobrindo index de snapshots sob `OfficeAccessMode::PlatformPrivileged` sem `realMembership`.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma
- Capability/contrato: `fiscal-monitoring-snapshot-read-auth` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente das changes SERPRO/MEI ativas

### Non-goals

- Não alterar matriz de permissões (`TenantPermission` / perfis).
- Não ligar feature flags fiscais / SERPRO / MEI / SEFAZ.
- Não mudar tenancy HTTP (`office_id` do client).
- Serviços `mei`/`mei-worker` no Compose.
- Targets Make de backup/restore/ops indisponíveis.
