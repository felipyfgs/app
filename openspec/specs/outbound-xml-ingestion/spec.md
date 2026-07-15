# Outbound XML Ingestion

## Purpose

Importação de XML de saídas (NF-e/NFC-e e afins) para vault e projeção no catálogo, sem emissão SEFAZ.
## Requirements
### Requirement: Importação de XML de saída
O sistema SHALL permitir que OPERATOR/ADMIN importe um ou mais XML (ou ZIP de XML) de documentos emitidos (saídas), persistindo bytes no vault e projeção no catálogo com direction OUT.

#### Scenario: Import procNFe
- **WHEN** um ZIP contém procNFe bem-formado da NF-e emitida pelo CNPJ do cliente
- **THEN** o sistema grava dfe_document imutável, projeção NFE com direction OUT e disponibiliza download

#### Scenario: Duplicata
- **WHEN** o mesmo SHA-256 já existe no office
- **THEN** o import é idempotente (não duplica vault; reporta skipped/duplicate)

#### Scenario: VIEWER
- **WHEN** VIEWER tenta importar
- **THEN** 403

### Requirement: Kinds suportados no import MVP
O sistema SHALL aceitar no MVP import de NF-e (55) e NFC-e (65); MAY aceitar outros kinds conhecidos com parse tolerante.

#### Scenario: NFC-e de saída
- **WHEN** o XML é NFC-e emitida pelo estabelecimento
- **THEN** kind=NFCE, direction=OUT

### Requirement: Sem emissão
O sistema MUST NOT autorizar ou transmitir nota à SEFAZ no fluxo de import — apenas armazena XML já autorizado.

#### Scenario: Import não chama SEFAZ
- **WHEN** o import conclui com sucesso
- **THEN** nenhuma chamada a web service de autorização/emissão SEFAZ é efetuada

### Requirement: Ingestão automática proveniente da SVRS
O sistema SHALL aceitar bytes de `nfeProc` validados pelo canal `SVRS_NFCE_DOWNLOAD_XML_DFE` na mesma persistência imutável e projeção de saída do import, registrando aquisição e origem automáticas sem atribuir upload humano.

#### Scenario: Recovery SVRS válido
- **WHEN** o adapter entrega bytes validados de NFC-e 65 emitida pelo estabelecimento
- **THEN** o sistema grava documento imutável, `kind=NFCE`, `direction=OUT`, aquisição SVRS e disponibiliza o XML conforme as policies existentes

#### Scenario: Documento já importado
- **WHEN** a mesma chave e SHA-256 já existem por upload anterior
- **THEN** o sistema não duplica o objeto/documento, adiciona ou reconcilia a proveniência e conclui a pendência

### Requirement: Divergência de bytes por chave
O sistema MUST NOT substituir silenciosamente um documento canônico quando a mesma chave chega da SVRS com SHA-256 diferente. Os bytes divergentes SHALL permanecer sob custódia/quarentena e gerar revisão operacional.

#### Scenario: Mesma chave com hash diferente
- **WHEN** o download SVRS válido possui chave já existente com bytes diferentes
- **THEN** o canônico permanece inalterado, a divergência é registrada e a recuperação fica bloqueada para revisão

### Requirement: Ingestão automática sem emissão
O fluxo automático da SVRS MUST NOT chamar autorização, inutilização, cancelamento ou recepção de evento da SEFAZ; ele SHALL realizar somente GET/POST de recuperação do XML já autorizado.

#### Scenario: Captura automática concluída
- **WHEN** uma recuperação SVRS é ingerida com sucesso
- **THEN** nenhuma operação fiscal mutante é executada

### Requirement: Satisfação do prazo por qualquer fonte válida
Uma pendência SHALL ser considerada capturada no prazo somente após ingestão canônica completa por vault, emissão/importação, `autXML`, XML/ZIP, pacote oficial ou SVRS. A aquisição SHALL registrar fonte, `captured_at`, `due_at` e resultado de prazo, preservando bytes imutáveis.

#### Scenario: Upload conclui antes do portal
- **WHEN** XML/ZIP válido satisfaz uma chave com slot SVRS ainda não iniciado
- **THEN** o slot é cancelado, a fonte upload é registrada e o documento conta como capturado no prazo

### Requirement: Divergência não conta como completude
Documento com chave, identidade, protocolo, digest, assinatura ou hash em divergência MUST permanecer fora da contagem concluída até revisão segura e MUST NOT substituir o canônico.

#### Scenario: Hash divergente perto do prazo
- **WHEN** uma segunda fonte apresenta bytes divergentes para a mesma chave
- **THEN** o sistema abre revisão crítica e não mascara o risco de prazo como captura concluída

