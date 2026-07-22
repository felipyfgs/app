## ADDED Requirements

### Requirement: Superfície whatsmeow possui catálogo completo e verificável
O sistema SHALL manter catálogo versionado de todo método público de `*whatsmeow.Client` e todo tipo público de evento da versão pinada, atribuindo a cada entrada uma disposição, escopo, implementação e evidência. O gate MUST falhar quando o method set mudar sem atualização explícita do catálogo.

#### Scenario: Método upstream é adicionado
- **WHEN** a versão pinada passa a expor método público ausente do catálogo
- **THEN** o teste de deriva falha e exige decisão explícita antes do merge

#### Scenario: Método não aplicável é catalogado
- **WHEN** um método pertence a grupo, channel, status, API deprecated ou primitiva interna perigosa
- **THEN** o catálogo conserva a entrada com disposição de exclusão e justificativa verificável

#### Scenario: Método aplicável fica sem evidência
- **WHEN** uma entrada 1:1 é marcada como direta, composta ou interna sem apontar implementação e teste
- **THEN** o gate do catálogo falha

### Requirement: Boundary rejeita qualquer alvo fora de conversa 1x1
Toda operação de chat ou contato SHALL aceitar somente endereço de usuário individual normalizado e MUST rejeitar group, community, newsletter/channel, broadcast/status e server desconhecido antes de upload, persistência de efeito remoto ou chamada ao whatsmeow.

#### Scenario: Comando aponta para grupo
- **WHEN** um comando válido e autenticado contém JID de grupo
- **THEN** o gateway encerra o comando com erro `RECIPIENT_SCOPE_NOT_ALLOWED` sem chamar o client

#### Scenario: Evento de canal chega ao handler
- **WHEN** o client emite mensagem ou sinal de newsletter/channel
- **THEN** o gateway descarta o evento antes do ledger 1:1 e incrementa somente métrica sanitizada de escopo rejeitado

#### Scenario: LID individual precisa de PN
- **WHEN** operação permitida exige PN e recebe LID individual conhecido
- **THEN** o gateway resolve pelo device store da própria sessão sem expor o mapping

### Requirement: Gateway cobre ciclo completo de sessão e conta aplicável
O gateway SHALL oferecer operações tipadas para conectar, desconectar, resetar, consultar estado, logout, QR, pareamento por telefone, espera de readiness, modo passivo e configurações internas allowlisted. Operações de transporte/proxy/push MUST permanecer somente administrativas e fail-closed.

#### Scenario: Pareamento por telefone
- **WHEN** administrador autorizado solicita código para telefone normalizado durante janela de pairing
- **THEN** o gateway usa `PairPhone`, devolve somente código/expiração pelo evento efêmero e nunca registra telefone ou código em log

#### Scenario: Reset de conexão
- **WHEN** owner vigente recebe comando de reset para sessão degradada
- **THEN** o gateway usa a primitiva de reset/reconexão sem criar novo device ou segundo owner

#### Scenario: Consulta de estado
- **WHEN** Laravel consulta sessão com HMAC e nonce válidos
- **THEN** o gateway retorna estado sanitizado derivado de conexão e login sem credenciais ou JID de device

### Requirement: Mensagens 1x1 cobrem tipos e ações suportados
O gateway SHALL enviar texto com preview/resposta, imagem, áudio/PTT, vídeo, documento, sticker, localização, contato e poll por DTOs allowlisted. SHALL também editar, revogar, reagir/remover reação, votar, marcar lida/tocada, configurar temporizador e solicitar reenvio de mensagem indisponível quando a primitiva pinada suportar a ação.

#### Scenario: Mensagem usa ID do Laravel
- **WHEN** qualquer tipo de mensagem é enviado ou repetido pelo mesmo comando
- **THEN** `SendMessage` recebe o mesmo `provider_message_id` e o gateway não gera nova identidade remota

#### Scenario: Mídia é enviada
- **WHEN** comando de mídia referencia stream interno autorizado com tamanho, MIME e digest válidos
- **THEN** gateway faz upload no media type correto, monta o protobuf allowlisted e não aceita base64 nem URL arbitrária

#### Scenario: Ação referencia mensagem
- **WHEN** operador edita, revoga, reage ou vota em mensagem 1:1 conhecida
- **THEN** gateway compõe o builder pinado correto e preserva chat, sender e provider IDs validados

#### Scenario: Tipo composto não é suportado pelo commit
- **WHEN** DTO solicita buttons/list cujo protobuf vigente não passa no teste de contrato
- **THEN** gateway responde `MESSAGE_KIND_UNSUPPORTED` e não faz fallback para texto diferente do solicitado

### Requirement: Consultas remotas são tipadas, autenticadas e sanitizadas
O gateway SHALL oferecer query privada para disponibilidade de número, user info, business profile, avatar, devices necessários internamente, links de contato/business, blocklist e privacy. Queries MUST usar HMAC, nonce, deadline, sessão pertencente ao caller Laravel e schemas de resposta allowlisted.

#### Scenario: Número é consultado
- **WHEN** Laravel envia lote limitado de telefones normalizados
- **THEN** gateway retorna disponibilidade e identidade verificada permitida sem device list ou node bruto

#### Scenario: Privacidade é alterada
- **WHEN** administrador envia nome e valor presentes na matriz pinada
- **THEN** gateway aplica o setting e devolve projeção sanitizada atualizada

#### Scenario: Query tenta replay
- **WHEN** a mesma query assinada é repetida com nonce já consumido
- **THEN** a segunda execução é rejeitada antes de chamar whatsmeow

### Requirement: Eventos 1x1 são traduzidos por tipo e sensibilidade
O gateway SHALL reconhecer mensagens e ações 1:1, receipts, presence, chat presence, history, app-state, profile/identity, blocklist/privacy, undecryptable/media retry e estados operacionais. Payloads MUST ser allowlisted e MUST NOT conter evento bruto, node, token, QR, media key, direct path ou credencial.

#### Scenario: Poll vote chega cifrado
- **WHEN** mensagem 1:1 contém poll update
- **THEN** gateway usa `DecryptPollVote`, emite hashes e opções conhecidas sanitizadas e mantém referência ao poll original

#### Scenario: Edit chega secret-encrypted
- **WHEN** mensagem 1:1 contém edição secret-encrypted suportada
- **THEN** gateway decripta antes da classificação e emite ação ligada ao provider ID original

#### Scenario: Presença é recebida
- **WHEN** contato previamente subscribed muda online/last seen ou typing state
- **THEN** gateway emite sinal efêmero com TTL e endereço normalizado, sem gravá-lo como mensagem da timeline

#### Scenario: Falha operacional ocorre
- **WHEN** há keepalive timeout, outdated client, temporary ban ou stream error
- **THEN** gateway emite estado sanitizado acionável sem serializar o evento upstream

### Requirement: App-state, histórico e recuperação são limitados a 1x1
O gateway SHALL compor archive, mute, pin, star, delete-for-me e mark-as-read apenas por builders allowlisted. History sync e unavailable-message recovery MUST operar somente em chat 1:1, com limites, cursor, deduplicação e peer target correto.

#### Scenario: Histórico é solicitado
- **WHEN** Laravel solicita lote anterior a uma mensagem 1:1 conhecida
- **THEN** gateway usa o request de history sync com limite configurado e entrega batches idempotentes filtrados para aquele chat

#### Scenario: Mensagem não pôde ser decriptada
- **WHEN** evento undecryptable elegível referencia chat/sender/message ID 1:1
- **THEN** gateway constrói unavailable request e o envia como peer message para os próprios devices, nunca para grupo/canal

#### Scenario: Chat é arquivado
- **WHEN** comando allowlisted altera archive/mute/pin de conversa 1:1
- **THEN** gateway usa `SendAppState` com builder específico e não aceita patch arbitrário
