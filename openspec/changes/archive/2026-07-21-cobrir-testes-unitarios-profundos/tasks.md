## 1. N0 — Alinhamento com a change crítica

- [x] 1.1 Confirmar inventário/gates L0–L3 da `cobrir-testes-unitarios-criticos` verdes (ou archive concluído)
- [x] 1.2 Mapear paths reais: `SimplesMeiAdapter`, pós-consulta PGMEI, `FiscalMutationPolicy`, `EnvelopeCrypto` (+ DocumentVaultReader se necessário)
  - Depende de: 1.1

## 2. N1 — Domínio Simples/MEI

- [x] 2.1 Unit: `SimplesMeiAdapter` com stub de fonte (PGMEI leitura)
  - Evidência: `php artisan test --filter=SimplesMeiAdapter`
- [x] 2.2 Unit: `PgmeiPostConsultService` e/ou `PgmeiDebtProjector` com decode fixture/inline
  - Depende de: 1.2
  - Evidência: filtro PgmeiPostConsult|PgmeiDebt

## 3. N1 — Mutação fail-closed

- [x] 3.1 Unit: `FiscalMutationPolicy` — kill switch / pré-condição faltante
  - Evidência: `php artisan test --filter=FiscalMutationPolicy`

## 4. N2 — Jornada PGMEI consult

- [x] 4.1 Feature: seed auth utilizável + contrato/limites mínimos TRIAL
- [x] 4.2 Feature: consult/enqueue PGMEI MONITOR com Integra/SERPRO fake — não `AUTHORIZATION_MISSING` por DRAFT; sem egress real
  - Depende de: 4.1, 2.1
  - Evidência: `php artisan test --filter=PgmeiConsultHappy|PgmeiMonitoring`

## 5. N2 — Vault crypto

- [x] 5.1 Unit: `EnvelopeCrypto` round-trip + material inválido (harness de teste local)
  - Evidência: `php artisan test --filter=EnvelopeCrypto`
  - Se harness inexistente: documentar blocker em design e criar stub mínimo de keyring de teste

## 6. N3 — Gates

- [x] 6.1 `vendor/bin/pint --test` nos arquivos tocados + artisan test filtrado da onda
  - Depende de: 2.2, 3.1, 4.2, 5.1
- [x] 6.2 `npx @fission-ai/openspec@1.6.0 validate cobrir-testes-unitarios-profundos --type change --strict`
  - Depende de: 6.1
