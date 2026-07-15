## ADDED Requirements

### Requirement: Captura CT-e
O sistema SHALL capturar CT-e via CTeDistribuicaoDFe com mTLS A1, cursor próprio, vault e projeção kind=CTE com direction derivada do papel (tomador/remetente/dest→IN; emitente quando aplicável→OUT).

#### Scenario: CT-e de frete de entrada
- **WHEN** o cliente é tomador no CT-e e o canal está habilitado
- **THEN** o documento aparece em /documents?kind=CTE&direction=IN

### Requirement: Captura MDF-e
O sistema SHALL capturar MDF-e via MDFeDistribuicaoDFe quando o CNPJ for ator de interesse, kind=MDFE, direction conforme papel, opt-in por flag.

### Requirement: Independência de NSU
Cursors CT-e e MDF-e MUST ser independentes de NF-e DistDFe e ADN.
