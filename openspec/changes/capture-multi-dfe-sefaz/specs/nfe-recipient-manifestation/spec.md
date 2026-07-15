## ADDED Requirements

### Requirement: Detectar resumo pendente de manifestação
O sistema SHALL identificar documentos DistDFe do tipo resumo de NF-e (`resNFe`) e projetá-los no catálogo com situação que indique XML completo ainda não disponível até manifestação (quando exigido pela regra SEFAZ).

#### Scenario: Resumo recebido
- **WHEN** um `resNFe` é persistido para a chave
- **THEN** o catálogo lista o documento com kind `NFE` e status/flag de “resumo / pendente de manifestação”

### Requirement: Registrar manifestação do destinatário
O sistema SHALL permitir que usuários com papel OPERATOR ou ADMIN registrem eventos de MD-e via **NFeRecepcaoEvento4** (Ambiente Nacional), com mTLS do A1 da **raiz do destinatário** (nunca certificado do escritório), usando os `tpEvento` oficiais: `210210` (ciência), `210200` (confirmação), `210220` (desconhecimento), `210240` (operação não realizada, com `xJust` 15–255).

#### Scenario: Ciência da operação (210210)
- **WHEN** o operador confirma ciência para uma chave com resumo pendente e ainda dentro do prazo de 10 dias da autorização
- **THEN** o sistema envia o evento 210210, registra auditoria sem segredos e marca a pendência para reconsulta DistDFe assíncrona

#### Scenario: Operação não realizada exige justificativa
- **WHEN** o operador envia 210240 sem xJust válido
- **THEN** o sistema rejeita localmente ou propaga a rejeição SEFAZ (ex. 595) sem corromper estado

#### Scenario: Papel insuficiente
- **WHEN** um VIEWER tenta manifestar
- **THEN** o sistema recusa com 403 e não chama a SEFAZ

#### Scenario: Prazos
- **WHEN** a ciência é tentada após 10 dias da autorização da NF-e
- **THEN** o sistema bloqueia ou exibe risco de rejeição 596; conclusivas respeitam o prazo vigente de 90 dias (Ajuste SINIEF 14/2026)

### Requirement: Obter XML completo após manifestação
O sistema SHALL reconsultar a distribuição (por NSU ou chave, conforme contrato oficial aplicável) após manifestação bem-sucedida e SHALL persistir o `procNFe` quando disponibilizado, sem apagar o resumo original se ainda for útil historicamente.

#### Scenario: procNFe disponível
- **WHEN** a SEFAZ disponibiliza o XML completo após ciência
- **THEN** a projeção passa a refletir a nota completa e o download XML entrega o `procNFe`

### Requirement: Sem exposição de certificado
O sistema MUST NOT retornar PFX, senha, PEM ou chave privada em qualquer resposta de manifestação ou detalhe.

#### Scenario: Resposta de manifestação sem segredos
- **WHEN** OPERATOR registra ciência ou conclusiva com sucesso ou falha
- **THEN** o JSON de resposta e a auditoria não contêm `pfx`, `password`, `pem` nem chave privada
