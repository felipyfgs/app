## Why

No Simples Nacional (PGDAS-D), o popover **Filtro** só oferece Cliente e Competência. Situação fica só nas abas KPI; Enviado/Não enviado aparece só na coluna Envio — não dá para combinar critérios no recurso de filtro como em Clientes/DCTFWeb.

## What Changes

- Expandir o popover Filtro da carteira **Simples Nacional** (`/monitoring/simples-mei`, submodule PGDASD) com:
  - **Situação** (multi, mesma semântica do KPI / API `situation`)
  - **Envio** — Enviado / Não enviado (novo eixo de lista)
- Manter Cliente, Competência e busca `q`.
- KPI de situação continua; chip e KPI compartilham o mesmo estado.
- **Fora de escopo:** carteira MEI (`/monitoring/mei` / submodule PGMEI), categorias, procuração, regime (já implícito no SN).

Non-goals: redesign do shell de filtro; ligar provider de comunicação; filtros MEI; flags SERPRO ON.

## Capabilities

### New Capabilities

- `simples-nacional-portfolio-filters`: contrato do popover Filtro da carteira Simples Nacional (Situação + Envio + Cliente + Competência) e filtro de lista por status de envio agregado.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — main specs vazias)

## Impact

- Web: `Portfolio.vue` (só ramo PGDASD), `monitoring-filters`, `useFiscalModulePortfolio`, tipos, URL/presets, itens de Envio.
- API: `ModulePortfolioFilters` + `ModulePortfolioQueryService` (filtro `send_status` / tracking agregado só para `simples_mei` + PGDASD).
- MEI: sem mudança de `filterConfig` no ramo PGMEI.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: spine Envio/Hist. comunicação; `situation` já no portfolio
- Depende de: nenhuma
- Desbloqueia: nenhuma
- Paralelismo: livre vs MEI
