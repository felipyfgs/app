## Why

Mensagens enviadas no WhatsApp do aparelho pareado (IsFromMe live) são descartadas no gateway antes do ledger, então o hub só mostra inbound ao vivo. Além disso, o hydrate realtime força `detailLoading` e troca a timeline por skeleton a cada sync — UX artificial, não “chat natural”.

## What Changes

- Gateway: emitir `MESSAGE_RECEIVED` live com `direction=OUTBOUND` quando `IsFromMe` (remover early-return); history permanece igual; dedup no Laravel por `provider_message_id`.
- Web: refresh de detalhe silencioso no hydrate (e pós-envio) sem ligar skeleton quando já há mensagens em cache; TimelinePanel só mostra skeleton se loading e timeline vazia.
- Testes Go (event bridge OUTBOUND live), Feature PHP (ingest OUTBOUND reabre/cria) e gate unitário web (silent refresh / skeleton).

Non-goals: grupos/canais, redesign do shell, alterar contrato HMAC, flags ON em produção, SERPRO live, mei no Compose, backup/restore.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `whatsapp-native-gateway`: live `IsFromMe` entra no ledger como OUTBOUND (não só history).
- `communication-inbox`: sync/hydrate da conversa aberta sem substituir timeline por skeleton; ingest OUTBOUND do dispositivo.

## Impact

- Gateway: `apps/whatsapp-gateway/internal/protocol/event_bridge.go` (+ testes Go).
- API: ingest já aceita OUTBOUND; Feature test cobre live OUTBOUND se necessário.
- Web: `useCommunicationWorkspace.ts`, `TimelinePanel.vue`, testes unitários de contrato UI.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: ledger `MESSAGE_RECEIVED`, Reverb+cursor sync, superfície Atendimento.
- Depende de: `cobrir-whatsmeow-conversas-1x1` (capabilities `whatsapp-native-gateway` / `communication-inbox`; marco `apply`; relação `coordenada` — mesmo EventBridge); `corrigir-realtime-inbox-comunicacao` (marco `apply`; relação `coordenada` — hydrate/seleção).
- Desbloqueia: chat natural com outbound do celular e timeline sem flicker.
- Paralelismo: não paralelizar patches no mesmo `event_bridge.go` / `useCommunicationWorkspace.ts` com changes ativas donas desses arquivos.
