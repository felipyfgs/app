## MODIFIED Requirements

### Requirement: Catálogo unificado entrada e saída
O sistema SHALL listar documentos de todas as fontes habilitadas (ADN, DistDFe, import) com kind, direction, source e disponibilidade de XML completo, filtráveis por kind e direction.

#### Scenario: Filtro combinação
- **WHEN** kind=NFE e direction=OUT
- **THEN** retorna apenas saídas NF-e (import ou papel emitente)
