## ADDED Requirements

### Requirement: Superfície Documentos em /docs
O sistema SHALL apresentar o catálogo fiscal na rota canônica `/docs` com a mesma base operacional da antiga tela de notas (lista, filtros, insights, detalhe, export), sob o rótulo de navegação **Documentos**.

#### Scenario: Navegação principal
- **WHEN** o usuário autenticado abre o menu principal
- **THEN** existe o destino Documentos apontando para `/docs`

#### Scenario: Redirect de /notes
- **WHEN** o usuário acessa `/notes` ou `/notes/:accessKey`
- **THEN** é redirecionado para `/docs` ou `/docs/:accessKey` preservando query string quando aplicável

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo de documento (`kind`) e exibir o tipo de cada linha (badge/label). Tipos sem captura implementada MUST mostrar empty state informativo, sem erro.

#### Scenario: Tipo sem captura
- **WHEN** o operador filtra por NF-e (ou outro kind sem dados)
- **THEN** a UI explica que a captura deste tipo ainda não está disponível

#### Scenario: NFS-e com dados
- **WHEN** o operador filtra por NFS-e (ou Todos, com apenas NFS-e populado)
- **THEN** a lista mostra documentos NFS-e com coluna/badge de tipo
