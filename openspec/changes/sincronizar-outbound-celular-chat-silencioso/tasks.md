## 1. Gateway outbound live (N0)

- [x] 1.1 Remover early-return `IsFromMe && !history` em `event_bridge.go` para live OUTBOUND entrar no ledger
- [x] 1.2 Teste Go: live `IsFromMe=true` → `MESSAGE_RECEIVED` com `direction=OUTBOUND`; history outbound sem regressão (`make gateway-test` / filtro EventBridge)

## 2. Ingest Laravel (N0)

- [x] 2.1 Feature PHP: `MESSAGE_RECEIVED` OUTBOUND cria mensagem outbound, reabre conversa pendente quando aplicável e deduplica por `provider_message_id` (`php artisan test --filter=Communication` via Compose)

## 3. Web hydrate silencioso (N1 ← 1,2 só para E2E; código paralelo ok)

- [x] 3.1 Extrair `refreshConversationDetail(id, { silent })` em `useCommunicationWorkspace.ts`; `hydrateFromEvents` e pós-`sendMessage` usam silent; abertura sem cache mantém loading
- [x] 3.2 `TimelinePanel.vue`: skeleton só se `loading && !conversation.messages?.length`
- [x] 3.3 Teste unitário/gate web cobre silent refresh e gate de skeleton

## 4. Gates (N2 ← 1–3)

- [x] 4.1 Validar OpenSpec da change (`openspec validate --specs --strict` + change)
- [x] 4.2 Gates da área: gateway-test, Feature Communication relevante, `pnpm` lint/typecheck/test da web tocada
