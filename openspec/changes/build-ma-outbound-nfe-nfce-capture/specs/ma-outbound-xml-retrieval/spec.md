## ADDED Requirements

### Requirement: Fonte oficial MA para NF-e e NFC-e de saída
O sistema SHALL tratar a plataforma oficial da SEFAZ-MA como fonte de recuperação de NF-e modelo 55 e NFC-e modelo 65 de saída por competência. A primeira entrega SHALL aceitar pacote oficial obtido por operador; recuperação automática MUST permanecer desligada até existir contrato máquina-a-máquina documentado ou autorização escrita da SEFAZ-MA.

#### Scenario: Pacote oficial assistido
- **WHEN** OPERATOR ou ADMIN envia pacote de saída produzido pela plataforma SEFAZ-MA para competência e estabelecimento autorizados
- **THEN** o sistema registra a aquisição como `SEFAZ_MA_PORTAL_PACKAGE` e inicia validação sem confundir o pacote com upload genérico

#### Scenario: M2M sem contrato
- **WHEN** não existe contrato oficial configurado para solicitação e download automáticos
- **THEN** `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` permanece ineficaz e o sistema apresenta modo `ASSISTED`, sem prometer captura automática

#### Scenario: Contrato oficial habilitado
- **WHEN** existe contrato M2M aprovado, configuração válida, flag ligada e estabelecimento allowlisted
- **THEN** o adaptador pode solicitar exportação `OUT` por competência/modelo, acompanhar o processamento assíncrono e baixar o pacote pelo contrato autorizado

### Requirement: Validação estrita do pacote e do XML
O sistema MUST aceitar como documento de guarda somente XML original autorizado/protocolado com chave válida, assinatura e protocolo verificáveis, emitente igual ao CNPJ completo do estabelecimento, `cUF=21`, ambiente correto e modelo 55 ou 65. HTML, DANFE, QR Code, resumo, XML remontado e `<NFe>` sem protocolo MUST NOT concluir recuperação.

#### Scenario: NF-e completa
- **WHEN** o pacote contém `procNFe` modelo 55 válido do emitente MA
- **THEN** os bytes originais são elegíveis para vault e projeção `kind=NFE`, `direction=OUT`

#### Scenario: NFC-e completa
- **WHEN** o pacote contém `procNFe` modelo 65 válido do emitente MA
- **THEN** os bytes originais são elegíveis para vault e projeção `kind=NFCE`, `direction=OUT`

#### Scenario: Artefato não fiscal
- **WHEN** uma entrada é DANFE/PDF/HTML, resposta de consulta ou XML sem assinatura/protocolo
- **THEN** o sistema rejeita a entrada como documento de guarda, mantém a recuperação pendente e reporta erro sanitizado

#### Scenario: Chave diferente da solicitada
- **WHEN** o pacote retorna XML cuja chave, emitente, modelo ou ambiente não corresponde à recuperação
- **THEN** o sistema coloca a entrada em quarentena, não conclui o número e gera alerta operacional

### Requirement: Persistência imutável, idempotência e proveniência
O sistema MUST persistir bytes originais e SHA-256 via `SecureObjectStore` antes de concluir a recuperação, SHALL registrar cada aquisição e MUST NOT sobrescrever silenciosamente documento canônico da mesma chave.

#### Scenario: Mesmo SHA-256
- **WHEN** o XML oficial já existe no vault do escritório
- **THEN** o sistema não duplica bytes, registra a nova proveniência MA e conclui a recuperação de forma idempotente

#### Scenario: Mesma chave com bytes divergentes
- **WHEN** chega XML de mesma chave com SHA-256 diferente do documento canônico
- **THEN** o sistema preserva os novos bytes em quarentena, não substitui a projeção e exige revisão

#### Scenario: Falha antes do commit
- **WHEN** vault, validação ou projeção falha antes da transação concluir
- **THEN** a recuperação não passa a `XML_CAPTURED` e pode ser retomada sem perda ou avanço falso

### Requirement: Solicitação assíncrona recuperável
Quando o contrato M2M estiver habilitado, o sistema SHALL persistir solicitação, competência, modelo, direção `OUT`, referência externa, estado, expiração e tentativas antes de polling ou download, e SHALL reconciliar respostas idempotentemente.

#### Scenario: Pacote ainda processando
- **WHEN** a plataforma informa que a solicitação não está pronta
- **THEN** o sistema agenda novo polling dentro do limite do contrato sem bloquear worker ou criar nova solicitação equivalente

#### Scenario: Arquivo disponível
- **WHEN** a solicitação fica pronta dentro do prazo
- **THEN** o sistema baixa uma vez de forma idempotente, valida o pacote e registra o resultado antes da expiração

#### Scenario: Solicitação expirada
- **WHEN** o arquivo ultrapassa a retenção informada, inicialmente sete dias
- **THEN** a solicitação passa a `EXPIRED`, gera item acionável e não é marcada como captura bem-sucedida

### Requirement: Proibição de automação de portal humano
O sistema MUST NOT automatizar telas, reCAPTCHA, Gov.br, SEFAZNET, sessão humana ou portal SVRS; MUST NOT armazenar credencial humana, cookie ou token de navegador para recuperação.

#### Scenario: Contrato exige automação de tela
- **WHEN** a única forma identificada de integração é simular navegador ou contornar desafio humano
- **THEN** o adaptador automático permanece desabilitado e o sistema mantém o fluxo assistido de pacote oficial

#### Scenario: Cookie enviado à API
- **WHEN** uma requisição tenta cadastrar cookie, token Gov.br ou senha SEFAZNET
- **THEN** o sistema rejeita o conteúdo sem persistir ou registrar seu valor

### Requirement: Isolamento, papéis e ausência de segredos
O sistema MUST derivar escritório e estabelecimento do contexto autorizado, SHALL permitir upload de pacote a OPERATOR/ADMIN, SHALL restringir configuração M2M a ADMIN com 2FA recente e MUST NOT expor A1, senha, CSC, material PEM, payload fiscal bruto ou referência de vault nas respostas comuns.

#### Scenario: Pacote de outro escritório
- **WHEN** usuário tenta associar pacote a estabelecimento fora do escritório ativo
- **THEN** o sistema rejeita sem revelar metadados do alvo

#### Scenario: VIEWER envia pacote
- **WHEN** VIEWER tenta criar solicitação ou enviar pacote oficial
- **THEN** recebe 403 e nenhuma aquisição é criada

#### Scenario: Erro externo
- **WHEN** o contrato M2M devolve erro contendo dado sensível
- **THEN** log e API guardam somente código, correlação e mensagem sanitizada

### Requirement: Estado honesto de disponibilidade
O sistema SHALL expor `capture_mode=ASSISTED|AUTOMATIC`, última competência concluída, pendências e motivo de indisponibilidade. Descoberta de chave sem XML, solicitação expirada ou pacote inválido MUST NOT ser apresentada como documento capturado.

#### Scenario: Somente fluxo assistido
- **WHEN** o portal oficial existe, mas não há M2M aprovado
- **THEN** a UI indica modo assistido e oferece ingestão de pacote sem afirmar sincronização automática

#### Scenario: Recuperação pendente
- **WHEN** há chaves descobertas ainda ausentes do pacote
- **THEN** o estado mostra quantidade pendente e competência relacionada, sem criar download vazio

