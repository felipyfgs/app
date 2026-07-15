## ADDED Requirements

### Requirement: Stream autXML do escritório isolado do DistDFe dos clientes
O sistema SHALL operar o canal `NFE_AUTXML_DISTDFE` como um stream próprio do escritório, autenticado exclusivamente com a identidade fiscal e o e-CNPJ A1 do escritório, e SHALL manter separados seus jobs, filas, locks, cursores, elegibilidade e resolução de credencial dos canais `NFE_DISTDFE` executados em nome dos clientes. O sistema MUST resolver a credencial pelo proprietário esperado no backend e MUST NOT aceitar `credential_id`, CNPJ de consulta ou tipo de proprietário fornecido pelo navegador ou pelo payload do job.

#### Scenario: DistDFe de entrada do cliente
- **WHEN** um job `NFE_DISTDFE` é executado para um estabelecimento
- **THEN** ele resolve o A1 ativo da raiz do cliente e o cursor do estabelecimento, sem consultar ou materializar a credencial do escritório

#### Scenario: Distribuição autXML do escritório
- **WHEN** um job `NFE_AUTXML_DISTDFE` é executado
- **THEN** ele resolve o A1 ativo da identidade fiscal do escritório e o cursor central dessa identidade, sem consultar ou materializar qualquer credencial de cliente

#### Scenario: Cliente e escritório possuem a mesma raiz
- **WHEN** a raiz do CNPJ de uma identidade fiscal do escritório também existe em um Cliente do mesmo tenant
- **THEN** os resolvedores continuam usando o proprietário e o canal explícitos e MUST NOT intercambiar objetos de vault, credenciais, cursores ou jobs

#### Scenario: Credencial forjada no payload
- **WHEN** uma requisição ou mensagem de fila informa identificador de credencial pertencente a outro proprietário
- **THEN** o sistema ignora/rejeita o identificador, não materializa o PFX e registra falha sanitizada

### Requirement: Cursor NSU central por CNPJ-base do escritório
O sistema SHALL manter cursor `distNSU` dedicado e iniciado em zero por `office_id`, CNPJ-base da identidade fiscal do escritório, ambiente e canal `NFE_AUTXML_DISTDFE`, conservando também o CNPJ completo canônico usado no pedido. Esse cursor MUST NOT possuir `establishment_id`, MUST NOT compartilhar `last_nsu` com qualquer cliente e SHALL consultar `NFeDistribuicaoDFe` usando como `CNPJ` de interesse o CNPJ completo normalizado e compatível com o certificado. O sistema MUST NOT criar dois cursores ou consumidores independentes para identidades que compartilham o mesmo CNPJ-base.

#### Scenario: Primeira sincronização autXML
- **WHEN** uma identidade fiscal habilitada ainda não possui cursor no ambiente
- **THEN** o sistema cria um único cursor central com `last_nsu=0` e não cria um cursor por cliente autorizado

#### Scenario: Novo cliente adere ao autXML
- **WHEN** outro estabelecimento passa a informar o CNPJ do escritório em `autXML`
- **THEN** ele passa a ser roteado pelo stream central existente e o sistema não reinicia nem bifurca o NSU

#### Scenario: Duas identidades da mesma raiz
- **WHEN** duas identidades fiscais configuradas no escritório possuem o mesmo CNPJ-base
- **THEN** ambas compartilham a mesma sequência/ownership de consumo e não podem abrir cursores independentes

#### Scenario: Concorrência de scheduler e disparo manual
- **WHEN** duas execuções tentam consumir o mesmo cursor autXML
- **THEN** somente uma obtém o lock do CNPJ-base+ambiente+canal e a outra termina sem chamada externa nem avanço

#### Scenario: Canal de cliente bloqueado
- **WHEN** um cursor `NFE_DISTDFE` de cliente está bloqueado
- **THEN** o cursor autXML do escritório preserva estado e agendamento próprios, e o bloqueio não é propagado entre os canais

### Requirement: Persistência atômica e roteamento do lote autXML
O sistema MUST decodificar e persistir de forma atômica todos os itens de uma página autXML antes de avançar o NSU. Para `procNFe` modelo 55, SHALL validar chave, protocolo, emitente e a presença do CNPJ completo do escritório entre todas as ocorrências de `autXML`; SHALL resolver o estabelecimento emitente somente dentro do mesmo `office_id`; e MUST registrar aquisição, NSU e interesse fiscal sem classificar o escritório como emitente, destinatário ou cliente.

#### Scenario: NF-e autXML vinculada
- **WHEN** um `procNFe` modelo 55 válido contém o CNPJ do escritório em `autXML` e o emitente corresponde inequivocamente a estabelecimento ativo do mesmo escritório
- **THEN** os bytes originais são preservados, a aquisição `AUTXML_DIST_NSU` é registrada e o interesse do estabelecimento é `ISSUER`/`OUT`

#### Scenario: CNPJ do escritório ausente da tag
- **WHEN** o lote contém XML íntegro cujo conjunto `autXML` não inclui a identidade fiscal consultada
- **THEN** o sistema preserva o artefato em quarentena, não o publica no catálogo de cliente e gera pendência operacional antes de avançar a página

#### Scenario: Emitente sem vínculo inequívoco
- **WHEN** o XML autXML é válido, mas o CNPJ emitente não corresponde a um único estabelecimento ativo do escritório
- **THEN** o sistema preserva XML e aquisição em quarentena, não cria interesse fiscal nem projeção visível e pode avançar o NSU após persistir a pendência

#### Scenario: Evento de documento já roteado
- **WHEN** um evento recebido no stream autXML referencia chave já vinculada a estabelecimento do mesmo escritório
- **THEN** o evento é ligado ao documento e ao interesse existente sem depender de emitente ausente no XML do evento

#### Scenario: Evento sem documento roteado
- **WHEN** um evento não pode ser ligado por chave a documento previamente autorizado no mesmo escritório
- **THEN** ele permanece em quarentena e MUST NOT criar interesse ou alterar projeção de outro tenant

#### Scenario: Documento modelo 65 no stream
- **WHEN** o stream retorna artefato que afirma ser NFC-e modelo 65
- **THEN** o sistema não o contabiliza como captura autXML, preserva evidência sanitizada para revisão e não amplia implicitamente o canal além de NF-e 55

### Requirement: O canal autXML nunca manifesta como destinatário
O sistema MUST NOT enfileirar ciência da operação, manifestação conclusiva, desbloqueio de XML ou qualquer evento fiscal de destinatário a partir de documento, resumo ou evento recebido pelo canal `NFE_AUTXML_DISTDFE`, pois o escritório atua como terceiro autorizado e não como destinatário da operação.

#### Scenario: Resumo recebido no canal autXML
- **WHEN** um `resNFe` é recebido no stream do escritório
- **THEN** ele não entra na fila de auto-ciência e permanece pendente ou em revisão conforme sua capacidade de vinculação

#### Scenario: Mesmo documento também chega ao destinatário cliente
- **WHEN** a mesma chave é recebida pelo `NFE_DISTDFE` de um cliente destinatário e pelo stream autXML do escritório
- **THEN** somente o fluxo do cliente pode avaliar manifestação, e a aquisição autXML não dispara nem altera essa decisão

### Requirement: Falhas e limites do stream autXML são independentes
O sistema SHALL aplicar ao canal autXML limite de páginas, intervalo mínimo entre chamadas, rate limit por identidade/IP, quiet period e tratamento de `cStat=656` próprios. Falha de Base64/GZip MUST impedir avanço da página; após cinco falhas consecutivas de decodificação o cursor SHALL ficar `BLOCKED`; e erros desse stream MUST NOT alterar cursores ou credenciais de clientes.

#### Scenario: Falha de decodificação
- **WHEN** qualquer `docZip` da página autXML não pode ser decodificado
- **THEN** nenhum NSU da página é confirmado, a falha sanitizada é contabilizada e o lote pode ser reprocessado

#### Scenario: Quinta falha consecutiva
- **WHEN** o cursor autXML alcança cinco falhas consecutivas de decodificação
- **THEN** ele entra em `BLOCKED`, gera item operacional e não bloqueia `NFE_DISTDFE`, CT-e ou demais canais dos clientes

#### Scenario: Consumo indevido 656
- **WHEN** o Ambiente Nacional retorna `cStat=656` ao CNPJ do escritório
- **THEN** o cursor autXML aplica backoff longo/circuit breaker, não avança NSU e registra somente código e motivo sanitizado
