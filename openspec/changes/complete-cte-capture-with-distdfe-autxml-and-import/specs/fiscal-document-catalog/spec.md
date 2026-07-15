## ADDED Requirements

### Requirement: Proveniência e qualidade do CT-e
O catálogo SHALL expor para CT-e a origem de aquisição (`CTE_DIST_NSU`, `CTE_AUTXML_DIST_NSU`, `MANUAL_XML`, `MANUAL_ZIP` ou `EMITTER_PUSH`) e a qualidade (`ORIGINAL`, `AUTXML_ORIGINAL` ou `AUTXML_REDACTED`) sem confundir origem com papel fiscal. XML em quarentena MUST NOT ser disponibilizado no catálogo comum.

#### Scenario: CT-e recebido pelo tomador
- **WHEN** o documento é capturado no DistDFe do cliente como tomador
- **THEN** o detalhe mostra origem `CTE_DIST_NSU`, qualidade `ORIGINAL` e papel `TAKER`

#### Scenario: CT-e emitido recebido pelo escritório
- **WHEN** o escritório captura como `autXML` uma cópia com referências substituídas por 44 noves
- **THEN** o detalhe mostra `CTE_AUTXML_DIST_NSU`, `AUTXML_REDACTED`, papel `ISSUER` do cliente e aviso textual da limitação

#### Scenario: Original posterior ao derivado
- **WHEN** um XML original do emissor é importado depois de existir cópia `AUTXML_REDACTED`
- **THEN** o original pode tornar-se o canônico baixável, preservando a aquisição derivada e sua auditoria sem apagar bytes

### Requirement: Interesses CT-e múltiplos no catálogo
Listagem, detalhe, filtro, exportação e download SHALL ser autorizados por `document_interests` do estabelecimento e MUST representar todos os papéis CT-e aplicáveis. O sistema MUST NOT armazenar uma única direção global como autoridade quando o mesmo documento pertence a mais de um cliente.

#### Scenario: Filtro por cliente e direção
- **WHEN** o mesmo CT-e tem `ISSUER/OUT` para o cliente A e `TAKER/IN` para o cliente B
- **THEN** o filtro de A por saída e o filtro de B por entrada encontram o mesmo documento sem vazamento entre clientes ou escritórios

#### Scenario: Visão ampla do escritório
- **WHEN** usuário autorizado abre o detalhe sem restringir a um cliente
- **THEN** a API apresenta todos os interesses pertencentes ao próprio `office_id` com cliente, estabelecimento, papel e direção

### Requirement: Cobertura CT-e honesta
O sistema SHALL derivar e expor cobertura CT-e por cliente e período usando estados `CAPTURED_ORIGINAL`, `CAPTURED_AUTXML_REDACTED`, `PENDING_IMPORT`, `HISTORICAL_GAP`, `BLOCKED` e `NO_ACTIVITY`. Ausência de NSU, chave ou XML MUST NOT ser apresentada como prova de inexistência de CT-e.

#### Scenario: Transportadora sem autXML
- **WHEN** o cliente emite CT-e, não configurou o escritório em `autXML` e não entregou XML
- **THEN** o período fica `PENDING_IMPORT` e a interface oferece XML/ZIP ou integração com emissor

#### Scenario: Período sem evidência
- **WHEN** os cursores estão saudáveis, mas nenhum CT-e ou sequência externa comprova atividade
- **THEN** o sistema mostra `NO_ACTIVITY` sem afirmar cobertura fiscal total

#### Scenario: Stream bloqueado por 656
- **WHEN** o circuito do CNPJ-base está aberto por consumo indevido
- **THEN** a cobertura operacional fica `BLOCKED` com próxima ação e horário sanitizados

### Requirement: Download respeita qualidade e canonicidade
O sistema SHALL disponibilizar o melhor artefato canônico autorizado para o interesse solicitado e SHALL informar sua qualidade no cabeçalho/metadado de download. Uma cópia `AUTXML_REDACTED` MUST continuar baixável quando for a única evidência preservada, mas MUST NOT ser rotulada como original exato.

#### Scenario: Somente cópia redigida disponível
- **WHEN** usuário autorizado baixa CT-e cuja única aquisição aceita é `AUTXML_REDACTED`
- **THEN** os bytes oficiais preservados são entregues com metadado e aviso de qualidade, sem reconstrução

#### Scenario: Original e redigido disponíveis
- **WHEN** as duas qualidades existem para a mesma chave
- **THEN** o catálogo oferece o original como canônico e mantém a proveniência do derivado no detalhe

