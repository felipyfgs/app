# NF-e XML Unlock via Ciência

## Purpose

Obter e persistir `procNFe` via ciência técnica (210210) + reconsulta DistDFe, para **entrega de XML** ao escritório — não como confirmação fiscal da operação.

## Requirements

### Requirement: Desbloqueio de XML completo via ciência técnica
O sistema SHALL permitir obter e persistir o `procNFe` de uma NF-e de destinatário quando apenas o resumo estiver no vault, usando ciência (tpEvento 210210) e reconsulta DistDFe, com o propósito de **entrega de XML** ao escritório — não como confirmação da operação.

#### Scenario: Obter XML completo a partir do resumo
- **WHEN** existe projeção NF-e com resumo e sem procNFe, flag de manifestação habilitada, A1 do cliente ativo, e um OPERATOR solicita desbloqueio (ciência com purpose de unlock)
- **THEN** o sistema envia 210210, enfileira reconsulta e, ao obter o XML completo, o disponibiliza para download/export

#### Scenario: Full já existe
- **WHEN** o procNFe da chave já está no vault
- **THEN** o sistema NÃO envia ciência e o download usa o XML completo existente

#### Scenario: Copy de não-confirmação
- **WHEN** a API/UI expõe a ação de desbloqueio
- **THEN** a descrição deixa claro que ciência não confirma a operação fiscal

### Requirement: Reconsulta sem quebrar captura
O sistema SHALL reconsultar a SEFAZ após ciência sem corromper o cursor DistDFe e sem exceder rate limit (evitar 656).

#### Scenario: Timeout de reconsulta
- **WHEN** o procNFe não aparece após N tentativas
- **THEN** o status indica que o XML completo ainda não está disponível e o resumo permanece; o usuário pode tentar de novo depois

### Requirement: Feature flag
O sistema MUST exigir `SEFAZ_AUTO_CIENCIA_ENABLED` e/ou `SEFAZ_MANIFEST_ENABLED` para enviar ciência (210210); com ambas off, download continua só do que já foi capturado no DistDFe. Conclusivas (210200/210220/210240) MUST exigir `SEFAZ_MANIFEST_ENABLED`.

#### Scenario: Flag desabilitada
- **WHEN** o usuário solicita ciência de unlock com as flags de ciência desligadas
- **THEN** o sistema não envia evento à SEFAZ e preserva os documentos já capturados

### Requirement: Ciência automática em resumo sem full
O sistema SHALL, quando a ciência automática estiver habilitada, enfileirar ciência técnica (210210) após capturar `resNFe` sem `procNFe` correspondente, para desbloquear o XML completo na reconsulta DistDFe. O sistema MUST NOT enviar automaticamente confirmação, desconhecimento ou operação não realizada.

#### Scenario: Resumo capturado sem procNFe
- **WHEN** o DistDFe persiste um resumo de destinatário e ainda não há XML completo da mesma chave
- **THEN** o sistema enfileira job de ciência (com espaçamento de rate limit) e, após aceite SEFAZ, reconsulta por chave sem avançar o NSU do cursor principal

#### Scenario: Full já existe no mesmo lote
- **WHEN** o lote DistDFe já trouxe o procNFe da chave
- **THEN** o sistema NÃO enfileira ciência automática para essa chave
