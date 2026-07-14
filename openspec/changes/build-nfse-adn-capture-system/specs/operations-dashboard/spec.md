## ADDED Requirements

### Requirement: Resumo operacional
O sistema SHALL apresentar totais do escritório para clientes ativos, estabelecimentos, documentos, trabalhos pendentes, falhas e credenciais próximas do vencimento.

#### Scenario: Abertura do painel
- **WHEN** um usuário autenticado acessa o painel
- **THEN** o sistema mostra somente métricas agregadas do escritório ativo com horário da última atualização

### Requirement: Saúde por cliente e estabelecimento
O sistema SHALL exibir último sucesso, próximo agendamento, NSU atual, estado do cursor e erro sanitizado de cada estabelecimento.

#### Scenario: Estabelecimento bloqueado
- **WHEN** uma sincronização passa a `BLOCKED`
- **THEN** o painel destaca o estabelecimento, o motivo operacional e a ação permitida ao perfil do usuário

### Requirement: Histórico de sincronizações
O sistema SHALL manter e listar execuções com início, fim, cursor inicial/final, documentos processados, páginas, resultado e número de tentativas.

#### Scenario: Execução sem documentos
- **WHEN** o ADN não entrega documentos novos
- **THEN** o histórico registra sucesso sem documentos e o próximo horário previsto

### Requirement: Trilha de auditoria
O sistema MUST registrar autenticação relevante, alterações de cadastro, gestão de certificados, sincronizações manuais, downloads e exportações com ator, alvo, resultado, horário e IP quando disponível.

#### Scenario: Substituição de certificado
- **WHEN** um administrador ativa uma nova credencial
- **THEN** a auditoria registra os identificadores e fingerprints envolvidos sem registrar senha ou material criptográfico

### Requirement: Logs e métricas sem segredos
O sistema MUST produzir logs estruturados e métricas de fila, atraso, sucesso, falha, 429 e uso de disco sem incluir PFX, senha, chave privada ou XML fiscal.

#### Scenario: Erro remoto do ADN
- **WHEN** uma chamada falha e a resposta contém dados potencialmente sensíveis
- **THEN** o log mantém apenas código, identificador de correlação e mensagem sanitizada

### Requirement: Estado de backup verificável
O sistema SHALL apresentar a data e o resultado do último backup e do último teste de restauração registrado.

#### Scenario: Backup desatualizado
- **WHEN** não existe backup bem-sucedido nas últimas 24 horas
- **THEN** o painel exibe um alerta operacional sem expor a chave mestra
