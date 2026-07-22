## ADDED Requirements

### Requirement: Workspace de atendimento inicializa quando a sessão autoriza visualização
O sistema SHALL carregar inboxes, conversas, sync por cursor e assinaturas Reverb quando o operador passa a ter `communication.view`, inclusive se a identidade autenticada chegar após o mount da página. Troca de sessão MUST reinicializar o workspace sem depender de um único `onMounted`.

#### Scenario: Identidade chega após o mount
- **WHEN** o operador abre `/communication` antes de `me` estar disponível e em seguida obtém `communication.view`
- **THEN** o workspace carrega inboxes/conversas, sincroniza o cursor e assina os canais privados das inboxes visíveis

#### Scenario: Troca de sessão
- **WHEN** a sessão do dashboard muda de usuário/escritório enquanto `/communication` permanece montada
- **THEN** o workspace reinicializa no novo contexto sem reutilizar assinaturas ou cursor da sessão anterior

### Requirement: Composer limpa rascunho somente após envio bem-sucedido
O sistema SHALL preservar body, anexo e citação do composer até a API confirmar o envio. Falha de disponibilidade, permissão ou validação MUST NOT apagar o rascunho; sucesso MUST limpar o composer.

#### Scenario: Envio rejeitado pela API
- **WHEN** o operador submete mensagem e a API responde erro (ex.: inbox não conectada)
- **THEN** o texto/anexo permanecem no composer e um erro é exibido

#### Scenario: Envio aceito
- **WHEN** o operador submete mensagem e a API persiste a mensagem
- **THEN** o composer é limpo e a timeline inclui a mensagem retornada

### Requirement: Broadcast de ledger é imediato após commit
O sistema SHALL publicar `communication.event` em canais privados Reverb imediatamente após o commit da transação do ledger (`ShouldBroadcastNow` ou equivalente síncrono pós-commit). O WebSocket MUST continuar sendo gatilho; a sincronização por cursor permanece a fonte de verdade.

#### Scenario: Mensagem persistida notifica assinantes sem depender da fila default
- **WHEN** um evento de comunicação é gravado no ledger com `BROADCAST_CONNECTION=reverb`
- **THEN** o evento privado é emitido sem enfileirar job de broadcast na fila `default`

#### Scenario: Reconexão ainda recupera lacuna
- **WHEN** o cliente perde a conexão e eventos são persistidos nesse intervalo
- **THEN** a chamada de sync após o último cursor retorna todos os eventos acessíveis faltantes

### Requirement: Hydrate realtime respeita conversa selecionada e cursor coerente
O sistema SHALL tratar cursor WebSocket numérico mesmo quando serializado como string, avançar o cursor de forma monotônica e, ao receber evento da conversa selecionada, recarregar a timeline dessa conversa mesmo que o filtro de status da lista exclua o item.

#### Scenario: Cursor chega como string
- **WHEN** o cliente Echo recebe `communication.event` com `cursor` string digitável maior que o cursor local
- **THEN** o workspace dispara sincronização por cursor

#### Scenario: Conversa selecionada fora do filtro OPEN
- **WHEN** o filtro de lista é OPEN, a conversa selecionada não está OPEN e chega evento dessa conversa
- **THEN** a timeline da seleção é atualizada sem exigir mudança manual do filtro

#### Scenario: Receipt sem conversation_id na conversa aberta
- **WHEN** chega evento da mesma inbox da conversa selecionada com `conversation_id` nulo
- **THEN** a timeline da seleção é recarregada

### Requirement: Autorização de canal privado alinha-se ao acesso REST
O sistema SHALL autorizar `private-communication.inbox.{inboxId}` e `private-communication.office.{officeId}` com a mesma regra de `CommunicationAccess` / `CurrentOffice` usada pela API REST (Admin do Office, platform privileged no Office ativo, ou membership ativa da inbox). MUST NOT exigir membership real no Office da inbox quando o ator é platform privileged com Office ativo correto.

#### Scenario: Platform privileged assina inbox do Office ativo
- **WHEN** ator platform privileged com Office ativo igual ao da inbox solicita auth do canal privado da inbox
- **THEN** a autorização é concedida

#### Scenario: Operador sem membership é negado
- **WHEN** operador com `communication.view` mas sem membership na inbox solicita auth do canal
- **THEN** a autorização é negada

#### Scenario: Admin do Office assina inbox do próprio Office
- **WHEN** Admin do Office ativo solicita auth de canal de inbox desse Office
- **THEN** a autorização é concedida

### Requirement: Cliente trata canal inscrito e faz poll quando realtime falha
O sistema SHALL exibir estado “tempo real ativo” somente após ao menos um canal privado inscrito com sucesso. Reconexão MUST forçar re-assinatura. Enquanto o workspace estiver montado e canais não estiverem prontos (ou realtime indisponível), o cliente SHALL sincronizar por cursor em intervalo curto (≤5s) até canais ficarem prontos ou o unmount.

#### Scenario: Badge não mente com só o transporte WS
- **WHEN** o transporte Pusher/Reverb conecta mas a auth do canal privado falha
- **THEN** o estado exibido NÃO é “tempo real ativo”

#### Scenario: Poll cobre lacuna sem canal
- **WHEN** o workspace está montado sem canal privado pronto e uma mensagem inbound é persistida
- **THEN** o sync por cursor atualiza lista/timeline em até 5 segundos sem F5
