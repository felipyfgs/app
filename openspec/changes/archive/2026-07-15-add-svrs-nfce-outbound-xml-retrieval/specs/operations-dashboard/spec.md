## ADDED Requirements

### Requirement: Saúde operacional do recovery SVRS
O sistema SHALL incluir no resumo e na saúde operacional backlog, idade da pendência mais antiga, capturas, retries, bloqueios, estado do circuit breaker e horário da última captura SVRS, sempre restritos ao escritório ativo.

#### Scenario: Backlog de XML NFC-e
- **WHEN** existem recuperações `QUEUED`, `RUNNING` ou `RETRY_SCHEDULED`
- **THEN** o dashboard mostra contagem e idade agregadas sem expor chave completa ou CNPJ em labels de métrica

### Requirement: Inbox tipada para falhas SVRS
O sistema SHALL gerar itens de inbox distintos para A1 indisponível/não relacionado, contrato do wrapper alterado, autenticação proibida, rate limit persistente, XML/assinatura inválidos, divergência de identidade/bytes, breaker aberto e tentativas esgotadas.

#### Scenario: Contrato alterado
- **WHEN** o parser bloqueia o canal por `RESPONSE_CONTRACT_CHANGED`
- **THEN** a inbox cria item crítico com deep-link ao canal, orientação de fallback e sem HTML remoto

#### Scenario: Tentativas esgotadas
- **WHEN** uma chave fica `NOT_AVAILABLE_VISIBLE`
- **THEN** a inbox cria item acionável para retry elegível ou upload assistido conforme papel

### Requirement: Controles operacionais protegidos
O dashboard SHALL permitir somente a ADMIN com 2FA recente ativar kill switch, resetar breaker ou alterar allowlist. OPERATOR SHALL ver ações de retry/fallback elegíveis e VIEWER MUST ver somente estado.

#### Scenario: Reset do breaker
- **WHEN** ADMIN com 2FA recente confirma reset após corrigir a causa
- **THEN** a auditoria registra ator, motivo e escopo sem registrar certificado, chave fiscal ou resposta remota

### Requirement: Logs e métricas sanitizados do canal SVRS
O sistema MUST registrar métricas por ambiente, resultado, classe HTTP e motivo tipado sem usar CNPJ, chave completa, XML, HTML, PFX, cookie ou senha como label/campo. Logs MUST usar correlação e identificadores internos sanitizados.

#### Scenario: Falha HTTP com página de erro
- **WHEN** a SVRS retorna página de erro contendo dados inesperados
- **THEN** logs e métricas registram apenas classe HTTP, motivo tipado, latência e correlação

