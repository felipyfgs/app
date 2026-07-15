## MODIFIED Requirements

### Requirement: Consulta paginada e filtrável
O sistema SHALL listar documentos fiscais do catálogo com paginação por cursor e filtros combináveis por tipo (`kind`), cliente, estabelecimento, papel, situação, competência e data de emissão, incluindo documentos capturados de fontes ADN e SEFAZ.

#### Scenario: Filtro por tipo com captura SEFAZ
- **WHEN** o cliente solicita `kind=NFE` e existem NF-e capturadas via DistDFe
- **THEN** o sistema retorna as projeções NFE com `source=SEFAZ`

#### Scenario: Filtro por tipo sem captura
- **WHEN** o cliente solicita um kind ainda sem fonte habilitada
- **THEN** o sistema retorna lista vazia sem erro

### Requirement: Identidade de tipo no catálogo
O sistema SHALL identificar cada item com `kind` entre os tipos comuns (`NFSE`, `NFE`, `NFCE`, `CTE`, `MDFE`) e `source` (`ADN`, `SEFAZ`) quando conhecido.

#### Scenario: Item NF-e serializado
- **WHEN** uma projeção NF-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFE`, `kind_label` legível e `source=SEFAZ`

## ADDED Requirements

### Requirement: Índice de catálogo multi-fonte
O sistema SHALL permitir consulta unificada de documentos de múltiplas fontes (ADN e SEFAZ) na API canônica `/api/v1/documents` sem exigir que o cliente conheça a tabela de projeção interna.

#### Scenario: Mescla de kinds na listagem
- **WHEN** o escritório possui NFS-e e NF-e capturadas
- **THEN** a listagem sem filtro de kind inclui ambas as famílias ordenadas de forma estável (ex.: por id ou issued_at desc)
