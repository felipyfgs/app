## Context

O EventBridge dropa live `IsFromMe` (linhas ~275–277), enquanto history já projeta OUTBOUND. O Laravel `GatewayEventIngestor` já aceita `MESSAGE_RECEIVED` com `direction=OUTBOUND` e deduplica por `provider_message_id` (eco de envio do hub é seguro). No web, `hydrateFromEvents` chama `selectConversation`, que sempre seta `detailLoading=true` → TimelinePanel troca a lista por `USkeleton`.

Justificativa da exceção 2 capabilities: o gap é transversal gateway→ledger→UI; um único change implantável evita half-fix (outbound sem UI estável ou UI estável sem outbound).

## Goals / Non-Goals

**Goals**

- Live IsFromMe → `MESSAGE_RECEIVED` OUTBOUND no ledger.
- Hydrate/sync da conversa aberta sem skeleton se já há mensagens.
- Sem duplicata quando o hub envia e o eco do aparelho chega.

**Non-Goals**

- Redesign shell; grupos; mudar HMAC/OpenAPI além do necessário; flags ON; SERPRO/mei Compose.

## Decisions

1. **Remover early-return IsFromMe live** (não filtrar no hub).
   - Alternativa: filtrar no Laravel — pior, perde outbound do aparelho.
   - Rationale: path de normalização/`messageDirection` já existe; history prova o contrato.

2. **Dedup fica no Laravel por `provider_message_id`**.
   - Alternativa: gateway tenta saber se o hub enviou — frágil.
   - Rationale: já implementado no ingestor.

3. **`refreshConversationDetail(id, { silent })`** separado de abertura com loading.
   - `silent: true` ou cache com `messages.length` → não seta `detailLoading`.
   - `hydrateFromEvents` e pós-`sendMessage` usam silent; clique/deep-link sem cache mantém skeleton.
   - Alternativa: nunca skeleton — piora cold open.

4. **TimelinePanel: skeleton só se `loading && !messages?.length`**.
   - Cinto e suspensório se algum caller ainda ligar loading com cache.

## Risks / Trade-offs

- [Eco hub + aparelho] → Dedup por provider_message_id; teste Feature cobre reprocessamento.
- [Flicker residual se `messages` undefined no merge] → merge preserva array; gate UI asserta silent path.
- [Concorrência com cobrir-whatsmeow no EventBridge] → patch mínimo coordenado; só remove o skip.

## Migration Plan

Deploy gateway + web juntos preferencialmente. Rollback: reintroduzir skip (volta gap outbound) / reverter silent (volta skeleton). Sem migration de DB.

## Open Questions

- Nenhuma bloqueante.
