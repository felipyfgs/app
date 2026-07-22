## ADDED Requirements

### Requirement: Ingest live OUTBOUND do dispositivo na timeline
O sistema SHALL aceitar `MESSAGE_RECEIVED` com `direction=OUTBOUND` originado do gateway (aparelho pareado), criar ou reutilizar a mensagem pelo `provider_message_id`, reabrir conversa pendente quando aplicável e NÃO duplicar quando o mesmo provider ID já existir (eco de envio do hub).

#### Scenario: Outbound do celular cria bolha
- **WHEN** o gateway envia `MESSAGE_RECEIVED` OUTBOUND com provider ID novo para conversa conhecida
- **THEN** a mensagem outbound é persistida na conversa e fica disponível via sync/REST

#### Scenario: Eco do hub não duplica
- **WHEN** o hub já persistiu mensagem com o mesmo `provider_message_id` e chega o eco OUTBOUND do gateway
- **THEN** o ingest é idempotente (sem segunda linha de mensagem)

### Requirement: Hydrate da conversa aberta não troca timeline por skeleton
O workspace SHALL recarregar o detalhe da conversa selecionada após eventos duráveis sem ativar estado de loading/skeleton quando a timeline já possui mensagens em cache (refresh silencioso). Abertura inicial ou seleção sem cache MAY exibir skeleton. O painel de timeline MUST mostrar skeleton somente quando `loading` e a lista de mensagens está vazia.

#### Scenario: Inbound na conversa aberta
- **WHEN** chega evento durável da conversa selecionada e já existem mensagens em cache
- **THEN** a timeline atualiza sem substituir o conteúdo por skeleton

#### Scenario: Envio pelo hub após merge otimista
- **WHEN** o operador envia pelo hub e o detalhe é revalidado
- **THEN** o refresh NÃO liga skeleton se a conversa já tem mensagens

#### Scenario: Abertura sem cache
- **WHEN** o operador abre conversa ainda sem mensagens carregadas
- **THEN** o skeleton MAY aparecer até o primeiro GET de detalhe
