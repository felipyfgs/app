## ADDED Requirements

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
O sistema MUST exigir `SEFAZ_MANIFEST_ENABLED` (ou flag equivalente) para enviar ciência; com flag off, download continua só do que já foi capturado no DistDFe.
