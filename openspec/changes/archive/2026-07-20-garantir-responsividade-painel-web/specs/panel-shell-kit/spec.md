## MODIFIED Requirements

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
