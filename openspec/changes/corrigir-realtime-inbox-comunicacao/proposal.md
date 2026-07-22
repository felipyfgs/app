## Why

O Atendimento não atualiza a timeline nem a lista automaticamente: além de init/composer/broadcast enfileirado, o canal privado Reverb não autoriza o mesmo ator que a API REST (platform privileged / Admin via `CurrentOffice`), o badge verde mente com só o transporte WS, e sem poll a UI fica presa até F5.

## What Changes

- Inicialização idempotente do workspace quando `canView` / sessão ficam disponíveis (não só no `onMounted`).
- Composer limpa rascunho apenas após `sendMessage` retornar sucesso.
- `CommunicationEventCommitted` passa a broadcast imediato (`ShouldBroadcastNow`), mantendo `$afterCommit`.
- Normalização do cursor recebido via WebSocket e hydrate da conversa selecionada independente do filtro OPEN (inclui receipts por `inbox_id`).
- Auth de `channels.php` alinhada a `CommunicationAccess` (Admin, platform privileged, membership).
- Badge “tempo real ativo” só com canal privado inscrito; force resubscribe no reconnect; poll de cursor ≤5s como fallback.
- Testes unitários/feature que cobrem init, cursor, clear-on-success, broadcast imediato e auth de canal.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `communication-inbox`: tempo real recuperável (broadcast imediato, init, composer, hydrate, **auth de canal = REST**, estado de canal honesto, poll fallback).

## Impact

- Web: `useCommunicationWorkspace`, plugin realtime, `Composer.vue`, `communication.vue`, testes unitários.
- API: `CommunicationEventCommitted`, `CommunicationAccess`, `routes/channels.php`, Feature `CommunicationApiTest`.
- Sem mudança de contrato HTTP público; WS continua gatilho e cursor sync continua fonte de verdade.
- Non-goals: cobertura whatsmeow (presence/history), redesign push-payload na UI, flags ON em prod, mei no Compose, ops backup/restore, mutações fiscais/SERPRO live.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: change arquivável/ativa `adicionar-comunicacao-whatsapp-nativa` (capability `communication-inbox`) e código atual de Reverb/Echo.
- Depende de: **nenhuma** (change ativa bloqueante)
- Capability/contrato: `communication-inbox`
- Marco exigido: `specs`
- Relação: coordenada com `cobrir-whatsmeow-conversas-1x1` (não bloqueante; aquela cobre gateway 1:1, esta corrige realtime/envio UI)
- Desbloqueia: operação confiável do Atendimento em tempo real
- Paralelismo: pode seguir em paralelo a changes sem ownership de `communication-inbox` / plugin Reverb
