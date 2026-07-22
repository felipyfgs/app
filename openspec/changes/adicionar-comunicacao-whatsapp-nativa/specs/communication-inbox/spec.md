## ADDED Requirements

### Requirement: Inboxes e acesso pertencem ao Office
O sistema SHALL permitir múltiplas inboxes WhatsApp por `Office`, com número conectado, fila `WorkDepartment`, membros e uma inbox geral opcional. Toda leitura ou mutação MUST resolver o Office por `CurrentOffice`; administradores do Office SHALL acessar todas as inboxes e demais membros SHALL acessar somente as inboxes autorizadas.

#### Scenario: Membro consulta inbox autorizada
- **WHEN** um membro autenticado consulta conversas de uma inbox à qual pertence no Office ativo
- **THEN** a API retorna somente dados daquela inbox e Office

#### Scenario: Inbox estrangeira é ocultada
- **WHEN** um usuário tenta acessar uma inbox pertencente a outro Office
- **THEN** a API responde como recurso indisponível e não revela metadados, membros ou mensagens

#### Scenario: Inbox geral é única
- **WHEN** um administrador marca uma inbox como padrão de saída no Office
- **THEN** qualquer inbox anteriormente padrão é desmarcada na mesma transação

### Requirement: Identidade de comunicação é independente de cliente
O sistema SHALL manter contatos de comunicação e identidades normalizadas por Office e canal. Uma identidade MUST ser única por Office+canal+endereço normalizado e SHALL poder se vincular a vários `Client`/`ClientContact`. Uma identidade recebida sem vínculo MUST criar contato provisório, sem criar cliente fiscal.

#### Scenario: Número desconhecido cria contato provisório
- **WHEN** chega mensagem 1:1 de um número ainda não conhecido no Office
- **THEN** o sistema cria uma única identidade e contato provisório e abre uma conversa sem cliente associado

#### Scenario: Mesmo telefone aparece em vários clientes
- **WHEN** o operador vincula uma identidade existente a contatos de dois clientes do mesmo Office
- **THEN** ambos os vínculos são preservados e a identidade não é duplicada

#### Scenario: Mesmo telefone em Offices diferentes
- **WHEN** o mesmo número conversa com duas inboxes pertencentes a Offices diferentes
- **THEN** cada Office mantém seu próprio contato, histórico e vínculos isolados

### Requirement: Conversas seguem ciclo de atendimento auditável
O sistema SHALL permitir no máximo uma conversa ativa por inbox+identidade, com estados `OPEN`, `PENDING`, `RESOLVED` e `SNOOZED`, fila, assignee, prioridade, snooze e `lock_version`. Nova interação quando não houver conversa ativa SHALL criar outra conversa; inbound em conversa ativa SHALL colocá-la `OPEN` e retirar snooze.

#### Scenario: Automação abre conversa pendente
- **WHEN** uma mensagem automática é criada para uma identidade sem conversa ativa
- **THEN** o sistema cria a conversa como `PENDING` na fila da inbox

#### Scenario: Cliente responde mensagem pendente
- **WHEN** chega mensagem inbound para conversa `PENDING`
- **THEN** a conversa muda para `OPEN`, mantém o contexto e entra não atribuída na fila quando ainda não houver assignee

#### Scenario: Nova mensagem após resolução
- **WHEN** chega mensagem para inbox+identidade cuja última conversa está `RESOLVED`
- **THEN** uma nova conversa é criada e a conversa resolvida permanece preservada

#### Scenario: Atualização concorrente
- **WHEN** dois operadores alteram a mesma conversa usando a mesma versão
- **THEN** somente a primeira alteração é aceita e a segunda recebe conflito de versão

### Requirement: Timeline unifica mensagens, notas e automações
O sistema SHALL persistir mensagens inbound/outbound, notas internas e mensagens de automação na mesma timeline. Mensagens SHALL suportar texto, imagem, áudio, vídeo, documento e resposta/citação. Notas internas MUST NOT ser enviadas ao transporte. Status remoto SHALL ser monotônico e eventos duplicados MUST NOT duplicar mensagens.

#### Scenario: Operador envia texto
- **WHEN** membro com `communication.reply` envia texto em uma conversa ativa
- **THEN** mensagem e outbox são persistidos atomicamente antes de qualquer chamada ao gateway

#### Scenario: Nota interna não sai do hub
- **WHEN** operador publica uma nota interna
- **THEN** a nota aparece na timeline com autor e nenhum comando de transporte é criado

#### Scenario: Receipt fora de ordem
- **WHEN** o sistema recebe `READ` antes de `DELIVERED` e depois recebe `SENT`
- **THEN** a mensagem permanece `READ` e todos os eventos são auditados sem regressão

#### Scenario: Evento inbound repetido
- **WHEN** o gateway reentrega o mesmo `gateway_event_id` ou `provider_message_id`
- **THEN** existe uma única mensagem e a segunda entrega é tratada idempotentemente

### Requirement: Anexos permanecem privados e recuperáveis
O sistema SHALL armazenar anexos com cifra, MIME detectado, tamanho e SHA-256, aceitando streams. Downloads MUST exigir usuário, Office e permissão válidos e MUST ser auditados. O sistema MUST NOT publicar URL permanente de mídia nem expor object IDs internos.

#### Scenario: Download autenticado
- **WHEN** membro autorizado baixa anexo de conversa acessível
- **THEN** o conteúdo é transmitido com headers seguros e o acesso é auditado

#### Scenario: Anexo estrangeiro
- **WHEN** usuário de outro Office tenta baixar o anexo
- **THEN** nenhum byte ou metadado do arquivo é retornado

#### Scenario: Ingestão interrompida
- **WHEN** o Laravel falha antes de confirmar mídia inbound
- **THEN** o gateway conserva o spool e reentrega até confirmação sem gerar segundo anexo

### Requirement: Atualização em tempo real é recuperável por cursor
O sistema SHALL publicar mudanças de inbox, conversa e mensagem em canais privados Reverb autorizados por Office/inbox. Cada mudança SHALL possuir cursor monotônico e a API SHALL oferecer sincronização após cursor; WebSocket MUST ser tratado como projeção, não fonte exclusiva.

#### Scenario: Membro recebe nova mensagem
- **WHEN** uma mensagem é persistida em inbox autorizada e o membro está conectado
- **THEN** o evento privado atualiza lista e timeline sem polling completo

#### Scenario: Reconexão recupera lacuna
- **WHEN** o cliente perde a conexão e eventos são persistidos nesse intervalo
- **THEN** a chamada de sync após o último cursor retorna todos os eventos acessíveis faltantes

#### Scenario: Canal privado não autorizado
- **WHEN** membro tenta assinar canal de inbox sem membership
- **THEN** a autorização do broadcast é negada

### Requirement: Superfície de atendimento segue o shell do produto
O sistema SHALL oferecer `/communication` com filtros/lista, timeline/composer e contexto de contato/clientes em desktop e slideovers em mobile. A superfície SHALL suportar status, snooze, fila, atribuição, labels, busca, canned responses, notas e tipos de mídia definidos, sem redesenhar o shell do dashboard.

#### Scenario: Atendimento desktop
- **WHEN** operador abre `/communication` em viewport desktop
- **THEN** lista, conversa e contexto são utilizáveis no padrão de painéis do arquétipo

#### Scenario: Atendimento mobile
- **WHEN** operador seleciona conversa em viewport mobile
- **THEN** timeline e contexto abrem em superfícies móveis sem overflow horizontal

#### Scenario: Recurso sem permissão
- **WHEN** membro com visualização mas sem resposta abre uma conversa
- **THEN** a timeline permanece legível e o composer fica indisponível

### Requirement: Retenção e expurgo são administrativos
O sistema MUST NOT expirar automaticamente mensagens ou anexos. Administrador autorizado SHALL poder exportar e expurgar dados de comunicação com auditoria. Expurgo SHALL apagar conteúdo e objetos recuperáveis, preservando tombstone sanitizado suficiente para integridade do ledger.

#### Scenario: Exportação administrativa
- **WHEN** administrador solicita exportação de escopo permitido
- **THEN** o sistema gera artefato privado auditado sem incluir credenciais de gateway ou sessão

#### Scenario: Expurgo de conversa
- **WHEN** administrador confirma expurgo de uma conversa
- **THEN** corpos e anexos são eliminados e eventos passam a referenciar somente tombstones sanitizados
