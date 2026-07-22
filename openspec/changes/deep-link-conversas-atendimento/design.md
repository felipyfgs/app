## Context

Hoje [`apps/web/app/pages/communication.vue`](apps/web/app/pages/communication.vue) concentra lista + timeline; a seleção é só estado do composable. Mailbox já usa deep-link (`/monitoring/mailbox/[id]`). Chatwoot referencia `…/conversations/{id}`.

## Goals / Non-Goals

**Goals:**
- URL canônica `/communication/conversations/{id}` (plural; ID numérico).
- Sync bidirecional seleção ↔ rota sem perder o shell mestre–detalhe.
- Recarregar a URL reabre a conversa após init do workspace.

**Non-Goals:**
- Prefixo de account/office na path (tenant continua `CurrentOffice`).
- Query-string como fonte de verdade.
- Grafia `convesation` (typo do pedido) — usar `conversations`.

## Decisions

1. **Path `/communication/conversations/:id`** — espelha Chatwoot e o recurso REST `conversations`.
2. **Extrair página compartilhada** — `pages/communication/index.vue` e `pages/communication/conversations/[id].vue` montam o mesmo componente/workspace; a rota filha só fornece o `id`.
3. **Router sync no page shell** — ao selecionar → `router.push`; ao limpar → `/communication`; `watch` em `route.params.id` chama `selectConversation`.
4. **Nav `active`** — `path === '/communication' || path.startsWith('/communication/')` (sem `exact: true` exclusivo).

## Risks / Trade-offs

- [ID inválido/403] → toast + redirect para `/communication`.
- [Duplo navigate] — mitigar compare id atual antes de push.
- Vazamento entre offices: API já escopa; URL só dispara o mesmo GET.

## Migration Plan

- Deploy web. Bookmark antigo `/communication` continua válido.
- Rollback: restaurar página única.

## Open Questions

- (nenhuma)
