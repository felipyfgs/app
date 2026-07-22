## ADDED Requirements

### Requirement: Gateway Go isola o protocolo WhatsApp
O sistema SHALL executar um gateway interno em Go/WhatsMeow separado do Laravel. O gateway SHALL gerenciar pairing, device store, conexão, envio, receipts e download de mídia, mas MUST NOT possuir clientes fiscais, permissões, templates, conversas ou histórico de negócio.

#### Scenario: Sessão é pareada
- **WHEN** Laravel envia comando autorizado de pairing para sessão provisionada
- **THEN** o gateway produz QR ou código efêmero e reporta mudanças até `CONNECTED` ou timeout

#### Scenario: Gateway reinicia
- **WHEN** o processo reinicia com device store válido
- **THEN** a sessão é retomada sem exigir novo pairing e sem criar outra inbox

#### Scenario: Logout confirmado
- **WHEN** Laravel envia comando explícito de logout
- **THEN** o gateway encerra conexão, revoga credenciais locais e emite estado `REVOKED`

### Requirement: Comandos são duráveis e idempotentes
O gateway SHALL persistir cada comando antes de responder HTTP 202 e MUST impor unicidade de `command_id`. Envio SHALL usar o `provider_message_id` fornecido pelo Laravel; retry do mesmo comando MUST NOT produzir nova identidade remota. Resultado ambíguo SHALL ser informado como `UNKNOWN` em vez de sucesso inventado.

#### Scenario: Mesmo comando é repetido
- **WHEN** Laravel repete um comando já aceito com o mesmo corpo e `command_id`
- **THEN** o gateway devolve a aceitação original sem enfileirar segundo envio

#### Scenario: Command ID conflita
- **WHEN** o mesmo `command_id` é reutilizado com digest diferente
- **THEN** o gateway rejeita o conflito e não executa nenhum novo efeito

#### Scenario: ACK remoto não é conclusivo
- **WHEN** a conexão cai depois do write e antes de confirmação conclusiva
- **THEN** o gateway preserva o mesmo provider ID, marca resultado ambíguo e permite reconciliação segura

### Requirement: Eventos são entregues pelo menos uma vez
O gateway SHALL persistir eventos antes da entrega ao Laravel e repeti-los até resposta 2xx. Cada evento MUST possuir `gateway_event_id`, `session_id`, tipo, timestamp e payload sanitizado; receipts SHALL referenciar o provider message ID. O gateway MUST conservar falhas duravelmente e oferecer health/metrics sem conteúdo de mensagem.

#### Scenario: Laravel está indisponível
- **WHEN** um inbound chega durante indisponibilidade do Laravel
- **THEN** evento e eventual mídia permanecem pendentes e são reenviados após recuperação

#### Scenario: Laravel confirma evento
- **WHEN** Laravel retorna 2xx para o evento
- **THEN** o gateway marca entrega confirmada e pode remover o spool associado

#### Scenario: Delivery falha repetidamente
- **WHEN** as tentativas excedem a política automática
- **THEN** o evento permanece em estado de erro reprocessável e uma métrica sanitizada sinaliza a falha

### Requirement: Comunicação interna exige autenticação e proteção contra replay
Toda chamada Laravel↔gateway SHALL usar HMAC-SHA256 versionado com key ID, timestamp, nonce e digest do corpo. Requisição com assinatura inválida, timestamp fora da janela ou nonce repetido MUST ser rejeitada antes de qualquer mutação. O gateway MUST permanecer sem rota pública no proxy externo.

#### Scenario: Assinatura válida
- **WHEN** requisição interna possui chave ativa, corpo íntegro, timestamp válido e nonce novo
- **THEN** o endpoint processa a operação e registra apenas metadados sanitizados

#### Scenario: Replay
- **WHEN** uma requisição válida é repetida com o mesmo nonce
- **THEN** a segunda chamada é rejeitada sem reexecutar o comando

#### Scenario: Chave em rotação
- **WHEN** key ID anterior ainda está na janela de rotação
- **THEN** assinaturas antigas e novas são aceitas sem expor os segredos em resposta ou log

### Requirement: Uma única réplica possui cada sessão
O gateway SHALL usar lease, heartbeat e fencing token para garantir um único owner ativo por sessão. Réplicas SHALL respeitar capacidade configurável e assumir sessão após expiração do owner anterior. Comandos MUST ser executados somente pelo owner que possui fencing token vigente.

#### Scenario: Duas réplicas disputam sessão
- **WHEN** duas réplicas tentam adquirir a mesma sessão simultaneamente
- **THEN** somente uma obtém lease válido e inicia WhatsMeow

#### Scenario: Owner deixa de renovar
- **WHEN** heartbeat expira
- **THEN** outra réplica assume a sessão com fencing token superior e o owner antigo não executa novos comandos

#### Scenario: Capacidade esgotada
- **WHEN** todas as réplicas atingem o limite de sessões
- **THEN** novas sessões permanecem pendentes/degradadas, sem conexão duplicada, e a condição aparece em métrica

### Requirement: Mídia usa spool cifrado e streaming privado
O gateway SHALL cifrar mídia temporária em volume persistente, enviar inbound por stream/multipart e apagar o spool somente após ACK do Laravel. Para outbound, SHALL obter bytes por endpoint interno autorizado ao command ID e MUST NOT usar URL pública ou acesso geral ao vault.

#### Scenario: Mídia inbound sobrevive restart
- **WHEN** gateway reinicia depois de salvar mídia e antes do ACK Laravel
- **THEN** o spool é retomado e o mesmo evento/mídia é reenviado

#### Scenario: Outbound sem autorização
- **WHEN** gateway solicita anexo com command ID que não referencia o objeto
- **THEN** Laravel nega o stream e nenhum object ID sensível é revelado

#### Scenario: ACK remove spool
- **WHEN** Laravel confirma persistência e digest da mídia inbound
- **THEN** gateway elimina o arquivo temporário e mantém apenas ledger sanitizado

### Requirement: Operação inicia fail-closed e observável
Gateway e Laravel SHALL respeitar kill switches global, Office e inbox, todos OFF por default. O gateway SHALL expor health e métricas de sessões, leases, fila, retries, latência e spool sem telefone, texto, QR ou credenciais. Uma inbox desabilitada MUST NOT conectar nem enviar.

#### Scenario: Flag global desligada
- **WHEN** processo está saudável mas a flag global está OFF
- **THEN** comandos de conexão/envio são recusados de forma auditável e nenhuma sessão é iniciada

#### Scenario: Inbox desabilitada
- **WHEN** somente a inbox está desabilitada
- **THEN** outras inboxes elegíveis continuam operando e aquela inbox não executa comandos

#### Scenario: Métrica operacional
- **WHEN** operador coleta métricas
- **THEN** recebe contagens/idades/estados sem conteúdo ou identificadores pessoais completos
