# CT-e Full Capture

## Purpose

Captura CT-e com direção fiscal. MDF-e está fora do escopo escritural.

Captura completa de CT-e com direção fiscal e NSU independente dos demais canais.

## Requirements

### Requirement: Captura CT-e
O sistema SHALL capturar CT-e via CTeDistribuicaoDFe com mTLS A1, cursor próprio, vault e projeção kind=CTE com direction derivada do papel (tomador/remetente/dest→IN; emitente quando aplicável→OUT).

#### Scenario: CT-e de frete de entrada
- **WHEN** o cliente é tomador no CT-e e o canal está habilitado
- **THEN** o documento aparece em /documents?kind=CTE&direction=IN
### Requirement: Independência de NSU
O cursor CT-e MUST ser independente de NF-e DistDFe e ADN.

#### Scenario: Avanço isolado
- **WHEN** o NSU de DistDFe NF-e avança
- **THEN** o cursor CT-e não é alterado
