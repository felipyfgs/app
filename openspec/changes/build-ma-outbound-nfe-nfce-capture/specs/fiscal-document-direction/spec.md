## MODIFIED Requirements

### Requirement: Direção fiscal no catálogo
O sistema SHALL classificar cada documento de catálogo com direção `IN` (entrada), `OUT` (saída) ou `UNKNOWN`, persistida na projeção e exposta na API de documentos. NF-e/NFC-e recuperada por canal MA MUST usar a identidade do emitente validada no XML, nunca parâmetro enviado pelo navegador.

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
- **WHEN** o operador importa um `procNFe` cujo emitente é o CNPJ do estabelecimento do cliente
- **THEN** direction = OUT e kind = NFE para modelo 55 ou NFCE para modelo 65

#### Scenario: NF-e de saída recuperada no MA
- **WHEN** o canal MA valida XML modelo 55 cujo emitente é o estabelecimento configurado
- **THEN** `fiscal_role=ISSUER`, `direction=OUT` e `kind=NFE`

#### Scenario: NFC-e de saída recuperada no MA
- **WHEN** o canal MA valida XML modelo 65 cujo emitente é o estabelecimento configurado
- **THEN** `fiscal_role=ISSUER`, `direction=OUT` e `kind=NFCE`

#### Scenario: Emitente divergente
- **WHEN** o XML recuperado não pertence ao estabelecimento do perfil
- **THEN** o sistema coloca o artefato em quarentena e MUST NOT classificá-lo como saída do cliente

