## MODIFIED Requirements

### Requirement: Direção fiscal no catálogo
O sistema SHALL classificar a direção fiscal como `IN`, `OUT` ou `UNKNOWN` no nível do interesse de cada estabelecimento e SHALL persistir/serializar essa direção no contexto consultado. Um documento canônico MAY possuir simultaneamente interesses `IN` e `OUT` para estabelecimentos diferentes do mesmo escritório; o sistema MUST NOT manter um único campo global de direção capaz de sobrescrever um desses interesses. Na visão sem estabelecimento específico, a API SHALL expor as direções/interesses aplicáveis sem duplicar os bytes ou inventar uma direção predominante.

#### Scenario: NFS-e prestador
- **WHEN** a projeção NFS-e tem interesse do estabelecimento com `fiscal_role=ISSUER`
- **THEN** a direção daquele interesse é `OUT`

#### Scenario: NFS-e tomador
- **WHEN** o interesse do estabelecimento tem `fiscal_role=TAKER`
- **THEN** a direção daquele interesse é `IN`

#### Scenario: NF-e DistDFe de interesse
- **WHEN** uma NF-e é capturada via DistDFe para estabelecimento destinatário que não é o emitente
- **THEN** o interesse desse estabelecimento é `TAKER`/`IN`

#### Scenario: XML importado como saída
- **WHEN** o operador importa `procNFe` modelo 55 ou 65 cujo emitente corresponde a estabelecimento do escritório
- **THEN** o interesse do emitente é `ISSUER`/`OUT` e o kind é `NFE` para modelo 55 ou `NFCE` para modelo 65

#### Scenario: NF-e capturada por autXML
- **WHEN** `procNFe` modelo 55 recebido por `AUTXML_DIST_NSU` tem emitente correspondente a estabelecimento do escritório
- **THEN** o interesse do emitente é `ISSUER`/`OUT`, ainda que o certificado usado na aquisição pertença ao escritório

#### Scenario: Mesma NF-e entre dois clientes
- **WHEN** o emitente e o destinatário da mesma chave pertencem a estabelecimentos A e B do escritório
- **THEN** o documento possui interesse A `ISSUER`/`OUT` e interesse B `TAKER`/`IN`, sem que a chegada posterior por outra fonte altere qualquer direção

#### Scenario: XML sem estabelecimento emitente vinculado
- **WHEN** um XML de saída não pode ser associado inequivocamente ao emitente no escritório
- **THEN** ele permanece em quarentena e MUST NOT criar direção `OUT` ou `UNKNOWN` no catálogo comum

### Requirement: Filtro por direção
O sistema SHALL permitir filtrar `GET /documents` por `direction=IN|OUT|UNKNOWN` aplicando o filtro aos interesses visíveis no escopo de cliente/estabelecimento informado ou, na visão ampla do escritório, à existência de ao menos um interesse correspondente. O resultado MUST manter uma única linha por documento na mesma página, mesmo quando houver múltiplas aquisições ou interesses que satisfaçam o filtro.

#### Scenario: Só saídas de um cliente
- **WHEN** a query inclui `client_id` e `direction=OUT`
- **THEN** a lista inclui apenas documentos em que estabelecimento daquele cliente possui interesse `ISSUER`/`OUT`

#### Scenario: Só entradas de um cliente
- **WHEN** a query inclui `client_id` e `direction=IN`
- **THEN** a lista inclui apenas documentos em que estabelecimento daquele cliente possui interesse de entrada, sem herdar a saída de outro cliente da mesma chave

#### Scenario: Filtros amplos em momentos diferentes
- **WHEN** um documento possui um interesse `IN` e outro `OUT` no escritório
- **THEN** ele pode satisfazer tanto uma consulta ampla `direction=IN` quanto outra `direction=OUT`, mas aparece uma única vez em cada resultado

#### Scenario: Direção desconhecida
- **WHEN** existe documento catalogável cujo papel fiscal ainda não pode ser derivado para um estabelecimento autorizado
- **THEN** somente o interesse explicitamente marcado `UNKNOWN` satisfaz o filtro correspondente; item apenas em quarentena não aparece

