## ADDED Requirements

### Requirement: Registro de execuções de backup da instância
O sistema MUST registrar cada execução de backup da instância com identificador, kind (`full`, `database` ou `vault`), status (`SUCCESS` ou `FAILED`), horários de início e fim, tamanho total quando conhecido, checksum do manifesto quando aplicável e mensagem sanitizada de falha, sem incluir `VAULT_MASTER_KEY`, PFX em claro, senha, PEM ou conteúdo XML fiscal.

#### Scenario: Backup full bem-sucedido
- **WHEN** o comando de backup `full` conclui a cópia do PostgreSQL e do diretório do cofre cifrado
- **THEN** o sistema grava um registro `SUCCESS` com `finished_at` e manifesto referenciando apenas artefatos cifrados ou dump de banco

#### Scenario: Falha parcial
- **WHEN** o dump do banco sucede e a cópia do cofre falha
- **THEN** o registro fica `FAILED`, nenhum status de “backup OK” é exposto como sucesso completo e a mensagem não contém caminhos de chave mestra nem secrets

### Requirement: Backup nunca inclui a chave mestra
O sistema MUST NOT gravar `VAULT_MASTER_KEY`, material de chave mestra ou senhas de certificado em artefatos de backup comuns, manifesto, logs ou respostas de API.

#### Scenario: Inspeção do manifesto
- **WHEN** um operador inspeciona o manifesto JSON do último backup
- **THEN** não há campos de chave mestra, PEM ou senha e os objetos do cofre permanecem no formato cifrado de envelope

### Requirement: Ensaio de restauração registrável
O sistema SHALL permitir um ensaio de restauração (restore drill) que valida a integridade do artefato de backup escolhido e MUST registrar data, status e mensagem sanitizada do drill, sem exigir exposição da chave mestra em CI e sem substituir o procedimento offline de custódia da chave.

#### Scenario: Drill do último SUCCESS
- **WHEN** o operador executa o restore drill apontando para o último backup `SUCCESS`
- **THEN** o sistema valida manifesto e presença dos componentes e grava `last_restore_drill` com status e horário

#### Scenario: Artefato corrompido
- **WHEN** o checksum ou arquivo obrigatório do manifesto não confere
- **THEN** o drill termina `FAILED` e o painel continua podendo mostrar o último drill falho sem marcar o backup original como inexistente

### Requirement: Exposição do estado de backup sem segredos
O sistema SHALL expor a autenticados do escritório da instância o resumo do último backup bem-sucedido, se o backup está atrasado (mais de 24 horas sem `SUCCESS`), se nunca houve `SUCCESS`, e o último restore drill, sem paths internos desnecessários e sem material sensível.

#### Scenario: Backup atrasado
- **WHEN** não existe registro `SUCCESS` com `finished_at` nas últimas 24 horas
- **THEN** o resumo operacional marca backup como `stale` e o painel pode exibir alerta operacional

#### Scenario: Nunca houve backup
- **WHEN** não existe nenhum registro `SUCCESS`
- **THEN** o resumo marca `never` e a severidade do alerta é crítica

### Requirement: Comandos operacionais e exclusão da API de restore
O sistema SHALL executar backup e restore drill via comandos de operação (CLI) com trava contra execução concorrente e SHALL NOT oferecer endpoint HTTP que restaure a base de produção ou devolva artefatos de backup para download anônimo.

#### Scenario: Segunda execução concorrente
- **WHEN** um backup já está em andamento e outro comando tenta iniciar
- **THEN** o segundo comando falha de forma controlada sem corromper o artefato em escrita

#### Scenario: Tentativa de restore pela API web
- **WHEN** um cliente HTTP autentica e procura rota de restore de backup
- **THEN** tal rota não existe no contrato da API da aplicação
