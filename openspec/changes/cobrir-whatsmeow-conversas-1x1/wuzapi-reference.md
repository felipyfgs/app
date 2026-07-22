# Referência WuzAPI para cobertura whatsmeow 1:1

## Proveniência

- Repositório: `https://github.com/asternic/wuzapi`
- Commit auditado: `70642149a0e8a81d49caa640f557217e03e09729` (2026-07-01)
- Licença: MIT; qualquer adaptação substancial deverá preservar atribuição compatível.
- whatsmeow usado pelo WuzAPI: `v0.0.0-20260516102357-8d3700152a69`.
- whatsmeow usado pelo hub: `v0.0.0-20260721154117-8b4a8ba0d318`.

WuzAPI é somente referência de leitura. O hub não adicionará seu binário, banco, dashboard, webhooks, RabbitMQ ou S3 como dependência.

## Superfície 1:1 observada

| Família | Rotas/recursos no WuzAPI | Métodos whatsmeow observados | Decisão no hub |
|---|---|---|---|
| Sessão | connect, disconnect, logout, status, QR, pair phone, proxy, history | `Connect`, `Disconnect`, `Logout`, `IsConnected`, `IsLoggedIn`, `GetQRChannel`, `PairPhone`, proxy setters, history builders | Adaptar por comandos/queries HMAC e manter lease/device store do baseline |
| Mensagem | text, image, audio, document, video, sticker, location, contact, poll, buttons, list | `GenerateMessageID`, `Upload`, `SendMessage`, `BuildPollCreation` | Laravel fornece ID; mídia somente por stream privado; DTOs allowlisted |
| Ações | delete, edit, react, unavailable, archive | `BuildRevoke`, `BuildEdit`, `BuildUnavailableMessageRequest`, `SendAppState` | Adaptar para 1:1 e validar ownership/provider IDs |
| Presença/receipts | global presence, subscribe, chat presence, mark read | `SendPresence`, `SubscribePresence`, `SendChatPresence`, `MarkRead` | Adaptar com enum fechado, TTL/deadline e evento sanitizado |
| Usuário | check, info, avatar, contacts, LID | `IsOnWhatsApp`, `GetUserInfo`, `GetProfilePictureInfo` e stores | Expor somente dados necessários e sempre office/session-scoped |
| Segurança de contato | block, unblock, blocklist, privacy | `UpdateBlocklist`, `GetBlocklist`, `TryFetchPrivacySettings`, `SetPrivacySetting` | Reusar matriz de validação e resolução LID→PN; exigir permissão administrativa |
| Inbound | mensagens, poll/edit decrypt, receipts, presence, history, app-state, sessão, retry, perfil, segurança | `DecryptPollVote`, `DecryptSecretEncryptedMessage`, event handler | Traduzir para envelopes próprios; nunca encaminhar evento bruto |

Fora do recorte: todas as rotas `/group/*`, `/newsletter/list`, `/status/set/text` e `/call/reject`.

## Padrões aproveitáveis

- Builders de mensagem para PTT/áudio, thumbnails em memória, sticker, localização, contato, poll, edit/revoke e reaction.
- Poll vote: guardar opções canônicas e resolver hashes após `DecryptPollVote`.
- Edit inbound: aplicar `DecryptSecretEncryptedMessage` antes da classificação.
- Privacidade: matriz fechada de nomes/valores que o setter round-tripa corretamente.
- Blocklist: normalizar legacy server, converter LID para PN pelo store e falhar se o mapping não existir.
- History: distinguir `FromMe`, extrair quote/reaction e deduplicar por message ID.
- Eventos operacionais adicionais: keepalive, outdated client, temporary ban, stream error e media retry.

## Padrões rejeitados ou corrigidos

- `postmap["event"] = rawEvt`: acoplamento e possível exposição de campos sensíveis; usar DTO allowlisted.
- Log de `PairSuccess` contendo token: proibido; logar somente IDs técnicos não reversíveis.
- Base64 e fetch de URL arbitrária para mídia: risco de memória/SSRF; usar stream autenticado ligado ao command ID.
- S3/public URL como entrega de mídia: contradiz vault/spool privado do hub.
- `context.Background()` em handlers: usar o contexto do comando/query com deadline.
- Webhook sem ledger equivalente ao baseline: manter event outbox at-least-once e ACK antes de apagar spool.
- Token bearer como única proteção: manter HMAC versionado, nonce e janela em ambas as direções.
- Evento raw persistido como JSON: persistir apenas projeção de negócio e metadados sanitizados.
- History automático incluindo grupos: filtrar estritamente JIDs 1:1 e impor limite/cursor.
- Gerar message ID dentro do adapter: preservar `provider_message_id` criado pelo Laravel em retries.

## Cobertura comparativa

- WuzAPI chama 55 dos 135 métodos públicos do `Client`; parte relevante é de grupos/newsletters/status.
- O event handler do WuzAPI possui 38 cases/famílias, incluindo grupos, canais, FB e calls.
- O baseline do hub chama diretamente dez métodos e trata cinco eventos.
- O WuzAPI ajuda principalmente nas famílias message builders, user/privacy/blocklist e event parsing; não substitui o catálogo completo nem as garantias operacionais do hub.
