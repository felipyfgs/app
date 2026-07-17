## Purpose

Definir o contrato de padronização do schema Postgres/Laravel do hub fiscal multi-escritório: perfis de tabela, tenancy Eloquent, lengths e casing canônicos, soft delete e evolução aditiva segura — sem confiar `office_id` do client e sem expor material de vault.

## ADDED Requirements

### Requirement: Perfis canônicos de tabela

O sistema SHALL classificar tabelas de domínio em um dos quatro perfis: tenant mutável, tenant append-only/evidência, plataforma/catálogo, ou pivot/junction. Novas migrations SHALL seguir o shape do perfil escolhido (PK `id` bigint, presença ou ausência de `office_id`, política de soft delete e mutabilidade).

#### Scenario: Nova tabela de cadastro tenant

- **WHEN** uma migration cria tabela de cadastro mutável por escritório
- **THEN** a tabela MUST ter `id`, `office_id` com FK para `offices`, timestamps de linha, e uniques de negócio compostos com `office_id` quando a chave for de tenant

#### Scenario: Nova tabela de evidência fiscal

- **WHEN** uma migration cria tabela append-only de evidência ou ledger de tenant
- **THEN** a tabela MUST ter `office_id`, MUST NOT usar `softDeletes`, e MUST referenciar vault apenas por `*_vault_object_id` opaco

#### Scenario: Tabela de catálogo ou plataforma

- **WHEN** a entidade for global da software house ou catálogo compartilhado (ex.: catálogo SERPRO, `platform_memberships`)
- **THEN** a tabela MUST NOT carregar `office_id` de tenancy fiscal implícita

### Requirement: Tenancy Eloquent em models de tenant

Todo model Eloquent que mapeia tabela de tenant com coluna `office_id` de isolamento fiscal SHALL usar o trait `BelongsToOffice` (auto-fill e global scope fail-closed via `CurrentOffice`, respeitando `PrivilegedOfficeContext`). Models de plataforma, membership e auditoria cross-tenant MAY permanecer sem o trait apenas se constarem em allowlist documentada e testada.

#### Scenario: Query de tenant sem escritório ativo

- **WHEN** `fail_closed_scopes` está habilitado e não há `CurrentOffice` nem contexto privilegiado aberto
- **THEN** queries em models com `BelongsToOffice` MUST retornar conjunto vazio (não todas as linhas)

#### Scenario: Model de tenant sem trait

- **WHEN** o inventário/teste de arquitetura encontra model de tenant com `office_id` fora da allowlist e sem `BelongsToOffice`
- **THEN** o teste MUST falhar

#### Scenario: Office_id do client

- **WHEN** um request envia `office_id` em query, body ou JSON
- **THEN** o sistema MUST NOT usar esse valor como autoridade de tenancy (autoridade permanece `CurrentOffice` / membership)

### Requirement: Length e formato de vault_object_id

Colunas cujo nome é `vault_object_id` ou termina em `_vault_object_id` SHALL ser `string(26)` alinhadas ao ULID emitido pelo `SecureObjectStore`. Novas colunas MUST NOT usar lengths 40 ou 64. Remediação de colunas legadas SHALL auditar comprimentos reais antes de encolher.

#### Scenario: Nova coluna de referência ao cofre

- **WHEN** uma migration adiciona referência a objeto no vault
- **THEN** a coluna MUST ser string de comprimento 26

#### Scenario: Shrink sem auditoria

- **WHEN** se planeja reduzir length de coluna vault existente
- **THEN** a remediação MUST verificar que nenhum valor persistido excede 26 caracteres antes do ALTER restritivo

### Requirement: Length e normalização de CNPJ

Colunas de CNPJ de contribuinte ou identidade fiscal (`cnpj`, `*_cnpj`, `root_cnpj` com regra própria de 8 para raiz) SHALL persistir apenas dígitos. CNPJ completo MUST usar comprimento 14; raiz MUST usar comprimento 8. Valores com máscara MUST ser normalizados antes de constraints de unicidade.

#### Scenario: Persistência de CNPJ completo

- **WHEN** o sistema grava um CNPJ de 14 posições
- **THEN** o valor persistido MUST conter exatamente 14 dígitos sem pontuação

#### Scenario: Coluna legada com length 18

- **WHEN** existe coluna `*cnpj*` com length 18
- **THEN** a wave de remediação MUST normalizar dados e alinhar a coluna a 14 (ou 8 se for raiz documentada) sem aceitar máscara permanente

### Requirement: Status, environment e competence canônicos

Colunas de máquina de estado `status` (e equivalentes de estado de agregado) SHALL usar `string(32)` e valores em `SCREAMING_SNAKE_CASE`, exceto enquanto um Enum PHP do agregado publicar explicitamente valores lowercase — nesse caso default e Enum MUST permanecer alinhados entre si. Colunas `environment` SHALL usar `string(20)` e valores `SCREAMING`. Competência mensal SHALL usar `string(7)` no formato `YYYY-MM`.

#### Scenario: Nova coluna status

- **WHEN** uma migration adiciona `status` de máquina de estado
- **THEN** a coluna MUST ter length 32 e default SCREAMING alinhado ao Enum PHP do agregado

#### Scenario: Competence mensal

- **WHEN** uma entidade registra competência mensal
- **THEN** o valor MUST caber em 7 caracteres no formato `YYYY-MM`

### Requirement: Soft delete allowlist

Soft delete (`softDeletes` / `deleted_at`) SHALL ser permitido somente nas tabelas de cadastro: `clients`, `establishments`, `client_contacts`, `client_custom_fields`. Tabelas de evidência fiscal, cursores, ledgers e projeções MUST NOT introduzir soft delete.

#### Scenario: Tentativa de soft delete em evidência

- **WHEN** uma migration propõe `softDeletes` em tabela de evidência ou projeção fiscal
- **THEN** a mudança MUST ser rejeitada pelo contrato de schema (review/teste de convenção)

### Requirement: Evolução aditiva e inventário

Remediações de schema SHALL preferir alargar colunas antes de encolher. O repositório SHALL manter inventário ou teste de arquitetura que registre desvios legados allowlisted e falhe ao introduzir novos desvios das regras de vault 26, CNPJ 14 e trait de tenancy. Naming legado de membership tenant `office_user` MUST ser preservado (sem rename cosmético nesta capability).

#### Scenario: Introduzir desvio novo de vault

- **WHEN** uma migration nova define `vault_object_id` com length diferente de 26
- **THEN** o inventário/teste de arquitetura MUST falhar

#### Scenario: Membership tenant

- **WHEN** o código referencia membership de usuário em escritório
- **THEN** a tabela canônica permanece `office_user` (não se exige rename para `office_memberships`)

### Requirement: Documentação operacional das convenções

O repositório SHALL expor documentação operacional (ex.: `docs/ops/schema-conventions.md` ou âncora equivalente) descrevendo os quatro perfis, a tabela de lengths canônicos e a allowlist de soft delete, para orientar authors de migrations e reviewers.

#### Scenario: Author consulta o canônico

- **WHEN** um desenvolvedor precisa criar uma nova tabela tenant
- **THEN** a documentação operacional MUST indicar office_id, trait, lengths de vault/CNPJ/status e proibição de soft delete fora da allowlist
