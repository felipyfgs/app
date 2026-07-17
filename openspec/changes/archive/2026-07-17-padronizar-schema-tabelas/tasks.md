## 1. Documentação e baseline

- [x] 1.1 Criar `docs/ops/schema-conventions.md` com os 4 perfis, tabela de lengths canônicos, allowlist de soft delete e regras de ouro de review
- [x] 1.2 Inventariar models com `office_id` sem `BelongsToOffice` e classificar em tenant / plataforma / membership / auditoria (lista versionada no doc ou config)
- [x] 1.3 Inventariar colunas `*vault_object_id*` (lengths), `*cnpj*` (lengths) e `status`/`environment`/`competence` (lengths) a partir das migrations

## 2. Teste de arquitetura / inventário

- [x] 2.1 Adicionar teste Architecture (PHPUnit) que falha se model de tenant (fora da allowlist de exceções) não usar `BelongsToOffice`
- [x] 2.2 Estender inventário/teste para falhar em **novas** colunas vault ≠ 26 e CNPJ completo ≠ 14 (baseline allowlist para legados até remediação)
- [x] 2.3 Garantir allowlist inicial cobre desvios legados conhecidos para CI permanecer verde após 2.1–2.2

## 3. Tenancy Eloquent (wave W1)

- [x] 3.1 Aplicar `BelongsToOffice` nos models classificados como tenant puro na lista 1.2
- [x] 3.2 Documentar exceções permanentes (membership, platform, audit chain) na allowlist do teste 2.1
- [x] 3.3 Adicionar ou estender testes de isolamento fail-closed (sem `CurrentOffice` → vazio; com office → só aquele office)

## 4. Vault e CNPJ (wave W2)

- [x] 4.1 Auditar comprimentos reais de `*vault_object_id*` (script ou query documentada); só então migration aditiva para `string(26)` onde max≤26
- [x] 4.2 Normalizar valores CNPJ com máscara/length 18 → 14 dígitos e alinhar definições de coluna para 14 (raiz permanece 8)
- [x] 4.3 Remover da allowlist do inventário os desvios de vault/CNPJ já remediados; revalidar testes 2.x

## 5. Status, environment e competence (wave W3)

- [x] 5.1 Widen de colunas `status` subdimensionadas para 32 onde necessário; defaults alinhados aos Enums PHP do agregado
- [x] 5.2 Alinhar `environment` a length 20 e valores SCREAMING após inventário de distinct values (sem truncar valores legados longos sem mapeamento)
- [x] 5.3 Alinhar `competence` / period keys mensais a `string(7)` `YYYY-MM` onde aplicável após auditoria de dados

## 6. Histórico de migration e higiene (wave W4)

- [x] 6.1 Consolidar duplicata `office_serpro_onboarding_states` (`900104` / `900401`): um create fonte + outro no-op documentado; `migrate:fresh` cria a tabela uma vez
- [x] 6.2 Registrar na doc que instantes de domínio **novos** usam `timestampTz` (sem bulk rewrite legado)

## 7. Verificação e fechamento

- [x] 7.1 Rodar `cd backend && vendor/bin/pint --test` e `php artisan test` nas suites afetadas (Architecture + Feature de tenancy/schema)
- [x] 7.2 Validar OpenSpec: `npx openspec validate --specs --strict` (e change se CLI exigir)
- [x] 7.3 Archive/sync da change + commit no mesmo dia ao fechar o software (`openspec-archive-change` / sync specs main)
