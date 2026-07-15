## MODIFIED Requirements

### Requirement: Captura CT-e
O sistema SHALL capturar CT-e via `CTeDistribuicaoDFe` com mTLS A1, cursor próprio, vault e projeção `kind=CTE`. A direção SHALL ser derivada do interesse por estabelecimento: `OUT` somente quando o cliente for comprovadamente `ISSUER` por uma origem apta a fornecer o CT-e emitido, e `IN` quando for remetente, destinatário, expedidor, recebedor ou tomador. O canal DistDFe do próprio cliente MUST NOT ser considerado origem do CT-e principal emitido por ele.

#### Scenario: CT-e de frete recebido
- **WHEN** o cliente é tomador no CT-e emitido por outra empresa e o canal está habilitado
- **THEN** o documento aparece em `/documents?kind=CTE&direction=IN`

#### Scenario: CT-e emitido capturado por autXML
- **WHEN** o canal do escritório recebe CT-e cujo emitente é o cliente e o escritório consta em `autXML`
- **THEN** o documento aparece para esse cliente com interesse `ISSUER` e `direction=OUT`, conservando a qualidade da aquisição

#### Scenario: Emitente no próprio DistDFe
- **WHEN** o canal do cliente recebe payload que o identifica como emitente do CT-e principal
- **THEN** o sistema não promove o payload como saída e o envia para revisão

