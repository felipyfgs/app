# Verificação backend — hub fiscal completo (16.1)

**Change:** `build-complete-fiscal-monitoring-hub` · task **16.1**  
**Data:** 2026-07-15  
**Ambiente:** local (Docker Compose · serviço `php`)  
**Operador:** agente de implementação (docs/ops)

> Foco desta entrega: **suite automatizada + preflight + registro**. Smoke produtivo real, aceite comercial SERPRO e drills manuais com cert/fatura dependem de ambiente/humano e estão em docs 16.4–16.8 com status `PENDING_OPS` quando aplicável.

## Escopo da suite executada

Filtro Pest/PHPUnit (relevante ao hub fiscal, SERPRO, plataforma, arquitetura e isolamento):

```bash
docker compose exec -T php php artisan test \
  --filter='Fiscal|Serpro|Platform|Architecture|Integra|Tenant'
```

### Resultado (2026-07-15)

| Métrica | Valor |
|---------|--------|
| Status | **PASS** |
| Tests | **219 passed** |
| Assertions | **1244** |
| Duration | ~15 s (script `docker/ops/fiscal-hub-verify-backend.sh`) |
| Failures / errors / skipped | 0 |

**Nota:** assert de `SerproHttpTransportSanitizeTest` alinhado a `LogSanitizer::scrubString` (omissão total da mensagem quando há material sensível residual, além de `Bearer [redacted]`).

Cobertura prática do filtro (não exaustivo):

- **Architecture:** FGTS sem scraping; controllers tenant sem dependência direta de SERPRO global; clientes SVRS allowlisted.
- **Platform:** lifecycle de assinatura, `PLATFORM_ADMIN`, isolamento negativo, troca de tenant.
- **Serpro / Usage / Integra:** contrato global sanitizado, autorização Autor/Termo/poderes, headers de cadeia, ledger shadow/bloqueio, reconciliação, Sitfis.
- **Fiscal / FiscalMonitoring:** core, Simples/MEI, DCTFWeb/MIT, parcelamentos, mailbox, FGTS/eSocial, mutações (gates), idempotência, normalização de situação.

## Suite completa (comando de referência)

Quando for necessário o gate de regressão total (backend **e** frontend), rodar:

```bash
# Backend — suite completa (Unit + Feature + Architecture)
docker compose exec -T php php artisan test

# Frontend (fora do container PHP; executar no host com Node do projeto)
cd frontend && npm test   # ou o script documentado no package.json / CI
```

A suite completa pode ser longa e inclui canais SEFAZ/ADN/outbound não exclusivos do hub Integra. Para PR do hub fiscal, o filtro da seção anterior é o gate mínimo recomendado; a suite completa deve rodar em CI ou antes de archive da change.

## Preflight isolamento multi-tenant

```bash
docker compose exec -T php php artisan ops:preflight-tenant-isolation
# opcional: --json --fail-on-issues
```

### Resultado local 2026-07-15

| Checagem | Qtd | Severidade |
|----------|-----|------------|
| Memberships órfãs | 0 | bloqueio |
| Roles inválidos | 0 | bloqueio |
| `office_id` nulo em colunas obrigatórias | 0 | bloqueio |
| Duplicidades críticas | 0 | bloqueio |
| **Migrations pendentes** | **3** | **bloqueio** |
| Membership ativa em office inativo | 0 | aviso |
| Usuários multi-membership | 0 | aviso |
| Offices sem membership | 0 | aviso |
| Scan vault limitado | 1 | aviso |

**Migrations pendentes no ambiente local** (instância Compose viva; **não** aplicadas neste passo 16.x):

1. `2026_07_15_246000_create_tax_guide_tables`
2. `2026_07_15_247000_create_esocial_fgts_monitoring_tables`
3. `2026_07_15_248000_create_fiscal_mutation_operations_tables`

> Testes automatizados usam SQLite in-memory e migram no bootstrap — por isso a suite **PASS** mesmo com essas 3 pendentes no Postgres local. Antes de piloto com dados reais, aplicar migrations no ambiente alvo e reexecutar o preflight com `--fail-on-issues`.

## Defaults seguros observados (config)

| Área | Default | Arquivo |
|------|---------|---------|
| Clientes SERPRO fake | `SERPRO_USE_FAKE_CLIENTS=true` | `config/serpro.php` |
| Smoke real | `SERPRO_SMOKE_ENABLED=false` / `PENDING_OPS` | `config/serpro.php` |
| Kill switch SERPRO | `SERPRO_KILL_SWITCH=false` (mas ops pode ligar) | `config/serpro.php` |
| Ledger shadow | `SERPRO_USAGE_SHADOW_MODE=true` | `config/serpro_usage.php` |
| Bloqueio comercial uso | `SERPRO_USAGE_COMMERCIAL_BLOCKING=false` | `config/serpro_usage.php` |
| Features hub | `FEATURES_GLOBAL_ENABLED=false` | `config/features.php` |
| Mutações | `FISCAL_MUTATIONS_ENABLED=false` | `config/fiscal_mutations.php` |
| Monitoramento | `FISCAL_MONITORING_ENABLED=false` | `config/fiscal_monitoring.php` |

## Script auxiliar

```bash
bash ./docker/ops/fiscal-hub-verify-backend.sh
# ou: bash ./docker/ops/fiscal-hub-verify-backend.sh --full
```

## Itens **não** cobertos por esta verificação automatizada

| Item | Doc / status |
|------|----------------|
| Threat model formal | `fiscal-hub-threat-model.md` |
| Política retenção/backup/exclusão | `fiscal-hub-retention-backup.md` |
| Trial mocks + ledger shadow (procedimento) | `fiscal-hub-trial-shadow-checklist.md` |
| Aceite piloto 1 office | `fiscal-hub-pilot-acceptance.md` |
| Smoke produtivo RO (cert real) | `fiscal-hub-prod-smoke-readonly.md` → **PENDING_OPS** |
| Conciliação fatura SERPRO | `fiscal-hub-usage-vs-invoice.md` → **PENDING_OPS** sem fatura |
| Drills suspensão/breaker/rollback | `fiscal-hub-resilience-drills.md` |
| Rollout por coortes | `fiscal-hub-cohort-rollout.md` |
| Aprovação mutantes | `fiscal-hub-mutating-approval.md` |
| Ops/suporte/comercial | `fiscal-hub-ops-support-onboarding.md` |
| Evidência comercial SaaS SERPRO | `serpro-integra-contador-commercial-legal-evidence.md` → **NO-GO** default |

## Conclusão 16.1

- **Gate automatizado backend (filtro Fiscal/Serpro/Platform/Architecture/Integra/Tenant):** satisfeito (219/219).
- **Suite completa backend + frontend:** documentada; não reexecutada na íntegra neste registro (comando acima).
- **Postgres local:** 3 migrations pendentes de guias/FGTS/mutações — aplicar antes de piloto com schema completo.
- **Próximos gates humanos/ops:** 16.2–16.11 (documentos nesta pasta) e evidência comercial SERPRO.
