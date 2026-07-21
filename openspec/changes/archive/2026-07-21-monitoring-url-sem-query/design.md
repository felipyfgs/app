## Context

Varredura da codebase: sob `/monitoring/*`, só **dois** pontos escrevem query Nuxt — `useFiscalModulePortfolio.syncUrl` (via `serializeListFilterQuery` + `MONITORING_LIST_QUERY_SCHEMA`) e `guides.vue` (`syncGuidesUrl`). O portfolio é compartilhado por Simples, MEI, DCTFWeb, FGTS, Parcelamentos, Sitfis e Declarações. Já corretos: mailbox (`useServerPage` strip), detalhe de cliente, `monitoringCanonicalQuery`, redirects legados.

Exceção transversal (3 capabilities): contrato novo de URL + sort contract + e2e — mesmo residual. Não fatiar sem specs conflitantes.

## Goals / Non-Goals

**Goals:**

- Toda superfície `/monitoring/*` com URL Nuxt = path canônico.
- Filtros/sort/paginação só em estado local + query params da **API HTTP**.
- Strip de bookmark legado nos dois writers.
- Testes/specs sem exigir sync filtros↔URL Nuxt em monitoramento.

**Non-Goals:**

- Sync de query em `/clients`, `/docs`, `/work`, `/closing`, `/health`.
- Mudar whitelist/contrato de sort na API.
- Middleware global de strip em todo o app.
- Persistir filtros em storage.
- SERPRO live, flags ON, mei no Compose, ops backup/restore.

## Decisions

1. **Um fix no portfolio cobre as 6 carteiras**  
   Remover `hydrateFromQuery` / `useListFilterQuery`; `syncUrl` = strip (`router.replace({ path })` se houver query), espelhando `useServerPage`.  
   Alternativa rejeitada: strip só em `/monitoring/simples` — deixa as outras carteiras inconsistentes.

2. **Guias: segundo writer independente**  
   `syncGuidesUrl` vira strip; defaults locais `due_at` desc. Não passa pelo portfolio.

3. **Sem middleware global**  
   Escopo nos writers conhecidos. Matcher amplo arriscaria rotas fora de monitoramento.

4. **`MONITORING_LIST_QUERY_SCHEMA`**  
   Deixar de usar no portfolio; remover export se ficar órfão, ou manter só se outro consumer aparecer na varredura (hoje: só portfolio).

5. **Fora de monitoramento intacto**  
   `ClientCatalogList` / `ByClient` / work continuam com LFU na query Nuxt.

## Risks / Trade-offs

- **[Perda de deep-link de filtros/sort]** → Aceito; alinhado a tabs locais do hub.
- **[Testes que exigem `serializeListFilterQuery` no portfolio]** → Atualizar no mesmo apply (`simples-nacional-portfolio-e2e`, possivelmente `shell-datatable-sort-contract`).
- **[Race replace]** → Só strip quando há query; path inalterado.

## Migration Plan

1. Portfolio strip + limpeza do schema.
2. Guides strip.
3. Testes de wiring.
4. Gates web + validate OpenSpec.
5. Rollback = revert front-only.

## Open Questions

Nenhuma — inventário de writers fechado na varredura.

## Mapa de dependências

- DAG: C0.
- Ownership: writers listados no proposal + specs da change.
- Marcos: `specs` → `apply` → `verify`.
- Paralelo: ok se não tocar nos mesmos arquivos.
- Rollout/rollback: front-only / revert.
