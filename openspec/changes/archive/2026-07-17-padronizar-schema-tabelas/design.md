## Context

O monorepo concentra o modelo de dados fiscal multi-escritório em PostgreSQL via migrations Laravel (~167 tabelas). Há padrões fortes implícitos (`foreignId('office_id')`, uniques compostos, partial uniques, soft delete só no cadastro), mas lengths e cobertura Eloquent divergiram:

- `vault_object_id` com 26 (ULID real do `SecureObjectStore`), 40 e 64
- CNPJ majoritariamente 14; exceções em 18 (sugere máscara)
- `status`/`environment` com lengths e casing mistos
- ~20 models com coluna `office_id` sem trait `BelongsToOffice`
- migration duplicada de `office_serpro_onboarding_states` (`900104` e `900401`)

A change formaliza o canônico e remedia em ondas, sem big-bang e sem rename cosmético de pivots.

## Goals / Non-Goals

**Goals:**

- Um contrato canônico de schema (4 perfis + convenções de coluna) aplicável a review de PR e a novas migrations.
- Fechar lacunas de tenancy no Eloquent para dados de tenant.
- Alinhar lengths de identidade (vault, CNPJ) e máquina de estado (`status`, `environment`, `competence`) de forma aditiva e auditável.
- Inventário/teste que impeça regressão das regras de ouro.
- Histórico de migration de onboarding coerente em `migrate:fresh`.

**Non-Goals:**

- Renomear `office_user` → `office_memberships` ou unificar pivots por estética.
- Soft delete em evidência/projeção/cursor fiscal.
- Bulk de `timestamp` → `timestamptz` em todas as colunas legadas.
- Um único enum global de `status` para todos os agregados.
- Alterar APIs REST/Nuxt, SERPRO live, canais outbound ou mutações fiscais.
- Expor conteúdo de vault, PFX, tokens ou XML.

## Decisions

### D1 — Quatro perfis de tabela (não um “estilo único”)

| Perfil | `office_id` | Soft delete | Mutabilidade | Trait |
|--------|-------------|-------------|--------------|--------|
| Tenant mutável | obrigatório | allowlist cadastro | update ok | `BelongsToOffice` |
| Tenant append-only / evidência | obrigatório | proibido | append / nova versão | `BelongsToOffice` |
| Plataforma / catálogo | ausente (fiscal) | N/A | conforme domínio | sem trait |
| Pivot / junction | conforme papel | sem soft delete genérico | membership/link | membership: sem trait obrigatório de “dado fiscal”; links tenant com trait |

**Alternativa rejeitada:** forçar o mesmo shape (sempre timestamps + softDeletes) em tudo — conflita com imutabilidade fiscal e catálogo global SERPRO.

### D2 — Convenções canônicas de coluna (uma opção cada)

| Item | Canônico |
|------|----------|
| PK | `id()` bigint |
| `office_id` | `foreignId` + `constrained('offices')` + cascade (ou restrict em evidência com retenção) |
| `*vault_object_id*` | `string(26)` — alinhado a `Str::ulid()` no store |
| `*cnpj*` | `string(14)`, só dígitos na persistência |
| `status` / máquina de estado | `string(32)`, valores `SCREAMING_SNAKE` |
| `environment` | `string(20)`, `SCREAMING` |
| `competence` / period key mensal | `string(7)` `YYYY-MM` |
| flags de estado | prefixo `is_` |
| instantes de domínio novos | `timestampTz` |
| linha Eloquent | `$table->timestamps()` salvo append-only documentado (`created_at` only) |

**Exceção de status:** se o Enum PHP do agregado já publica lowercase (`incomplete`, `unverified`), o default da migration segue o Enum até wave dedicada de rename de valor — length continua 32.

**Alternativa rejeitada:** `enum` nativo Postgres para todo `status` — churn alto com sqlite de testes e vocabulários por agregado.

### D3 — Widen before shrink

- Aumentar length (ex.: status 20→32) é a operação padrão.
- Encolher (vault 40/64→26, CNPJ 18→14, environment 40→20) **só** após `MAX(LENGTH(...))` / inventário de valores e normalização de dados.
- Preferir script/teste de inventário a migration cega.

### D4 — Política `BelongsToOffice`

1. Classificar cada model com `office_id`: **tenant**, **plataforma com office opcional**, **pivot membership**, **auditoria cross-tenant**.
2. Tenant e evidência de tenant → trait obrigatório.
3. Plataforma / membership / audit chain → **sem** trait; scope explícito no código de acesso; documentar na allowlist de exceções do teste de arquitetura.
4. Não auto-preencher `office_id` em jobs de plataforma via trait cego.

**Alternativa rejeitada:** trait em todos os models com a coluna — quebra jobs privilegiados e memberships.

### D5 — Soft delete: allowlist fixa

Permitido apenas: `clients`, `establishments`, `client_contacts`, `client_custom_fields`.  
Evidência fiscal continua hard-delete / restrict FK (já há waves de retenção).

### D6 — Naming de junction (documentar, não renomear)

- Membership tenant: **`office_user`** (estável).
- Membership plataforma: **`platform_memberships`**.
- Links com payload: `*_links`.
- Novas tabelas **não** criam `office_memberships` paralelo.

### D7 — Inventário e teste de arquitetura

- Teste Architecture (PHPUnit) e/ou script CLI de inventário que falha se:
  - nova migration introduzir `*vault_object_id*` com length ≠ 26
  - `*cnpj*` com length ≠ 14 (exceto colunas explicitamente allowlisted se ainda em remediação)
  - model tenant mapeado sem `BelongsToOffice` fora da allowlist de exceções
- Doc operacional curta (`docs/ops/schema-conventions.md`) espelhando o canônico para humanos/review.

### D8 — Migration duplicada de onboarding

- Manter um create “source of truth”.
- O segundo arquivo vira no-op documentado (ou merge apenas em greenfield) sem `dropIfExists` destrutivo em ambientes com dados.
- Garantir `migrate:fresh` + testes de schema (`hasTable` guards já existem).

### D9 — Ondas de implementação (não monólito)

| Wave | Conteúdo |
|------|----------|
| W0 | Doc + inventário + teste de arquitetura (fail em desvios **novos**; baseline dos legados allowlisted) |
| W1 | Trait nos models tenant classificados + testes isolamento |
| W2 | Vault 26 + CNPJ 14 (com auditoria/normalização) |
| W3 | status/environment/competence lengths + casing por agregado |
| W4 | onboarding migration + política timestampTz só para colunas novas |

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Trait em model errado quebra job de plataforma | Classificação + allowlist de exceções no teste |
| Shrink de vault trunca ID não-ULID | Auditoria `length`; investigar outliers antes do ALTER |
| CNPJ 18→14 quebra unique se dados tiverem máscara inconsistente | Normalizar dígitos em migration de dados antes do shrink |
| Environment 40→20 corta valores SEFAZ longos | Inventário de distinct values; mapear aliases; só então shrink |
| Status lowercase → SCREAMING quebra comparações no PHP | Alinhar Enum e migration no **mesmo** PR por agregado |
| Partial unique / checks só em pgsql falham em sqlite | Manter guards `DB::getDriverName()` como no repo |
| Teste de inventário ruidoso (muitos legados) | Baseline allowlist encolhe a cada wave; fail em **novos** desvios desde W0 |
| Duplicata onboarding confunde reinstall | No-op documentado + teste `migrate:fresh` |

## Migration Plan

1. **W0** merge: doc + architecture test com allowlist do estado atual (não quebra CI).
2. **W1** merge: traits + testes de isolamento; monitorar jobs Horizon que usam `PrivilegedOfficeContext`.
3. **W2–W3** em PRs por família de colunas; em produção: backup, migrate, validar counts.
4. **W4** limpeza histórica de migration; só seguro se fresh install continua verde.
5. **Rollback:** migrations aditivas com `down()` que reverte length/trait não se reverte por migration (código); preferir feature flags só se query path mudar — nesta change o risco maior é W1 (scope fail-closed mais rígido), revertível por revert de commit do trait.

## Open Questions

- Lista final de exceções **permanentes** sem `BelongsToOffice` (ex.: `AuditLog`, `PlatformPrivilegedAuditEvent`, `OfficeMembership`) — fechar na W1 com revisão do inventário.
- Há algum `vault_object_id` que não seja ULID do `SecureObjectStore`? Confirmar na auditoria W2 antes do shrink.
- Valores reais de `environment` > 20 em bases de dev/staging — se existirem, adiar shrink e só widen/documentar.
