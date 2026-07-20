# listas-padrao-clients Specification

## Purpose
TBD - created by archiving change alinhar-listas-padrao-clients. Update Purpose after archive.
## Requirements
### Requirement: Padrão ouro de lista é /clients

O sistema SHALL tratar a anatomia de lista de `/clients` (`ClientCatalogList` + `ShellDataTable` com `ui-preset="monitoring-compact"`, per-page padrão 20 nas opções 10/20/50, toolbar shell, footer com `mt-auto`) como referência obrigatória para listas admin offset do painel. Domínio específico de clientes (KPIs A1, bulk categorias, credencial) MUST NOT ser exigido em outras listas.

#### Scenario: Anatomia mínima de lista offset

- **WHEN** uma page de lista offset coberta por esta capability é renderizada no desktop
- **THEN** o corpo contém `ShellDataTable` (não `<UTable`)
- **AND** o footer de paginação/per-page segue o contrato do kit Shell (contagem + seletor + `UPagination`)
- **AND** filtros/busca usam `ShellListFilterToolbar` ou `ShellFilterToolbarLite` quando houver toolbar

### Requirement: Inventário A alinhado ao ouro

As superfícies do inventário A (exports, closing, docs/imports, work/processes, work/templates, admin/offices, syncs, health, carteiras via ModuleTable, clients) MUST permanecer sem `<UTable` na grade principal e MUST usar densidade coerente: `monitoring-compact` para listas operacionais densas; reporting pode usar `dashboard` se documentado.

#### Scenario: Lista A sem UTable

- **WHEN** o gate de migração inspeciona uma superfície do inventário A
- **THEN** o fonte contém `ShellDataTable` (direto ou via ModuleDataTable/ClientCatalogList)
- **AND** não contém markup `<UTable`

### Requirement: Inventário B migrado para ShellDataTable

As superfícies do inventário B MUST migrar a grade de `<UTable` para `ShellDataTable`: `docs/Catalog`, `docs/ByClient`, `ClientListDashboard`, `admin/serpro/catalog`, `admin/serpro/contracts`, `admin/serpro/usage`, `settings/usage` (e reexport `/conta/consumo`), e tabelas de seção em `monitoring/clients/[clientId]`.

#### Scenario: Docs Catalog sem UTable

- **WHEN** o componente `docs/Catalog.vue` é inspecionado após a migração
- **THEN** a grade principal usa `ShellDataTable`
- **AND** não monta `<UTable`

#### Scenario: Feed cursor sem fingir offset

- **WHEN** a lista é cursor/load-more (ex.: docs Catalog, syncs, health)
- **THEN** o sistema NÃO exige seletor 10/20/50 de offset
- **AND** pode ocultar paginação offset (`show-footer=false` ou equivalente) mantendo a grade Shell

### Requirement: Chrome canônico nas listas cobertas

Listas autenticadas cobertas por W2 MUST usar `ShellPagePanel` e `ShellPageNavbar` (com `ShellNavbarRefresh` / `ShellNavbarBack` quando a ação existir), em vez de montar `UDashboardNavbar` + collapse ad hoc na page.

#### Scenario: Page lista com ShellPagePanel

- **WHEN** uma page de lista coberta por W2 é aberta
- **THEN** o painel raiz é `ShellPagePanel` (ou ModuleTable já encapsula painel equivalente documentado)
- **AND** o título/ações passam por `ShellPageNavbar` ou slot equivalente do shell

### Requirement: Gate impede regressão do catálogo

O sistema SHALL manter teste(s) automatizado(s) listando as superfícies A+B migradas e falhando se reaparecer `<UTable` ou sumir `ShellDataTable` na grade principal.

#### Scenario: Gate verde

- **WHEN** a suíte unitária do gate de listas é executada
- **THEN** todas as superfícies catalogadas nesta capability passam nos asserts

