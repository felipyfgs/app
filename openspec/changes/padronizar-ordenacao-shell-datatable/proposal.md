## Why

Várias planilhas do painel exibem `sortHeader` (ordenação clicável) sem enviar `sort`/`sort_direction` à API ou sem reload — UX enganosa. O contrato canônico da lista de clientes (`ShellDataTable` + `manualSorting` + whitelist) precisa ser aplicado nas demais listas N1.

## What Changes

- Guias: ligar ordenação ao endpoint (`client_id`, `competence`, `due_at`, etc.); remover sort de `id`; sync URL + `watch(sorting)`.
- Registrations e tax-processes: remover `sortHeader` fantasma (API sem sort).
- Installments: desabilitar sort na coluna `situation` sem mapeamento API.
- ByClient (docs): sync URL de `sort` / `sort_direction`.
- Clientes: dropar `cnpj` da whitelist de sort (coluna não existe na grade).
- Empty de listas N1 fora da tabela → slot `#empty` do `ShellDataTable` (syncs, health, serpro/contracts e equivalentes óbvios).
- Testes de contrato `sortHeader` ↔ backend.

## Capabilities

### New Capabilities

- `shell-datatable-sort-contract`: contrato de ordenação e empty do `ShellDataTable` em listas N1 do painel (sortHeader só com API real, manualSorting, URL sync quando houver sort, empty no `#empty`).

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capabilities arquivadas para este contrato)

## Impact

- Frontend: `guides.vue`, `registrations.vue`, `tax-processes.vue`, `installments.vue`, `ByClient.vue`, `ClientCatalogList` / whitelist de sort, `syncs.vue`, `health.vue`, `admin/serpro/contracts.vue`, testes unitários de layout/sort.
- API: sem mudança de contrato HTTP (guias já aceitam sort); sem flags SERPRO/SEFAZ/MEI.
- Non-goals: toolbars/KPIs/filtros salvos; redesign de páginas; `ui-preset` unificado; sort em admin/work sem API; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias / archive (fora do DAG ativo)
- Depende de: nenhuma
- Capability/contrato: `shell-datatable-sort-contract` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação web do contrato de ordenação
- Paralelismo: pode rodar em paralelo com changes que não toquem os mesmos arquivos de monitoring/docs listados
