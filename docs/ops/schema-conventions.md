# Convenções canônicas de schema

Contrato operacional do modelo de dados Postgres/Laravel do hub fiscal multi-escritório.
Espelha a capability OpenSpec `schema-conventions`. Config/testes: `backend/config/schema_conventions.php` e `tests/Architecture/SchemaConventionsTest.php`.

## Quatro perfis de tabela

| Perfil | `office_id` | Soft delete | Mutabilidade | Trait Eloquent |
|--------|-------------|-------------|--------------|----------------|
| **Tenant mutável** | obrigatório + FK `offices` | só allowlist cadastro | update ok | `BelongsToOffice` |
| **Tenant append-only / evidência** | obrigatório | **proibido** | append / nova versão | `BelongsToOffice` |
| **Plataforma / catálogo** | ausente (fiscal) | N/A | conforme domínio | sem trait |
| **Pivot / junction** | conforme papel | sem soft delete genérico | membership/link | membership **sem** trait |

### Shapes esperados

**Cadastro tenant (ex.: clients)**  
`id` bigint · `office_id` FK · timestamps · softDeletes **somente** se allowlist · uniques de negócio compostos com `office_id`.

**Evidência / ledger tenant**  
`id` · `office_id` · sem `softDeletes` · vault só via `*_vault_object_id` opaco · preferir imutabilidade.

**Catálogo / plataforma**  
Sem `office_id` de tenancy fiscal (ex.: catálogo SERPRO, `platform_memberships`).

**Membership**  
Tabela canônica tenant: **`office_user`** (model `OfficeMembership`). Plataforma: `platform_memberships`. **Não** criar `office_memberships` paralelo.

## Lengths e formatos canônicos

| Item | Canônico | Notas |
|------|----------|--------|
| PK | `id()` bigint | |
| `office_id` | `foreignId` + `constrained('offices')` | cascade ou restrict em evidência com retenção |
| `*vault_object_id*` | `string(26)` | ULID do `SecureObjectStore` (`Str::ulid()`) |
| `*cnpj*` completo (dígitos clássicos) | `string(14)`, só dígitos | sem máscara na persistência |
| `root_cnpj` / raiz | `string(8)` | |
| Identidade CNPJ alfanumérica (SERPRO) | até `string(18)` | allowlist: `contractor_cnpj` em credential versions, `destination_cnpj` em term versions, `author_identity` |
| `status` (máquina de estado) | `string(32)`, valores `SCREAMING_SNAKE` | se Enum PHP já publica lowercase, default segue o Enum até wave de rename |
| `environment` | `string(20)`, `SCREAMING` | legado SEFAZ pode estar em 40 até shrink auditado |
| `competence` mensal | `string(7)` `YYYY-MM` | `period_key` multi-formato pode ser maior (documentar) |
| flags | prefixo `is_` | |
| instantes de domínio **novos** | `timestampTz` | sem bulk rewrite de `timestamp` legado |
| linha Eloquent | `$table->timestamps()` | append-only pode ter só `created_at` |

## Soft delete — allowlist fixa

Permitido **somente**:

- `clients`
- `establishments`
- `client_contacts`
- `client_custom_fields`

Evidência fiscal, cursores, ledgers e projeções: **hard-delete / restrict FK**. Não introduzir `softDeletes`.

## Tenancy Eloquent

- Autoridade: `CurrentOffice` — **nunca** confiar `office_id` do client.
- Models de **tenant** com coluna `office_id` de isolamento fiscal: trait `BelongsToOffice` (auto-fill + global scope fail-closed).
- `PrivilegedOfficeContext` (jobs/console) e flag `fiscal_data_model.fail_closed_scopes` controlam o scope.
- Exceções permanentes (membership, audit, billing/ops de plataforma, `office_id` nullable): ver `config/schema_conventions.php` → `belongs_to_office_exceptions`.

### Classificação inventário (models com `office_id` sem trait — baseline)

| Classe | Classificação | Trait? |
|--------|---------------|--------|
| `OfficeMembership` | membership (`office_user`) | não (permanente) |
| `OfficeSubscription` | comercial multi-contexto | não (permanente) |
| `AccountActivation` | ativação / auth flow | não (permanente) |
| `AuditLog` | auditoria cross-tenant | não (permanente) |
| `PlatformPrivilegedAuditEvent` | auditoria plataforma | não (permanente) |
| `VaultObjectJournalEntry` | journal de vault | não (permanente) |
| `SerproBillingInvoiceLine` | billing plataforma | não (permanente) |
| `SerproOfficeQuantityUsageLimit` | limite de plataforma | não (permanente) |
| `SerproRetentionJob` | job de retenção plataforma | não (permanente) |
| `SerproRolloutApproval` | aprovação de rollout | não (permanente) |
| `SerproUsageBudget` | budget plataforma | não (permanente) |
| `SerproUsageIncident` | incidente plataforma | não (permanente) |
| `SerproUsageMonthlyAggregate` | agregado (`office_id` nullable GLOBAL) | não (permanente) |
| `SerproUsageReconciliationAdjustment` | reconciliação plataforma | não (permanente) |
| `SerproReadinessRun` | readiness (`office_id` nullable em scope plataforma) | não (permanente) |
| `User` | não é coluna de tenancy fiscal (`selected_office_id`) | N/A |
| `SerproDteCanaryRequest` | dual-access plataforma + tenant | não (permanente; isolamento nos services) |
| `SerproAsyncJobRun` | tenant puro | **sim** (W1) |
| `SerproAuthorizationConsent` | tenant puro | **sim** (W1) |
| `SerproEventosRun` | tenant puro | **sim** (W1) |
| `SerproTermVersion` | tenant puro | **sim** (W1) |

## Inventário de colunas (migrations)

### Vault (`*vault_object_id*`)

- Canônico: **26**.
- Legados remediados nesta change (create + migration aditiva): 40 (monitoring, mailbox, tax_guide, esocial) e 64 (MA CSC/seed, operational evidence).
- **Auditoria antes de shrink em produção:**

```sql
-- Postgres: rodar por coluna antes do ALTER restritivo
SELECT MAX(LENGTH(vault_object_id)) AS max_len FROM fiscal_evidence_artifacts;
-- Repetir para cada *vault_object_id* (listar em information_schema)
SELECT table_name, column_name, character_maximum_length
FROM information_schema.columns
WHERE column_name LIKE '%vault_object_id%'
  AND table_schema = 'public';
```

A migration de remediação aborta se `MAX(LENGTH(...)) > 26`.

### CNPJ

- Quase tudo já em 14 / raiz 8.
- **Exceção intencional 18:** identidades alfanuméricas SERPRO (`serpro_credential_versions.contractor_cnpj`, `serpro_term_versions.destination_cnpj`) — não tratar como “máscara”.
- Normalização de máscara (dígitos) deve ocorrer na camada de domínio (`BrazilianTaxId` / services), não persistir pontuação.

### Status / environment / competence

- `status` subdimensionado (< 32): widen para 32 (migration aditiva). Lengths > 32 legados (40) são aceitáveis (widen-first; não encolher).
- `environment` canônico 20; legados SEFAZ em 40 permanecem allowlisted até inventário de `DISTINCT` / `MAX(LENGTH)` confirmar shrink seguro.
- `competence` mensal já em 7 na maioria; `period_key` / `competence_period_key` com 20+ podem carregar formatos não mensais — não forçar 7 sem auditoria de valores.

## Regras de ouro de review (PR)

1. Nova tabela tenant → `office_id` + model com `BelongsToOffice` (ou justificativa na allowlist + teste).
2. Nova `*vault_object_id*` → **somente** `string(26)`.
3. Novo CNPJ clássico → `string(14)` dígitos; raiz → 8; alfanumérico SERPRO → documentar e allowlist se > 14.
4. Novo `status` de máquina → `string(32)` + default alinhado ao Enum PHP.
5. Novo `environment` → `string(20)` SCREAMING.
6. Soft delete só na allowlist de cadastro.
7. Preferir **widen** a shrink; shrink só com `MAX(LENGTH)` / distinct values.
8. Instantes de domínio novos → `timestampTz`.
9. Não confiar `office_id` do client; não expor vault/XML/segredos em JSON de API.
10. Não renomear `office_user` por estética.

## Histórico de migration: onboarding SERPRO

- **Fonte do create:** `2026_07_16_900104_create_office_serpro_onboarding_states_table.php` (create + backfill).
- **Stub no-op:** `2026_07_16_900401_...` — não recria a tabela; documenta dependência do 900104.
- `migrate:fresh` cria a tabela **uma vez**.

## Evolução aditiva

Remediações preferem migration aditiva. Alterar arquivos de create antigos só para alinhar `migrate:fresh` ao canônico **e** sempre com migration de upgrade para ambientes que já rodaram o create antigo.
