## ADDED Requirements

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

