## ADDED Requirements

### Requirement: Leitura de snapshots usa TenantAuthorization sem exigir membership dual

O `FiscalSnapshotController` SHALL autorizar leituras de snapshots, findings, pending items e download de evidência exclusivamente via `TenantAuthorization::allows` com `TenantPermission::FiscalMonitoringView` (e target de tenancy quando aplicável). A leitura MUST NOT ser negada apenas porque `CurrentOffice::realMembership()` é null em contexto `platform_privileged` quando o ator é `PLATFORM_ADMIN` autorizado.

#### Scenario: Platform admin privilegiado sem membership dual lista snapshots

- **WHEN** um `PLATFORM_ADMIN` autenticado opera em `OfficeAccessMode::PlatformPrivileged` sobre um Office sem `OfficeMembership` dual ativa
- **AND** `TenantAuthorization` concede `fiscal.monitoring.view`
- **AND** o cliente solicita `GET /api/v1/fiscal/snapshots` no escopo desse Office
- **THEN** a API MUST responder 2xx com a página de snapshots (ou lista vazia autorizada)
- **AND** MUST NOT responder 403 com «Sem permissão para monitoramento fiscal» só por ausência de membership dual

#### Scenario: Ator sem permissão continua barrado

- **WHEN** o ator autenticado não possui `fiscal.monitoring.view` segundo `TenantAuthorization`
- **THEN** `GET /api/v1/fiscal/snapshots` MUST responder 403
