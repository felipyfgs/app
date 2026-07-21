## Purpose

Capability `shell-datatable-sort-contract` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Sort header only with real server sort
Em listas N1 do painel que usam `ShellDataTable` / `ModuleDataTable`, o sistema SHALL exibir `sortHeader` somente em colunas cujo `sort` é aceito pela API e recarregado com `manualSorting`. Colunas sem suporte de ordenação no backend MUST usar header texto e `enableSorting: false`.

#### Scenario: Guias ordena via API
- **WHEN** o usuário clica no header ordenável de Cliente, Competência ou Vencimento na lista de Guias
- **THEN** a página envia `sort` e `sort_direction` (ou `direction`) ao endpoint de guias, recarrega a página 1 e MUST NOT alterar a query string da rota Nuxt

#### Scenario: Guias não ordena por id
- **WHEN** a grade de Guias é renderizada
- **THEN** a coluna ID NÃO usa `sortHeader` e NÃO envia `sort=id`

#### Scenario: Registrations e tax-processes sem sort fantasma
- **WHEN** as listas de Cadastro PNR e e-Processo são renderizadas
- **THEN** a coluna Cliente NÃO usa `sortHeader` e a página NÃO promete ordenação sem backend

#### Scenario: Installments situation sem sort
- **WHEN** a grade de Parcelamentos é renderizada
- **THEN** a coluna Situação NÃO usa `sortHeader` (sem mapeamento em `SORT_COLUMN_TO_API`)

### Requirement: URL sync for server-sorted lists
Listas N1 com ordenação no servidor **fora** do hub `/monitoring/*` (incluindo a visão Por cliente em Documentos e a lista de clientes) SHALL sincronizar `sort` e `sort_direction` na query string. Superfícies de monitoramento MUST manter ordenação só em estado local (URL path-only). A whitelist de sort da lista de clientes MUST NÃO incluir `cnpj` enquanto não houver coluna CNPJ ordenável.

#### Scenario: ByClient deep-link de ordenação
- **WHEN** a URL da visão Por cliente contém `sort` e `sort_direction` válidos
- **THEN** a grade aplica essa ordenação no carregamento e a preserva ao recarregar

#### Scenario: Clientes sem sort por cnpj
- **WHEN** a lista de clientes serializa filtros de URL
- **THEN** o parâmetro `sort` NÃO aceita `cnpj` como valor canônico da whitelist da grade

#### Scenario: Monitoramento sem sort na query Nuxt
- **WHEN** o operador ordena uma carteira ou lista sob `/monitoring/*`
- **THEN** a URL Nuxt permanece sem `sort` nem `sort_direction`

### Requirement: Empty state inside ShellDataTable
Listas N1 que montam `ShellDataTable` SHALL renderizar o estado vazio no slot `#empty` (ou props `empty-*` do shell), e NÃO esconder a tabela com um `UEmpty`/`v-if` externo quando a grade já está no fluxo de lista.

#### Scenario: Syncs e health com empty no slot
- **WHEN** syncs ou health não têm linhas
- **THEN** o empty aparece dentro do `ShellDataTable` via `#empty` (tabela permanece montada)

#### Scenario: Contratos SERPRO com empty no slot
- **WHEN** a lista de contratos SERPRO está vazia
- **THEN** o empty aparece no `#empty` do `ShellDataTable`
