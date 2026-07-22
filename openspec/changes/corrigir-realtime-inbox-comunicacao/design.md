## Context

O Atendimento usa Laravel Reverb + Echo como gatilho e `GET /api/v1/communication/events?after=` como fonte de verdade. Evidência live: WS transport 101 OK, ledger OK, F5/sync OK, mas auth de canal privado não alinha com REST (`channels.php` exige membership real; `CommunicationAccess` libera Admin/platform privileged via `CurrentOffice`), e o badge verde reflete só o transporte.

## Goals / Non-Goals

**Goals:**
- Workspace inicializa quando `canView` fica verdadeiro (e em troca de sessão).
- Composer preserva rascunho até sucesso do POST.
- Eventos de ledger disparam broadcast imediato pós-commit.
- Eventos WS com cursor numérico disparam sync; conversa selecionada hidrata (inclui receipts da mesma inbox).
- Auth de canal = mesma regra REST; badge só com canal inscrito; poll ≤5s quando canal não pronto.

**Non-Goals:**
- Redesign para aplicar payload WS direto na UI.
- Cobertura whatsmeow (presence/history/ações) de `cobrir-whatsmeow-conversas-1x1`.
- Ligar flags em produção por default; mexer em nginx/Compose Reverb salvo diagnóstico pontual.

## Decisions

1. **Manter WS = gatilho + cursor sync** — alinhado ao contrato existente; menor risco que push-merge de mensagens.
2. **`ShouldBroadcastNow` em `CommunicationEventCommitted`** — elimina dependência de Horizon para o gatilho realtime; ledger/outbox continuam assíncronos.
3. **Init via `watch(canView)` + `sessionEpoch`** no composable — centraliza retentativa.
4. **Clear-on-success no Composer** — limpa só se `sendMessage` retorna `true`.
5. **Hydrate da seleção** — `conversation_id` selecionado ou `inbox_id` da seleção (receipts).
6. **Sem bump otimista de cursor no WS** — o sync usa `after` exclusivo.
7. **`channels.php` delega a `CommunicationAccess`** — uma única regra de visibilidade; platform privileged com Office ativo correto autoriza.
8. **Estado `connected` só com `.subscribed()`** — transporte sozinho → `connecting`/`unavailable`.
9. **Poll 5s fallback** enquanto workspace montado sem canal pronto — UI atualiza mesmo se auth/WS falhar.

## Risks / Trade-offs

- [Broadcast síncrono sob carga] → Mitigação: payload pequeno; sync/reconexão cobrem.
- [Poll aumenta carga REST] → Mitigação: só sem canal pronto; intervalo 5s; para ao inscrever.
- [Platform privileged em canal] → Mitigação: exige `CurrentOffice` do middleware (mesmo Office da API); isolamento por office_id da inbox.
- Vazamento entre offices / flags ON / mei Compose: fora de escopo; fail-closed permanece.

## Migration Plan

- Deploy usual (API + web). Sem migration DB.
- Rollback: reverter `channels.php`/plugin/workspace; sync manual continua.

## Open Questions

- (nenhuma bloqueante)
