## MODIFIED Requirements

### Requirement: Identidade de tipo no catálogo
O sistema SHALL identificar cada item do catálogo com um `kind` de DF-e pertencente ao escopo escritural (`NFSE`, `NFE`, `NFCE`, `CTE`) e um `source` de captura quando conhecido (`ADN`, `SEFAZ`, etc.). O sistema MUST NOT listar MDF-e no catálogo operacional.

#### Scenario: Item NFS-e serializado
- **WHEN** uma projeção NFS-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFSE`, `kind_label` legível e `source=ADN`

#### Scenario: Item NF-e serializado
- **WHEN** uma projeção NF-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFE`, `kind_label` legível e `source=SEFAZ`

#### Scenario: Compatibilidade com filtro MDF-e legado
- **WHEN** o cliente solicita o catálogo com `kind=MDFE`
- **THEN** a API retorna coleção vazia e cursor nulo sem consultar tabela ou projeção MDF-e

## ADDED Requirements

### Requirement: MDF-e fora do escopo escritural
O sistema MUST NOT capturar, sincronizar, listar, detalhar, baixar ou exportar MDF-e e MUST manter qualquer estrutura legada correspondente inerte.

#### Scenario: Catálogo sem MDF-e
- **WHEN** o catálogo é solicitado sem filtro de tipo
- **THEN** nenhum item MDF-e integra a consulta ou a resposta
