# Inventário operacional — RBAC multi-tenant (baseline W0)

**Change:** `padronizar-autorizacao-multitenant`
**Data de congelamento:** 2026-07-17
**Escopo:** inventário executável de autorização legada **sem** alterar decisões de runtime.

Referências:

- OpenSpec: `openspec/changes/padronizar-autorizacao-multitenant/`
- Catálogo canônico: `App\Enums\TenantPermission`
- Flag (default OFF): `features.canonical_multitenant_rbac` / `FEATURE_CANONICAL_MULTITENANT_RBAC`
- Allowlist transitória: `backend/config/multitenant_rbac.php`
- Guardrails: `tests/Architecture/MultitenantRbacGuardrailsTest.php`
- Caracterização backend: `tests/Unit/Enums/OfficeRoleCapabilitiesCharacterizationTest.php`
- Caracterização frontend: `frontend/tests/unit/permissions.test.ts`, `work-permissions.test.ts`

## 1. Papéis e autoridades atuais

| Conceito | Storage atual | Valor | Observação |
|---|---|---|---|
| Plataforma | `platform_memberships.role` | `PLATFORM_ADMIN` | Singleton via `PlatformOwnerService` + índice parcial |
| Tenant | `office_user.role` | `ADMIN` \| `OPERATOR` \| `VIEWER` | Enum `OfficeRole` |
| Contexto | `CurrentOffice` | membership / platform_privileged | Privilegiado exige flag OFF por default |
| Contrato HTTP | `/api/v1/me` | `role`, `is_platform_admin`, `real_office_role`, `access_mode` | Aliases legados a manter na janela compatível |

### Alvos canônicos (pós-cutover)

| Legado | Canônico |
|---|---|
| `PLATFORM_ADMIN` | `platform_admin` (membership global) |
| `ADMIN` | `tenant_admin` (membership tenant, perfil null) |
| `OPERATOR` | `tenant_user` + perfil sistema `legacy-operator` |
| `VIEWER` | `tenant_user` + perfil sistema `legacy-viewer` |

## 2. Matriz congelada `OfficeRole::can*` → `TenantPermission`

| Método | ADMIN | OPERATOR | VIEWER | Chave canônica |
|---|:---:|:---:|:---:|---|
| `canManageClients` | ✓ | ✓ | — | `clients.manage` |
| `canManageCredentials` | ✓ | — | — | `credentials.manage` |
| `canTriggerSync` | ✓ | ✓ | — | `fiscal.sync.trigger` |
| `canManifestNfe` | ✓ | ✓ | — | `fiscal.nfe.manifest` |
| `canExport` | ✓ | ✓ | — | `exports.create` |
| `canShareListFilters` | ✓ | ✓ | — | `filters.share` |
| `canImportDocuments` | ✓ | ✓ | — | `documents.import` |
| `canMutateFiscal` | ✓ | — | — | `fiscal.mutations.execute` |
| `canManageWorkCatalog` | ✓ | — | — | `work.catalog.manage` |
| `canCreateWorkProcesses` | ✓ | ✓ | — | `work.processes.create` |
| `canExecuteWorkTasks` | ✓ | ✓ | — | `work.tasks.execute` |
| `canAdministerWork` | ✓ | — | — | `work.administer` |
| `canViewWork` | ✓ | ✓ | ✓ | `work.view` |
| `canDownloadWorkEvidence` | ✓ | ✓ | — | `work.evidence.download` |
| `canExportWork` | ✓ | ✓ | — | `work.exports.create` |

Chaves adicionais do catálogo (sem método `can*` 1:1, usadas em superfície/admin):

| Chave | Uso previsto |
|---|---|
| `tenant.dashboard.view` | Entrada / leitura base |
| `tenant.settings.view` / `tenant.settings.manage` | Configuração do escritório |
| `tenant.users.view` / `create` / `manage` | Equipe |
| `tenant.modules.manage` | Módulos do tenant |
| `tenant.permission_profiles.manage` | Perfis (somente tenant_admin, não delegável) |
| `tenant.roles.assign_admin` | Promover tenant_admin (não delegável) |
| `clients.view` | Leitura de carteira |
| `credentials.status.view` | Status sem material secreto |
| `fiscal.documents.view` / `fiscal.monitoring.view` | Leitura fiscal |
| `operations.view` / `operations.triage` | Inbox / triagem |

## 3. Helpers frontend (`app/utils/permissions.ts`)

| Helper | Papel efetivo típico | Chave canônica alvo |
|---|---|---|
| `hasConfirmedAdminAccess` | ADMIN | baseline tenant_admin / settings |
| `isPlatformAdmin` | flag global | `platform_role=platform_admin` |
| `canAccessPlatformAdmin` | platform | superfície `/admin/*` |
| `isPlatformPrivilegedContext` | platform + mode | `access_mode=platform_privileged` |
| `canAccessOfficeSettings` | ADMIN ou privilegiado | `tenant.settings.*` |
| `canManageClients` | ADMIN\|OPERATOR | `clients.manage` |
| `canManageCredentials` | ADMIN | `credentials.manage` |
| `canTriggerSync` | ADMIN\|OPERATOR | `fiscal.sync.trigger` |
| `canCreateExport` | ADMIN\|OPERATOR | `exports.create` |
| `canImportDocuments` | ADMIN\|OPERATOR | `documents.import` |
| `canAssociateCategories` | ADMIN\|OPERATOR | (fiscal categories; alinhar a `clients.manage` ou chave futura) |
| `canTriageMailbox` | ADMIN\|OPERATOR | `operations.triage` |
| `canExecuteHighRiskMutation` | ADMIN | `fiscal.mutations.execute` |
| `canManageOfficeTeam` | ADMIN / privilegiado | `tenant.users.*` + assign_admin |
| `canViewWork` / Work helpers | ver matriz Work | `work.*` |

**Regra:** frontend só reflete; backend é autoridade (TAG-15).

## 4. Singleton Owner (plataforma)

| Artefato | Path |
|---|---|
| Serviço | `app/Services/Platform/PlatformOwnerService.php` |
| Exceção | `app/Services/Platform/PlatformOwnerException.php` |
| Controller | `app/Http/Controllers/Api/V1/Platform/PlatformOwnerController.php` |
| Comandos | `app:platform-owner:recover`, `app:platform-owner:consolidate` |
| Índice parcial | `platform_memberships_one_platform_admin` |
| Onboarding | `InitialOnboardingService` (só global; Office manual depois) |
| Bootstrap dual | `BootstrapOfficeCommand` (cria Office + ADMIN) |

## 5. Ocorrências backend por família (decisão → canônica)

### Policies

| Arquivo | Decisão legada | Chave / destino |
|---|---|---|
| `ClientPolicy` | `canManageClients` / `Admin` delete | `clients.manage` |
| `ClientContactPolicy` | `canManageClients` | `clients.manage` |
| `ClientCredentialPolicy` | `Admin` only | `credentials.manage` |
| `EstablishmentPolicy` | `canManageClients` / `Admin` | `clients.manage` |
| `OfficeFiscalCredentialPolicy` | `Admin` | `credentials.manage` |
| `OfficeSettingsPolicy` | `Admin` | `tenant.settings.manage` |
| `OutboundCaptureProfilePolicy` | Admin\|Operator / Admin | `clients.manage` + admin settings |
| `SavedListFilterPolicy` | `canShareListFilters` / Admin | `filters.share` |
| `SerproTenantAccessPolicy` | all roles / Admin / Admin\|Op | `fiscal.*` + manage |
| Work policies (`UsesRealWorkRole`) | `can*` Work | `work.*` |

### Controllers / services (amostra alta densidade)

Famílias a migrar em lotes (task 4.3):

1. **Clients / Credentials / Imports / Exports** — `ClientController`, `DocumentImport*`, `ExportController`, `NoteController` (manifest), `SyncController`
2. **Fiscal / Monitoring / communications** — `*MonitoringController`, `SimplesMei`, `Dctfweb*`, `TaxGuide*`, `FiscalMutation*`, services de comunicação
3. **Operations / Inbox** — `Operations*`, collectors
4. **Outbound / SEFAZ** — `OutboundCapture*`, `OutboundDeadline*`, `SvrsNfce*`, `Cte*`
5. **Integra / SERPRO tenant** — `SerproTenant*`, `OfficeSerpro*`, `DteCanary*`, guards
6. **Work** — `Operational*` services/policies
7. **Equipe / ativação** — `OfficeTeamService`, `OfficeMemberController`
8. **Plataforma / owner** — `PlatformOwner*`, onboarding, bootstrap

Allowlist completa e atualizada: `config/multitenant_rbac.php` → `legacy_auth_literal_allowlist`.

### Frontend (superfícies)

| Área | Paths representativos |
|---|---|
| Tipos / API | `types/api.ts`, `createOfficeApi.ts`, `createPlatformApi.ts` |
| Auth / nav | `middleware/auth.global.ts`, `utils/auth-redirect.ts`, `utils/navigation.ts`, `utils/account-navigation.ts` |
| Permissões | `utils/permissions.ts`, `utils/monitoring-actions.ts` |
| Equipe | `components/settings/Team*` |
| Admin / SERPRO | `pages/admin/**`, console SERPRO |
| Work / monitores / clients | `pages/work/**`, `pages/**/monitoring/**`, `components/clients/**` |

### OpenSpec (changes ativas)

Specs de PGDAS-D, PGMEI e DCTFWeb ainda citam `ADMIN`/`OPERATOR`/`VIEWER` como contrato — realinhar na onda 9.1 para permissões semânticas (task 9.1).

## 6. O que NÃO entra no replace / gate de literais

| Padrão | Motivo |
|---|---|
| `OPERATOR_REVIEW` | Código de domínio fiscal |
| `FiscalRole` (`ISSUER`, `RECIPIENT`, …) | Papel fiscal do documento, não RBAC |
| Atributo HTML `role` | Acessibilidade |
| Perfis institucionais / outbound / pessoais | Outro domínio |
| `OWNER_CONFIRMATION`, `owner_approver_user_id` | Protocolo SERPRO histórico (semântica vira platform admin na onda 6.1; códigos permanecem) |

## 7. Feature flag

| Chave config | Env | Default | Kill switch |
|---|---|---|---|
| `features.canonical_multitenant_rbac.enabled` | `FEATURE_CANONICAL_MULTITENANT_RBAC` | **false** | global vence |

Com flag OFF: autoridade = caminho legado. Shadow mode (ondas posteriores) compara sem trocar autoridade.

## 8. Evidências W0

```bash
# Backend (caracterização + catálogo + flag + arquitetura)
cd backend && php artisan test \
  --filter='OfficeRoleCapabilitiesCharacterizationTest|TenantPermissionCatalogTest|FeatureFlagsTest|MultitenantRbacGuardrailsTest|OfficeRoleWorkCapabilitiesTest'

# Frontend
cd frontend && pnpm exec vitest run tests/unit/permissions.test.ts tests/unit/work-permissions.test.ts
```

Critérios:

- [x] Matriz `OfficeRole::can*` congelada e verde
- [x] Helpers frontend com matriz de caracterização
- [x] `TenantPermission` com chave/módulo/risco/delegable
- [x] Flag OFF por default (`FeatureFlagsTest`)
- [x] Arch test bloqueia `Gate::before` e literais fora da allowlist; fixtures propositais acusadas no detector
- [x] Inventário lista arquivo/decisão/chave canônica

## 9. Próximo passo

Task **2.1** — migrations aditivas (schema RBAC + lifecycle) sem ativar cutover.
