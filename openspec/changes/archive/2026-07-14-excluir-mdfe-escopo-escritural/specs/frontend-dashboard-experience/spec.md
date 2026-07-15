## MODIFIED Requirements

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo e exibir o tipo de cada linha somente para `NFSE`, `NFE`, `NFCE` e `CTE`. Tipos com captura habilitada MUST listar dados reais; tipos sem captura MUST manter empty state informativo. MDF-e MUST NOT aparecer como opção operacional.

#### Scenario: Tipo sem captura
- **WHEN** o operador filtra por kind sem captura habilitada ou sem dados
- **THEN** a UI explica a indisponibilidade sem erro

#### Scenario: NFS-e com dados
- **WHEN** o operador filtra por NFS-e (ou Todos, com apenas NFS-e populado)
- **THEN** a lista mostra documentos NFS-e com coluna ou badge de tipo

#### Scenario: NF-e com captura
- **WHEN** a captura DistDFe está habilitada e há documentos
- **THEN** o filtro NF-e mostra linhas com badge NFE e não exibe “em breve” como único estado

#### Scenario: MDF-e ausente
- **WHEN** o operador abre os filtros e estados do catálogo
- **THEN** MDF-e não é apresentado como opção disponível ou futura

### Requirement: Sincronizações multi-canal
O sistema SHALL apresentar status de cursors SEFAZ DistDFe e CT-e nas telas de sincronização e saúde de forma distinguível dos cursors ADN, sem apresentar canal MDF-e.

#### Scenario: Cursor DistDFe bloqueado
- **WHEN** o cursor DistDFe está BLOCKED
- **THEN** a UI de sync ou health mostra o canal e severidade sem dump SOAP bruto

#### Scenario: Superfície operacional sem MDF-e
- **WHEN** o usuário abre sincronização ou saúde
- **THEN** não existe filtro, status ou ação para MDF-e
