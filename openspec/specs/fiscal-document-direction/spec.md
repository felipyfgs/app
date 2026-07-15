# Fiscal Document Direction

## Purpose

Classificação e filtro de direção fiscal (IN/OUT/UNKNOWN) no catálogo de documentos do escritório.

## Requirements

### Requirement: Direção fiscal no catálogo
O sistema SHALL classificar cada documento de catálogo com direção `IN` (entrada), `OUT` (saída) ou `UNKNOWN`, persistida na projeção e exposta na API de documentos.

#### Scenario: NFS-e prestador
- **WHEN** a projeção NFS-e tem fiscal_role ISSUER
- **THEN** direction = OUT

#### Scenario: NFS-e tomador
- **WHEN** fiscal_role é TAKER
- **THEN** direction = IN

#### Scenario: NF-e DistDFe de interesse
- **WHEN** a NF-e foi capturada via DistDFe como documento de interesse do estabelecimento (não emissão própria)
- **THEN** direction = IN

#### Scenario: XML importado como saída
- **WHEN** o operador importa um procNFe cujo emitente é o CNPJ do estabelecimento do cliente
- **THEN** direction = OUT e kind = NFE

### Requirement: Filtro por direção
O sistema SHALL permitir filtrar a listagem `GET /documents` por `direction=IN|OUT|UNKNOWN`.

#### Scenario: Só saídas
- **WHEN** a query inclui direction=OUT
- **THEN** a lista não inclui itens classificados como IN
