## ADDED Requirements

### Requirement: Jobs internos autenticados
O microserviço SHALL aceitar criação, consulta, retomada, cancelamento e download de artefatos somente com assinatura HMAC válida e proteção contra replay.

#### Scenario: Assinatura válida
- **WHEN** o Laravel envia corpo, timestamp e nonce válidos com assinatura canônica
- **THEN** o microserviço aceita a chamada e registra apenas identificadores opacos

#### Scenario: Replay de nonce
- **WHEN** uma assinatura reutiliza nonce ainda presente na janela de replay
- **THEN** o microserviço responde `401` sem criar ou alterar job

### Requirement: Ciclo assíncrono e idempotente
O microserviço SHALL representar jobs pelos estados `QUEUED`, `RUNNING`, `WAITING_USER_ACTION`, `SUCCEEDED`, `FAILED`, `CANCELLED` ou `UNCERTAIN` e SHALL reutilizar o job existente quando receber a mesma chave de idempotência e fingerprint.

#### Scenario: Reenvio idempotente
- **WHEN** o Laravel repete a criação com a mesma chave e o mesmo fingerprint
- **THEN** a API retorna o job original sem nova execução

#### Scenario: Colisão de idempotência
- **WHEN** a mesma chave é enviada com fingerprint diferente
- **THEN** a API responde conflito e não executa o payload novo

### Requirement: Isolamento e retenção mínima
O worker SHALL usar contexto de navegador não persistente por job, fechar recursos em qualquer resultado e SHALL manter resultados e artefatos apenas pelo TTL configurado.

#### Scenario: Finalização com erro
- **WHEN** a execução levanta timeout ou exceção de parser
- **THEN** browser context e arquivos temporários são removidos e o erro público é redigido

### Requirement: Serviço interno observável
O microserviço SHALL expor liveness/readiness, métricas de jobs sem PII e SHALL operar sem credenciais de banco ou vault.

#### Scenario: Dependência Redis indisponível
- **WHEN** a API está viva mas não alcança Redis
- **THEN** liveness permanece saudável, readiness falha e novos jobs não são aceitos
