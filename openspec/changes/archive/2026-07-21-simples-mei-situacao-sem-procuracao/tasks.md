## 1. N0 — API portfolio

- [x] 1.1 Em `detailSimplesMei`, carregar projeção de procuração em lote e setar `procuracao_status` no detail

## 2. N1 — UI Situação

- [x] 2.1 Tipos: `procuracao_status` em `SimplesMeiClientDetail`
- [x] 2.2 `buildPgdasdColumns` / `buildPgmeiColumns`: se `missing`, badge Sem procuração
  - Depende de: 2.1

## 3. N2 — Gates

- [x] 3.1 Teste API ou unitário da projeção no portfolio + lint/typecheck da área web tocada
  - Depende de: 1.1, 2.2
- [x] 3.2 `openspec validate simples-mei-situacao-sem-procuracao --strict`
  - Depende de: 3.1
