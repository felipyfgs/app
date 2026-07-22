## ADDED Requirements

### Requirement: Composer preserva a intenção da mensagem até o WhatsApp
O sistema SHALL permitir texto, mídia, áudio gravado/PTT, sticker WebP e resposta a mensagem anterior, preservando tipo, citação e conteúdo no comando remoto. Texto SHALL ser obrigatório somente quando não houver mídia; o sistema MUST rejeitar combinações incompatíveis de MIME, tipo e PTT antes da outbox.

#### Scenario: Enter envia e Shift Enter quebra linha
- **WHEN** o operador pressiona Enter fora de composição IME em um composer válido
- **THEN** a mensagem é enviada, enquanto Shift+Enter insere uma quebra de linha sem envio

#### Scenario: Operador responde mensagem anterior
- **WHEN** o operador seleciona uma mensagem acessível da conversa e envia texto ou mídia como resposta
- **THEN** Laravel persiste o vínculo local e envia ao gateway o provider ID e remetente remoto allowlisted da mensagem citada

#### Scenario: Áudio gravado vira PTT
- **WHEN** o navegador suporta um MIME de gravação allowlisted e o operador conclui a gravação
- **THEN** o arquivo é enviado como `AUDIO` com `ptt=true`, sem transformar seu nome em corpo da mensagem

#### Scenario: WebP é escolhido como sticker
- **WHEN** o operador usa o seletor de sticker com arquivo `image/webp`
- **THEN** a mensagem é persistida e enviada como `STICKER`, sem legenda artificial

#### Scenario: Tipo de mídia é incompatível
- **WHEN** o cliente declara `STICKER` para arquivo que não seja WebP ou PTT para tipo que não seja áudio
- **THEN** a API responde validação sem persistir mensagem, anexo ou comando

### Requirement: Mídia da timeline é privada e utilizável
O sistema SHALL preservar nome, MIME, tamanho e digest de mídia inbound/outbound e SHALL oferecer preview inline autenticado para imagem, sticker, áudio e vídeo, além de download explícito. Preview e download MUST repetir autorização por Office/inbox, MUST NOT revelar `object_id` e MUST usar cache privado desabilitado.

#### Scenario: Imagem recebida aparece inline
- **WHEN** mensagem inbound acessível possui imagem persistida no vault
- **THEN** a timeline renderiza preview same-origin e mantém ação de download com o nome sanitizado

#### Scenario: Áudio recebido pode ser reproduzido
- **WHEN** mensagem inbound ou outbound acessível possui anexo de áudio
- **THEN** a timeline oferece player autenticado e o receipt `PLAYED` continua disponível somente quando aplicável

#### Scenario: Usuário de outro Office tenta preview
- **WHEN** usuário sem acesso solicita a URL inline de um anexo estrangeiro
- **THEN** nenhum byte, nome, MIME interno ou existência do objeto é revelado

#### Scenario: Documento não é embutido
- **WHEN** um anexo PDF, texto ou ZIP é exibido na timeline
- **THEN** a UI mostra cartão de arquivo e download, sem executar conteúdo inline

### Requirement: Ações de mensagem são bidirecionais e honestas
O sistema SHALL permitir ao operador reagir/remover reação em mensagem 1:1 e editar/revogar somente mensagens OUTBOUND elegíveis. Edit, revoke e reaction SHALL permanecer pendentes até confirmação do gateway; ações válidas originadas pelo cliente SHALL atualizar a mesma mensagem projetada e preservar o evento auditável.

#### Scenario: Operador escolhe reação no popover
- **WHEN** operador seleciona qualquer emoji da allowlist visual para mensagem remota
- **THEN** a reação é enfileirada para o provider ID original e a UI aguarda confirmação antes de alterar o estado durável

#### Scenario: Cliente edita mensagem recebida
- **WHEN** o gateway entrega evento `EDIT` válido do cliente para provider ID existente
- **THEN** o corpo original é atualizado, marcado como editado e permanece na mesma conversa

#### Scenario: Cliente apaga mensagem recebida
- **WHEN** o gateway entrega evento `REVOKE` válido do cliente para provider ID existente
- **THEN** a timeline substitui o conteúdo por estado apagado sem eliminar o ledger auditável

#### Scenario: Operador tenta editar inbound
- **WHEN** o operador tenta editar ou apagar para todos uma mensagem INBOUND
- **THEN** a ação não é oferecida na UI e a API rejeita chamada forjada

### Requirement: Identidade do cliente orienta lista e cabeçalho
Quando uma conversa estiver vinculada a `Client`, o sistema SHALL mostrar o nome do cliente fiscal como identidade principal no cabeçalho e na lista de conversas, mantendo nome do contato e telefone mascarado como contexto secundário. Na ausência de cliente vinculado, SHALL usar contato e depois endereço mascarado como fallback.

#### Scenario: Conversa possui cliente e contato
- **WHEN** a projeção da conversa contém cliente fiscal e contato nomeado
- **THEN** lista e cabeçalho mostram o cliente como título e o contato como informação secundária

#### Scenario: Conversa não possui cliente
- **WHEN** identidade provisória ainda não está vinculada a cliente fiscal
- **THEN** lista e cabeçalho usam nome do contato ou telefone mascarado sem inventar cliente

#### Scenario: Busca usa nome do cliente
- **WHEN** operador pesquisa pelo nome empresarial de cliente vinculado no Office ativo
- **THEN** a conversa correspondente é retornada sem incluir clientes de outro Office

### Requirement: Atendimento mantém o shell clean e responsivo
O sistema SHALL manter a estrutura master-detail do dashboard, usando densidade compacta, cores semânticas, controles acessíveis e ações progressivas. Desktop e mobile MUST permitir lista, mídia, quote, reactions, edição, revogação e composer sem overflow horizontal ou perda do rascunho.

#### Scenario: Timeline desktop com mídia
- **WHEN** uma conversa contém texto e múltiplos tipos de mídia
- **THEN** bolhas, previews, timestamps, status e ações permanecem legíveis no painel redimensionável

#### Scenario: Composer mobile grava áudio
- **WHEN** operador usa o composer em viewport mobile
- **THEN** controles essenciais permanecem alcançáveis e iniciar/cancelar gravação não abre overflow horizontal

#### Scenario: Perfil somente leitura
- **WHEN** membro com `communication.view` sem `communication.reply` abre conversa rica
- **THEN** todo histórico e mídia acessível permanecem legíveis, enquanto envio e mutações ficam indisponíveis
