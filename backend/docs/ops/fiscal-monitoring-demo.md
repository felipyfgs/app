# Fixtures fiscais demonstrativas (office `demo`)

Dataset sintético do hub de monitoramento fiscal para **local/testing**.  
Não possui validade fiscal e **nunca** é carregado em produção.

## Escopo

| Item | Valor |
|------|--------|
| Office | slug `demo` (`FISCAL_DEMO_OFFICE_SLUG`) |
| Sentinela (isolamento) | slug `demo-sentinel` |
| Ambientes | `local`, `testing` apenas |
| Data-âncora | `DEMO_FISCAL_ANCHOR_AT` (default `2026-06-15T12:00:00-03:00`) |
| Manifesto | `Database\Seeders\Demo\FiscalDemoManifest` v1.0.0 — 18 clientes |
| Marcador | notes/`metadata` com `[demo-fixture]`; `correlation_id` prefixo `DEMO_` |

## Comandos

```bash
# Via DatabaseSeeder (users + DemoCatalog + fiscal demo)
php artisan db:seed

# Só o dataset fiscal (idempotente — purga fixtures demo e recria)
php artisan fiscal:demo-seed
```

Contagens impressas são sanitizadas (sem `vault_object_id`, tokens ou material criptográfico).

## Perfil local somente leitura

Habilite leitura do hub; mantenha mutações e scheduler desligados:

```env
APP_ENV=local
FEATURES_GLOBAL_ENABLED=true
FISCAL_MONITORING_ENABLED=true
FISCAL_MONITORING_SCHEDULER_ENABLED=false
FISCAL_MONITORING_MUTATING_ENABLED=false
FEATURES_MUTATING_ENABLED=false

FEATURE_SIMPLES_MEI_ENABLED=true
FEATURE_DCTFWEB_MIT_ENABLED=true
FEATURE_PARCELAMENTOS_ENABLED=true
FEATURE_SITFIS_ENABLED=true
FEATURE_MAILBOX_ENABLED=true
FEATURE_DECLARACOES_ENABLED=true
FEATURE_GUIAS_ENABLED=true
FEATURE_FGTS_ENABLED=true

# Mutações de módulo permanecem OFF
FEATURE_SIMPLES_MEI_MUTATING_ENABLED=false
FEATURE_DCTFWEB_MIT_MUTATING_ENABLED=false
FEATURE_PARCELAMENTOS_MUTATING_ENABLED=false
FEATURE_SITFIS_MUTATING_ENABLED=false
FEATURE_MAILBOX_MUTATING_ENABLED=false
FEATURE_DECLARACOES_MUTATING_ENABLED=false
FEATURE_GUIAS_MUTATING_ENABLED=false
FEATURE_FGTS_MUTATING_ENABLED=false
FEATURE_MUTACOES_MUTATING_ENABLED=false

DEMO_FISCAL_ANCHOR_AT=2026-06-15T12:00:00-03:00
FISCAL_DEMO_ENABLED=true
FISCAL_DEMO_OFFICE_SLUG=demo
```

## Comportamento de mutações

- Preflight de mutações fiscais no office `demo` retorna `DEMO_MODE` (bloqueio explícito).
- Emissão de guias no office demo é bloqueada com código `demo_mode`.
- Ações internas (filtros, associação de categoria, triagem de mailbox, navegação) continuam permitidas conforme o papel.
- Clients fake (`FakeIntegraContadorClient`, etc.) **não** são acionados pelo seeder.

## Proveniência

- Resolver: `App\Services\Fiscal\Demo\FiscalDataOriginResolver`
- Em `local/testing` + office demo → `data_origin=DEMO`
- Em production → sempre `LIVE` (mesmo com variáveis DEMO_*); seeder recusa execução

## Segurança

O seeder **não** cria:

- PFX, senha, PEM, chave privada
- Consumer Secret / tokens SERPRO / Termo assinado
- XML fiscal real
- Contrato SERPRO sintético

Arquivos de evidência/mailbox/guias passam por `SecureObjectStore` com marca  
`DEMONSTRAÇÃO — SEM VALIDADE FISCAL`. APIs públicas não expõem `vault_object_id`.

## Idempotência e purga

1. Guard de ambiente + slug
2. Transação: remove apenas clientes/registros marcados `[demo-fixture]` / `DEMO_*` no office demo
3. Recria a partir do manifesto
4. Office sentinela (mesmo CNPJ de C01) é criado/preservado fora da carteira demo

Outros tenants **não** são tocados.

## FGTS

Cobertura parcial via eSocial (S-1299 / S-5003).  
`guide_status` e `payment_status` permanecem `UNSUPPORTED` (sem API pública FGTS Digital).

## Cursor documental BLOCKED

Cliente C09: `SyncCursor` com `consecutive_decode_failures=5`, status `BLOCKED`,  
`last_nsu` preservado (sem salto silencioso).

## Produção

- `FiscalMonitoringDemoSeeder` e `fiscal:demo-seed` recusam `APP_ENV=production`
- Variáveis `FISCAL_DEMO_*` / `DEMO_*` em production são ignoradas pelo guard
- Build/runtime produtivo não depende do manifesto nem de mocks Nuxt

## Diagnóstico

```bash
php artisan fiscal:demo-seed
# Conferir contagens clients≈18, runs/snapshots > 0, sentinel_office=1

# Isolamento: office demo não lista cliente do demo-sentinel
```
