# ADN Document Sync

## Purpose

Captura de documentos NFS-e via API oficial do ADN (mTLS, cursor por estabelecimento/NSU, persistência atômica e sincronização horária).

## Requirements

### Requirement: Comunicação oficial por mTLS
O sistema MUST consultar a API de contribuintes do ADN por mTLS usando o A1 da mesma raiz do CNPJ consultado, TLS 1.2 ou superior e validação de hostname e cadeia habilitadas.

#### Scenario: Consulta de filial
- **WHEN** um estabelecimento usa o certificado ativo de sua raiz
- **THEN** o cliente envia o CNPJ completo em `cnpjConsulta` e o PFX somente em memória

#### Scenario: Falha de verificação TLS
- **WHEN** a cadeia ou o hostname do servidor não pode ser validado
- **THEN** o sistema encerra a chamada e não aceita a resposta

### Requirement: Cursor independente por estabelecimento
O sistema SHALL manter um último NSU por estabelecimento e ambiente, iniciando em zero e consultando lotes até alcançar o fim da distribuição disponível.

#### Scenario: Primeira sincronização
- **WHEN** um estabelecimento ainda não possui histórico de sincronização
- **THEN** a primeira consulta usa NSU zero e processa o histórico fornecido pelo ADN

#### Scenario: Nenhum documento novo
- **WHEN** o ADN responde `NENHUM_DOCUMENTO_LOCALIZADO`
- **THEN** o sistema preserva o cursor e agenda a próxima execução para uma hora depois

### Requirement: Persistência atômica e idempotente de página
O sistema MUST persistir todos os documentos válidos da página antes de avançar o cursor e MUST tolerar o reprocessamento da mesma página sem duplicação.

#### Scenario: Falha parcial de banco
- **WHEN** qualquer persistência da página falha antes do commit
- **THEN** o cursor não avança e a página inteira pode ser processada novamente

#### Scenario: Documento já persistido
- **WHEN** uma repetição contém o mesmo estabelecimento, NSU e documento
- **THEN** o sistema mantém uma única associação e conclui a página sem duplicar o XML

### Requirement: Decodificação dos documentos distribuídos
O sistema SHALL decodificar o Base64 e o GZip de cada item e SHALL preservar os bytes XML resultantes sem normalização.

#### Scenario: Payload corrompido
- **WHEN** um item não pode ser decodificado como Base64/GZip
- **THEN** o sistema não avança o cursor, registra uma falha sanitizada e agenda uma tentativa

### Requirement: Processamento justo e limitado
O sistema SHALL limitar cada job a 20 páginas, impedir duas execuções simultâneas do mesmo estabelecimento e aplicar limites globais configuráveis de concorrência e taxa.

#### Scenario: Cliente com grande backfill
- **WHEN** um job conclui sua vigésima página e ainda existem documentos
- **THEN** o sistema atualiza o progresso e reenfileira o estabelecimento após os demais trabalhos elegíveis

### Requirement: Tratamento explícito de falhas
O sistema SHALL aplicar backoff exponencial com jitter a 429 e 5xx e SHALL bloquear o cursor em erros permanentes ou após cinco falhas consecutivas de decodificação.

#### Scenario: Limite de requisições
- **WHEN** o ADN responde HTTP 429
- **THEN** o sistema preserva o cursor e reagenda a chamada respeitando o atraso calculado

#### Scenario: Quinta falha de decodificação
- **WHEN** o mesmo cursor acumula cinco falhas consecutivas de decodificação
- **THEN** o estabelecimento passa a `BLOCKED` e requer intervenção sem pular o NSU

### Requirement: Sincronização horária distribuída
O sistema SHALL selecionar cursores vencidos a cada minuto e distribuir deterministicamente suas execuções ao longo de uma janela máxima de uma hora.

#### Scenario: Mais de mil estabelecimentos vencidos
- **WHEN** começa um novo ciclo horário
- **THEN** o Scheduler escalona todos os estabelecimentos elegíveis sem dispará-los simultaneamente
