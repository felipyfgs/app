## Context

O portfolio fiscal (`ModulePortfolioQueryService`) lista clientes por módulo via `scopedClientIdsQuery`. Para `declarations`, já existe `applyDeclarationsSubmoduleScope`. Para `simples_mei`, o `submodule` (PGDASD|PGMEI) altera SQL de situação, categorias de competência e detalhes — mas **não** restringe quais clientes entram na carteira. Front e API já trocam `submodule` corretamente; o scheduler já roteia monitor por `tax_regime`.

## Goals / Non-Goals

**Goals:**

- Escopo de carteira Simples/MEI por família de `clients.tax_regime` alinhado ao `submodule`.
- Uma única aplicação do filtro em `scopedClientIdsQuery` (clients + overview + agregações).
- Manter `office_id` explícito mesmo com `withoutGlobalScopes()`.
- Teste feature com clientes mistos SN/MEI/outro.

**Non-Goals:**

- Alterar tabs/URL/frontend além do comportamento derivado da API.
- Usar `office_fiscal_category_links` como fonte primária de filtro.
- Auto-link de categorias fiscais no cadastro.
- SERPRO live, flags ON, mutações, mei no Compose.
- Refator ampla de tenancy (já fail-closed via `BelongsToOffice` + pin de `office_id`).

## Decisions

### 1. Fonte de verdade = `clients.tax_regime`

- **Decisão:** filtrar com `TaxRegimeCode::SimplesNacional|Mei->storageFilterValues()` (aliases legados inclusos).
- **Por quê:** mesmo critério do `FiscalMonitoringScheduler::simplesMeiSystemServiceForClient`; coluna já no cadastro.
- **Alternativa rejeitada:** filtrar só por `office_fiscal_category_links` — links são seed/manuais e podem divergir do regime.

### 2. Helper espelhando Declarações

- **Decisão:** `applySimplesMeiSubmoduleScope($q, $filters)` chamado quando `$module === FiscalModuleKey::SimplesMei`.
  - `PGDASD` → `whereIn(tax_regime, storageFilterValues(SimplesNacional))`
  - `PGMEI` → `whereIn(tax_regime, storageFilterValues(Mei))`
  - submodule vazio/desconhecido → fail-closed (`whereRaw('1 = 0')`) ou no-op só se o controller já rejeita unknown (preferir confiar em `knownSubmodules` + no-op se null legado não existir no hub).
- **Por quê:** padrão já validado em Declarações; evita duplicar filtro em overview/clients.
- **Nota:** hub Simples/MEI sempre envia submodule (default PGDASD); sem “agregado” intencional nas abas.

### 3. Regimes fora de SN/MEI

- **Decisão:** Lucro Presumido/Real/Imune/Outro/null/unknown **não** aparecem em nenhuma aba.
- **Por quê:** abas são operacionais SN vs MEI; misturar outros regimes reabre o vazamento perceptual.

### 4. Tenancy inalterada

- **Decisão:** manter `withoutGlobalScopes()` + `where('clients.office_id', $office->id)`; não confiar em `office_id` do HTTP.
- **Por quê:** isolamento já sólido; esta change não é sobre vazamento entre offices.

### 5. Front sem mudança

- **Decisão:** zero alteração em `SIMPLES_MEI_TABS` / composables — só a API passa a filtrar.
- **Por quê:** wiring já correto; filtro client-side seria cosmético e inseguro.

## Risks / Trade-offs

- **[Cadastro com tax_regime vazio/legado]** → cliente some das duas abas. Mitigação: `storageFilterValues` cobre aliases; cadastro deve normalizar via `TaxRegimeCode::fromInput`.
- **[Links de categoria vs regime]** → UI de cobertura por categoria pode ainda referenciar categorias SN/MEI; lista de clientes fica correta por regime. Mitigação: aceitável; alinhar links fica fora de escopo.
- **[Regressão de contadores]** → overview deve usar o mesmo scoped query (já usa). Mitigação: assert no teste de overview.
- **[Vazamento entre offices]** → risco residual só se alguém remover o pin `office_id`. Mitigação: teste continua criando clients em office isolado; não abrir PrivilegedOfficeContext em request HTTP.

## Migration Plan

1. Deploy API com filtro + testes.
2. Front já compatível (query param existente).
3. Rollback: reverter o helper/chamada — front continua funcionando (lista volta a misturar).

## Open Questions

- Nenhuma bloqueante. Opcional futuro: auto-sincronizar `office_fiscal_category_links` a partir de `tax_regime`.
