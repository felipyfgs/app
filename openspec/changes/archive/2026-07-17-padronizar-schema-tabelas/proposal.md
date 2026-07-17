## Why

O hub fiscal já possui ~167 tabelas com uma base sólida (tenancy por `office_id`, FKs e uniques compostos), mas convenções de coluna, lengths e cobertura do trait `BelongsToOffice` divergiram entre ondas de domínio (DF-e, outbound, SERPRO, monitoramento). Sem um contrato canônico, novas migrations e models ampliam o risco de vazamento multi-tenant, CNPJ mascarado e referências de vault inconsistentes.

## What Changes

- Documentar e adotar **convenções canônicas de schema** (4 perfis de tabela + lengths/casing/traits).
- Publicar **regras de review** para novas migrations/models (vault 26, CNPJ 14, status 32 SCREAMING, etc.).
- Corrigir **lacunas de tenancy no Eloquent**: models de tenant com `office_id` devem usar `BelongsToOffice` (com classificação explícita de exceções de plataforma).
- Alinhar **colunas de identidade** em ondas aditivas: `vault_object_id` → 26 (após auditoria), CNPJ → 14 sem máscara, `status`/`environment`/`competence` com lengths canônicos (widen first).
- Consolidar **migration duplicada** de `office_serpro_onboarding_states` (histórico limpo, sem drop em produção).
- Introduzir **inventário/teste de arquitetura** que falhe quando novos desvios canônicos forem introduzidos.
- **Não** renomear pivots legados de alto churn (`office_user`); **não** soft-delete em evidência fiscal; **não** big-bang de 167 tabelas.

## Capabilities

### New Capabilities

- `schema-conventions`: contrato permanente de padronização do modelo de dados Postgres/Laravel do hub — perfis de tabela (tenant mutável, append-only, plataforma, pivot), tenancy Eloquent, lengths e casing canônicos, allowlist de soft delete e regras de evolução aditiva do schema.

### Modified Capabilities

- (nenhuma — main specs ainda vazias; esta change introduz a capability.)

## Impact

- **Backend:** `database/migrations/*` (waves aditivas), `app/Models/**` (trait `BelongsToOffice`), possível `docs/ops/schema-conventions.md` ou âncora em `config/fiscal_data_model.php`, testes Architecture/Feature de inventário e isolamento.
- **Tenancy:** reforço fail-closed via `CurrentOffice` + `BelongsToOffice`; exceções de plataforma documentadas.
- **APIs/Frontend:** sem mudança de contrato de API prevista; sem UI.
- **Ops:** migrations aditivas (widen/shrink com auditoria); sem rebuild de vault; sem exposição de segredos.
- **Fora de escopo:** SERPRO live, mutações fiscais outbound, rename massivo de tabelas, portal de contribuinte.
