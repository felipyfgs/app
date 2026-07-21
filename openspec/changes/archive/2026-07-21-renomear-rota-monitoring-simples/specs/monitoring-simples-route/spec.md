## ADDED Requirements

### Requirement: Rota canônica Simples Nacional

A superfície de monitoramento do Simples Nacional (PGDAS-D) SHALL ser servida em `/monitoring/simples`. A navegação do painel MUST apontar esse path para o item “Simples Nacional”. O path `/monitoring/mei` MUST permanecer exclusivo da superfície MEI (PGMEI).

#### Scenario: Abrir Simples Nacional pela sidebar
- **WHEN** o operador navega para o item Simples Nacional
- **THEN** a URL canônica SHALL ser `/monitoring/simples`
- **AND** a página renderiza a carteira PGDASD

### Requirement: Redirects legados simples-mei

Rotas legadas `/monitoring/simples-mei` e `/monitoring/simples-mei/:submodule` SHALL redirecionar (replace) para a superfície canônica correspondente. Segmento PGMEI MUST ir para `/monitoring/mei`; demais casos MUST ir para `/monitoring/simples`.

#### Scenario: Bookmark antigo da carteira
- **WHEN** o operador abre `/monitoring/simples-mei`
- **THEN** o sistema redireciona para `/monitoring/simples`

#### Scenario: Path legado PGMEI
- **WHEN** o operador abre `/monitoring/simples-mei/pgmei`
- **THEN** o sistema redireciona para `/monitoring/mei`
