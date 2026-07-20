# panel-shell-kit Specification

## Purpose
TBD - created by archiving change shell-ui-kit. Update Purpose after archive.
## Requirements
### Requirement: Kit Shell é o único lugar de anatomia de lista e chrome de página

O sistema SHALL fornecer componentes reutilizáveis sob `apps/web/app/components/shell/` com auto-import Nuxt prefixado `Shell*`. Páginas autenticadas e componentes de domínio MUST NOT montar anatomia própria de lista admin (`UTable` + paginação/per-page/empty de lista) nem chrome de painel (`UDashboardPanel` + navbar canônica) nas superfícies migradas por esta capability; MUST consumir o kit.

#### Scenario: Auto-import pelo path da pasta

- **WHEN** um componente é adicionado em `components/shell/DataTable.vue`
- **THEN** o template pode usá-lo como `<ShellDataTable />` sem import manual

#### Scenario: Página migrada não reinventa tabela

- **WHEN** uma page de lista coberta pelo kit é inspecionada
- **THEN** ela NÃO contém markup direto de `<UTable` nem `UPagination`/`USelect` de per-page para a lista principal
- **AND** usa `ShellDataTable` (ou composição equivalente via shell) para a grade

### Requirement: ShellDataTable padroniza grade offset server-side

O sistema SHALL expor `ShellDataTable` que renderiza `UTable` com presets de `table-ui` (`dashboard` ou `monitoring-compact`), classe de lista `shrink-0`, estado de loading, slot/área de empty, e `ShellTableFooter` com contagem, seletor 10/20/50 por página e `UPagination`. Em viewport `< md`, o componente SHALL apresentar as linhas como cards mobile (composição shell compartilhada, ex. `ShellMobileCards` ou equivalente) usando campos/slots primários configuráveis pela page, de modo a evitar overflow horizontal grave; em `md+` SHALL manter a grade tabular. O footer MUST empilhar ou compactar controles (per-page / paginação) de forma usável em viewport `< sm`. O componente MUST NOT buscar dados de API; a page/domínio fornece `columns`, `data`, `page`, `total`, `itemsPerPage` e reage a `update:page` / `update:itemsPerPage`.

#### Scenario: Troca de linhas por página

- **WHEN** o usuário seleciona outra opção no seletor «N por página»
- **THEN** o componente emite `update:itemsPerPage` com valor em `{10,20,50}`
- **AND** a page responsável recarrega a lista a partir da página 1

#### Scenario: Footer sempre disponível com total

- **WHEN** `total > 0`
- **THEN** o footer exibe a contagem e a paginação conforme props
- **AND** o seletor de per-page está visível no desktop (pode ocultar ou empilhar em viewport estreito)

#### Scenario: Cards mobile no telefone

- **WHEN** `ShellDataTable` renderiza dados com `total > 0` em viewport `< md`
- **THEN** as linhas aparecem como cards (não apenas tabela com `min-w-*` forçando scroll da viewport)
- **AND** ações primárias da linha permanecem acessíveis no card

#### Scenario: Tabela no breakpoint md ou maior

- **WHEN** a mesma instância é vista em viewport `md+`
- **THEN** a grade `UTable` permanece o modo principal de exibição

### Requirement: Chrome de página canônico

O sistema SHALL expor `ShellPagePanel` encapsulando `UDashboardPanel` com slots de header/toolbar/body, e `ShellPageNavbar` com collapse, título e slots de ações. Ações comuns MUST ter `ShellNavbarRefresh` e `ShellNavbarBack` com aria/loading padronizados. Variante settings MUST ter `ShellSettingsShell` (padding/largura via `DashboardContent` + navegação de seção opcional).

#### Scenario: Lista usa PagePanel + Navbar

- **WHEN** uma page de lista migrada é renderizada
- **THEN** o chrome superior é `ShellPagePanel` / `ShellPageNavbar` (ou composição shell equivalente)
- **AND** o botão de atualizar, se presente, é `ShellNavbarRefresh`

### Requirement: Empty e erro de lista tipados

O sistema SHALL expor `ShellListEmpty` para estados empty/filtered/error de lista e `ShellLoadError` para falha de carga com ação «Tentar novamente». Listas migradas MUST usar esses componentes (ou empty slot do `ShellDataTable` alimentado por eles) em vez de `UEmpty`/`UAlert` ad hoc para os mesmos estados.

#### Scenario: Lista vazia sem filtros

- **WHEN** a lista carrega com `total = 0` e sem filtros ativos
- **THEN** o usuário vê empty tipado `empty` com CTA opcional fornecido pela page

#### Scenario: Falha de carga

- **WHEN** a API da lista falha
- **THEN** o usuário vê `ShellLoadError` (ou empty `error`) com ação de retry

### Requirement: ModuleDataTable compõe o kit

O monitoramento fiscal SHALL continuar expondo `ModuleDataTable` / `ModuleTable` para seleção, column visibility e UX de carteira, mas a grade desktop MUST compor `ShellDataTable` (ou o mesmo contrato de footer/per-page/presets) e os cards mobile MUST reutilizar a composição shell de cards (sem markup duplicado divergente), preservando emits públicos `update:page` e `update:perPage`.

#### Scenario: Carteira fiscal paginação

- **WHEN** o usuário altera página ou per-page numa carteira via ModuleTable
- **THEN** o comportamento permanece server-side com opções 10/20/50
- **AND** o default de per-page alinhado ao kit é 20

#### Scenario: Cards mobile alinhados ao shell

- **WHEN** uma carteira fiscal é vista em viewport `< md`
- **THEN** os cards usam a composição shell compartilhada (ou wrapper fino sem divergência de contrato)
- **AND** seleção em massa e ações de linha, quando aplicáveis, permanecem funcionais

### Requirement: Bulk bar e toolbar leve reutilizáveis

O sistema SHALL expor `ShellBulkActionBar` para «N selecionados» + slot de ações, e `ShellFilterToolbarLite` para busca+refresh sem DataTableFilter. Listas com seleção em massa migradas MUST preferir `ShellBulkActionBar` em vez de barras duplicadas. Listas sem chips de filtro MUST poder usar `ShellFilterToolbarLite` ou `ShellListFilterToolbar` existente.

#### Scenario: Seleção em massa visível

- **WHEN** `selectedCount > 0` numa lista com bulk
- **THEN** a barra de bulk shell fica visível com a contagem e as ações do slot

### Requirement: Gate de contrato impede regressão

O sistema SHALL manter teste(s) automatizado(s) que verificam: (1) existência e uso de `ShellDataTable` / `ShellTableFooter` / opções per-page; (2) pages/componentes migrados listados no design NÃO contêm `<UTable` na anatomia da lista principal.

#### Scenario: Teste de layout passa

- **WHEN** a suíte unitária de contrato de lista é executada
- **THEN** os asserts do kit e das superfícies migradas passam

