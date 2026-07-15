## MODIFIED Requirements

### Requirement: Direção fiscal no catálogo
O sistema SHALL classificar cada interesse de documento com direção `IN`, `OUT` ou `UNKNOWN`, persistida por estabelecimento e exposta na API. Para CT-e, somente o papel comprovado `ISSUER` SHALL produzir `OUT`; remetente, destinatário, expedidor, recebedor e tomador SHALL produzir `IN`. Identidade e direção MUST ser derivadas do XML validado e da relação persistida do servidor, nunca de parâmetro enviado pelo navegador.

#### Scenario: NFS-e prestador
- **WHEN** a projeção NFS-e tem fiscal_role ISSUER
- **THEN** direction = OUT

#### Scenario: NFS-e tomador
- **WHEN** fiscal_role é TAKER
- **THEN** direction = IN

#### Scenario: NF-e DistDFe de interesse
- **WHEN** a NF-e foi capturada via DistDFe como documento de interesse do estabelecimento e não como emissão própria
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

#### Scenario: CT-e emitido pelo cliente
- **WHEN** `emit/CNPJ` corresponde ao estabelecimento e o CT-e original ou derivado autorizado foi obtido por import, `EMITTER_PUSH` ou `CTE_AUTXML_DISTDFE`
- **THEN** o interesse do estabelecimento é `ISSUER/OUT`

#### Scenario: CT-e relacionado ao cliente
- **WHEN** outro CNPJ emitiu o CT-e e o estabelecimento aparece como remetente, destinatário, expedidor, recebedor ou tomador
- **THEN** o interesse correspondente possui `direction=IN`, mesmo que a mercadoria associada seja uma saída comercial

#### Scenario: Emitente divergente
- **WHEN** o XML recuperado não pertence ao estabelecimento do perfil
- **THEN** o sistema coloca o artefato em quarentena e MUST NOT classificá-lo como saída do cliente

## ADDED Requirements

### Requirement: Papéis CT-e preservados por estabelecimento
O sistema MUST preservar separadamente os papéis CT-e `ISSUER`, `SENDER`, `RECIPIENT`, `EXPEDITOR`, `RECEIVER` e `TAKER` por estabelecimento. Um mesmo documento SHALL poder ter múltiplos interesses e direções para diferentes clientes do mesmo escritório sem duplicar o objeto fiscal.

#### Scenario: Dois clientes no mesmo CT-e
- **WHEN** um cliente do escritório é emitente e outro é destinatário do mesmo CT-e
- **THEN** o sistema conserva `ISSUER/OUT` para o primeiro e `RECIPIENT/IN` para o segundo sobre um documento canônico

#### Scenario: Mesmo cliente em dois papéis não emitentes
- **WHEN** o mesmo estabelecimento aparece validamente como expedidor e tomador
- **THEN** os dois papéis ficam registrados, enquanto a direção deduplicada para filtro permanece `IN`

#### Scenario: Nenhum papel comprovado
- **WHEN** nenhuma identidade do XML corresponde exatamente ao estabelecimento
- **THEN** o sistema não usa papel padrão, mantém `UNKNOWN` ou quarentena e exige revisão

