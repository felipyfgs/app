## 1. N0 — Catálogo, contrato comum e escopo

- [x] 1.1 Versionar manifests dos 135 métodos e 74 eventos com disposição/owner/evidência, implementar teste de reflexão e referências de compilação e reconciliar `catalog.md` sem entrada ausente ou duplicada (`make gateway-test`)
  Depende de: change externa `adicionar-comunicacao-whatsapp-nativa` no marco `apply`
- [x] 1.2 Evoluir de forma backward-compatible o OpenAPI, `GatewayCommandType`/`GatewayEventType`, DTOs Go/PHP e schemas de payload/query para as famílias 1:1, com testes de contrato estrito e HMAC/replay também em queries (`make gateway-test`; `php artisan test --filter=GatewayContract` via Compose)
  Depende de: change externa `adicionar-comunicacao-whatsapp-nativa` no marco `apply`
- [x] 1.3 Implementar normalizador/allowlist central PN/LID e rejeição pré-efeito de group, community, newsletter, broadcast/status e server desconhecido, com testes negativos cobrindo comando, query e evento (`make gateway-test`)
  Depende de: change externa `adicionar-comunicacao-whatsapp-nativa` no marco `apply`

## 2. N1 — Cobertura whatsmeow no gateway

- [x] 2.1 Implementar ciclo de sessão cancelável: `ConnectContext`, readiness, login state, reset, pair phone/passkey, passive, cleanup de handlers e HTTP/proxy/retry settings seguros; testar transições, redaction e lease ownership (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.2 Implementar envio tipado de text/preview/quote, image, audio/PTT, video, document, sticker, location, contact, poll e tipos interativos suportados, usando upload streaming e IDs do Laravel; testar protobufs, MIME, limites e idempotência (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.3 Implementar edit, revoke, reaction/remove, poll vote, message key, read/played, disappearing timer e unavailable request por builders/peer corretos; testar alvo 1:1, provider IDs e falhas sem fallback (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.4 Implementar presença global, subscribe presence e chat presence com enums/TTL, além de receipts ativos/protocolares, com testes de sinais sanitizados e ausência de mensagem durável (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.5 Implementar query executor para `IsOnWhatsApp`, user/business profile, avatar, contact/business links, QR de contato e primitives de device somente internas; testar limites, schemas sanitizados, deadline e replay (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.6 Implementar blocklist com LID→PN, privacy matrix/cache/default timer e app-state allowlisted de archive/mute/pin/star/delete/mark-read, com testes inspirados e atribuídos ao WuzAPI (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.7 Implementar history sync/download/delete, parse de web message, media retry/server-error receipt, thumbnail e sticker pack sob limites 1:1, com deduplicação e testes de recovery (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3
- [x] 2.8 Completar event bridge para mensagens/actions, decriptação de poll/edit, history/app-state, presence, profile/identity, privacy/blocklist, media retry e estados operacionais, provando que raw event e segredos nunca entram no payload (`make gateway-test`)
  Depende de: 1.1, 1.2, 1.3

## 3. N2 — Projeção Laravel e experiência de atendimento

- [x] 3.1 Estender transport/outbox/worker Laravel para comandos mutáveis novos e queries HMAC, com autorização por Office/inbox e testes Feature de replay, kill switch e tenant isolation (`php artisan test --filter=CommunicationGateway` via Compose)
  Depende de: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8
- [x] 3.2 Evoluir `MessageKind`, relações de quote/action/poll e ingestão de eventos/history de forma aditiva e idempotente, com migration quando necessária e testes de status/conversa resolvida (`php artisan test --filter=Communication` via Compose)
  Depende de: 2.2, 2.3, 2.7, 2.8
- [x] 3.3 Expor APIs tenant-scoped para ações de conversa e controles administrativos, separando `communication.reply` de `communication.manage_inboxes`, com testes negativos por permissão/Office (`php artisan test --filter=Communication` via Compose)
  Depende de: 2.3, 2.4, 2.5, 2.6, 2.8
## 4. N3 — UX e fechamento do catálogo

- [x] 4.1 Atualizar timeline/composer/contexto Nuxt para tipos, quote, reaction/edit/revoke, poll e sinais efêmeros necessários, alinhado ao arquétipo e com testes unitários/fidelity/artifacts da superfície (`pnpm run lint`; `pnpm run typecheck`; `pnpm run test` via Compose)
  Depende de: 3.1, 3.2, 3.3
- [x] 4.2 Atualizar manifests/catálogo de cada método/evento de `PENDING` para `IMPLEMENTED` somente com path e teste reais, registrar atribuições WuzAPI utilizadas e provar zero gaps no auditor (`make gateway-test`)
  Depende de: 3.1, 3.2, 3.3

## 5. N4 — Gates integrados e prontidão

- [x] 5.1 Rodar gates completos da API, gateway e Web afetados (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test`, `make gateway-test`, `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e registrar evidência
  Depende de: 4.1, 4.2
- [x] 5.2 Validar Compose dev/prod, ausência de `mei`/`mei-worker`, OpenSpec estrito e auditoria final 135/74 sem `PENDING`, sem chamar WhatsApp/SERPRO live e sem habilitar flags (`docker compose config --quiet`; `docker compose -f docker-compose.prod.yml config --quiet`; `npx @fission-ai/openspec@1.6.0 validate cobrir-whatsmeow-conversas-1x1 --strict`)
  Depende de: 4.1, 4.2
