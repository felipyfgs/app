## MODIFIED Requirements

### Requirement: Importação de XML de saída
O sistema SHALL permitir que OPERATOR e ADMIN importem um ou mais XML ou ZIP de documentos emitidos, incluindo NF-e 55, NFC-e 65 e CT-e 57, em lote assíncrono, durável e isolado por `office_id`. Cada item válido MUST ter bytes preservados no vault, identidade fiscal validada, aquisição registrada e projeção criada; falha parcial MUST NOT descartar os demais itens válidos.

#### Scenario: Import procNFe
- **WHEN** um ZIP contém `procNFe` bem-formado da NF-e emitida pelo CNPJ de estabelecimento do escritório
- **THEN** o sistema grava documento imutável, projeção NFE com `direction=OUT` e disponibiliza o download

#### Scenario: Import cteProc
- **WHEN** um XML direto ou entrada de ZIP contém `cteProc` modelo 57 autorizado e emitido por estabelecimento do escritório
- **THEN** o sistema grava documento imutável, cria interesse `ISSUER/OUT`, aquisição manual e disponibiliza o XML no catálogo CT-e

#### Scenario: Lote multiempresa e multikind
- **WHEN** o lote contém NF-e, NFC-e e CT-e válidos de diferentes estabelecimentos do mesmo escritório
- **THEN** cada item é associado pelo emitente exato e concluído independentemente sem confiar em `office_id` ou cliente fornecido pelo navegador

#### Scenario: Duplicata
- **WHEN** o mesmo SHA-256 já existe no office
- **THEN** o import reutiliza o documento, registra a nova aquisição e reporta duplicata sem duplicar o vault

#### Scenario: VIEWER
- **WHEN** VIEWER tenta importar
- **THEN** a API responde 403 e nenhum arquivo é processado

### Requirement: Kinds suportados no import MVP
O sistema SHALL aceitar no MVP import de NF-e modelo 55, NFC-e modelo 65 e CT-e modelo 57 processados e autorizados, além de seus eventos protocolados suportados. Payload de CT-e OS/GTV-e ou outro modelo SHALL ser preservado em quarentena tolerante e MUST NOT ser apresentado como projeção completa sem contrato específico.

#### Scenario: NFC-e de saída
- **WHEN** o XML é NFC-e emitida pelo estabelecimento
- **THEN** `kind=NFCE` e `direction=OUT`

#### Scenario: CT-e de saída
- **WHEN** o XML é `cteProc` modelo 57 emitido pelo estabelecimento
- **THEN** `kind=CTE`, papel `ISSUER` e `direction=OUT`

#### Scenario: CT-e OS fora da projeção completa
- **WHEN** o lote contém modelo 67 bem-formado ainda sem capability específico
- **THEN** o sistema preserva o item com estado de revisão e não o mistura silenciosamente ao modelo 57

## ADDED Requirements

### Requirement: Validação fiscal do CT-e importado
Antes de promover `cteProc`, o sistema MUST validar XML bem-formado sem DTD/rede, modelo, chave e DV, `infCte/@Id`, emitente, ambiente, protocolo, cStat de autorização e assinatura digital. O CNPJ completo de `emit` MUST corresponder univocamente a estabelecimento ativo do mesmo `office_id`; seleção de cliente ou presença em `autXML` MUST NOT substituir essa identidade.

#### Scenario: CT-e autorizado e íntegro
- **WHEN** todas as validações fiscais e criptográficas são aprovadas
- **THEN** o documento é promovido como original emitido pelo cliente

#### Scenario: Emitente divergente
- **WHEN** o cliente opcional selecionado não corresponde a `emit/CNPJ`
- **THEN** o item fica `CLIENT_MISMATCH` sem associação por raiz, destinatário ou `autXML`

#### Scenario: Mesma chave com bytes diferentes
- **WHEN** existe documento canônico para a chave e o novo XML possui SHA-256 diferente
- **THEN** o canônico não é substituído e o candidato fica em conflito/quarentena auditável

### Requirement: Entrega autenticada pelo ERP ou emissor
O sistema SHALL expor um contrato autenticado e isolado por escritório para que um ERP/emissor entregue `cteProc` e eventos já autorizados, reutilizando exatamente a ingestão, validação, idempotência e quarentena do batch manual com aquisição `EMITTER_PUSH`. O contrato MUST NOT autorizar, emitir ou registrar eventos na SEFAZ.

#### Scenario: Push válido do emissor
- **WHEN** uma integração autorizada entrega `cteProc` válido de estabelecimento cadastrado no próprio escritório
- **THEN** o sistema ingere o documento como original, registra `EMITTER_PUSH` e responde com identificador durável de processamento

#### Scenario: Tentativa entre escritórios
- **WHEN** a credencial de integração de um escritório envia CT-e cujo emitente pertence apenas a outro `office_id`
- **THEN** o sistema não revela o cadastro alvo, isola o item e retorna resultado sanitizado

#### Scenario: Pedido de emissão
- **WHEN** o integrador tenta usar o contrato para transmitir XML não autorizado ou solicitar autorização
- **THEN** o sistema recusa a operação e não chama Web Service fiscal mutante

