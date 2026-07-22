## ADDED Requirements

### Requirement: Timeline representa tipos e ações nativas 1x1
O sistema SHALL representar texto, imagem, áudio/PTT, vídeo, documento, sticker, localização, contato, poll e respostas interativas suportadas. Reação, edição e revogação MUST atualizar ou relacionar a mensagem original sem criar conversa paralela nem perder o evento auditável.

#### Scenario: Mensagem responde outra
- **WHEN** operador envia resposta para mensagem acessível da mesma conversa
- **THEN** Laravel persiste `reply_to_message_id`, gateway recebe somente os metadados allowlisted e a timeline mostra o vínculo

#### Scenario: Reação é removida
- **WHEN** operador remove sua reação de mensagem 1:1
- **THEN** o mesmo alvo recebe reação vazia e a projeção remove o emoji preservando auditoria

#### Scenario: Mensagem é editada ou revogada
- **WHEN** evento remoto válido referencia provider ID existente
- **THEN** a timeline atualiza o conteúdo/estado original monotonicamente e registra a ação deduplicada

### Requirement: Sinais efêmeros não viram histórico de negócio
O sistema SHALL projetar presença online/last seen e typing/paused/recording somente para membros autorizados da inbox, com TTL e recuperação por estado atual. Esses sinais MUST NOT criar `CommunicationMessage`, anexos ou disparos fiscais.

#### Scenario: Contato está digitando
- **WHEN** gateway entrega chat presence 1:1 para conversa ativa
- **THEN** membros autorizados veem indicador temporário e nenhum item é inserido na timeline

#### Scenario: Sinal expira
- **WHEN** TTL termina sem nova atualização
- **THEN** a UI remove o indicador mesmo que não receba evento explícito de pausa

#### Scenario: Membro sem acesso observa canal realtime
- **WHEN** usuário tenta assinar sinais de inbox fora de sua membership/Office
- **THEN** autorização é negada sem revelar endereço, last seen ou existência da conversa

### Requirement: Consultas e controles de conta exigem papel administrativo
O sistema SHALL permitir a administradores autorizados consultar disponibilidade/perfil, gerenciar blocklist e privacy e iniciar operações de sessão. Operadores com apenas `communication.reply` MUST limitar-se a ações de conversa e receipts.

#### Scenario: Operador tenta mudar privacidade
- **WHEN** membro sem `communication.manage_inboxes` chama controle de privacy ou blocklist
- **THEN** Laravel rejeita antes de criar comando/query para o gateway

#### Scenario: Administrador bloqueia identidade
- **WHEN** administrador bloqueia uma identidade individual do Office ativo
- **THEN** o gateway resolve PN/LID da sessão correta, aplica a ação e o hub registra evento sanitizado

### Requirement: Histórico importado converge com a timeline
O sistema SHALL ingerir history batches 1:1 idempotentemente, reutilizando conversa/identidade do Office, preservando direction, provider ID, timestamp, tipo e quote. Histórico MUST NOT reabrir conversa resolvida nem emitir automação, notificação de mensagem nova ou receipt retroativo.

#### Scenario: Mesmo batch é reentregue
- **WHEN** history batch com provider IDs já persistidos é recebido novamente
- **THEN** nenhuma mensagem, anexo ou evento de produto é duplicado

#### Scenario: Histórico contém grupo ou channel
- **WHEN** batch upstream inclui conversa fora do escopo 1:1
- **THEN** a entrada é rejeitada antes da projeção de tenant e aparece somente em métrica sanitizada

#### Scenario: Mensagem antiga pertence a conversa resolvida
- **WHEN** history sync entrega mensagem anterior ao fechamento da conversa
- **THEN** sistema associa ao histórico correto sem alterar o estado atual da conversa
