## 1. N0 — Contrato de envio e mídia privada

- [x] 1.1 Evoluir `SendMessageRequest` e o controller para corpo opcional com mídia, `kind`, `ptt`, quote remoto, digest idempotente completo e validação MIME/tipo, com Feature tests de texto, áudio, sticker, citação e rejeições (`php artisan test --filter=CommunicationApiTest` via Compose).
  Depende de: changes `adicionar-comunicacao-whatsapp-nativa`, `cobrir-whatsmeow-conversas-1x1` e `sincronizar-outbound-celular-chat-silencioso` no marco `apply`.
- [x] 1.2 Preservar filename inbound e expor `filename`, `preview_url` e stream inline tenant-scoped para imagem/áudio/vídeo, mantendo download e testes de acesso cross-Office (`php artisan test --filter=Communication` via Compose).
  Depende de: change `adicionar-comunicacao-whatsapp-nativa` no marco `apply`.
- [x] 1.3 Corrigir `ContextInfo` de citação 1:1 no gateway, validar PTT/sticker e ampliar testes typed para quote inbound/outbound e MIME incompatível (`make gateway-test`).
  Depende de: change `cobrir-whatsmeow-conversas-1x1` no marco `apply`.

## 2. N1 — Interações do composer e identidade do cliente

- [x] 2.1 Criar helpers testáveis de Enter/Shift+Enter/IME, MIME/extensão do recorder e identidade cliente/contato, com Vitest cobrindo fallbacks e nomes (`pnpm run test -- communication`).
  Depende de: 1.1.
- [x] 2.2 Refatorar o composer para envio com Enter, emoji no texto, anexo, sticker WebP e gravação/cancelamento de áudio com cleanup, limite e presença `RECORDING`, propagando `kind`/`ptt` pelo API client/workspace (`pnpm run lint`, `pnpm run typecheck`, testes unitários).
  Depende de: 1.1, 2.1.
- [x] 2.3 Fazer busca/lista/header priorizarem o nome do cliente fiscal e exibirem contato/telefone como contexto, com teste Feature de busca tenant-scoped e Vitest de fallbacks.
  Depende de: 2.1.

## 3. N2 — Timeline rica e ações bidirecionais

- [x] 3.1 Refatorar `MessageContent` para preview autenticado de imagem/sticker, players de áudio/vídeo e cartão de documento com filename/download, cobrindo mídia inbound e outbound em gate unitário/fidelity.
  Depende de: 1.2, 2.1.
- [x] 3.2 Evoluir ações da timeline com popover de emojis, remover reação, quote navegável, edição e revogação com estados assíncronos honestos, preservando regras OUTBOUND/INBOUND e testes Feature/Vitest.
  Depende de: 1.1, 1.3, 2.1.
- [x] 3.3 Refinar densidade, foco, responsividade e estados vazios/loading/error da lista, timeline e composer sem alterar o shell `UDashboardPanel` nem criar overflow mobile, atualizando fidelity/artifacts.
  Depende de: 2.2, 2.3, 3.1, 3.2.

## 4. N3 — Verificação integrada

- [x] 4.1 Rodar gates completos da API e gateway (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `php artisan test`, `make gateway-test`, `go vet` quando disponível) e corrigir regressões.
  Depende de: 1.1, 1.2, 1.3, 3.2.
- [x] 4.2 Rodar gates completos Web (`pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e corrigir regressões.
  Depende de: 2.2, 2.3, 3.1, 3.2, 3.3.
- [x] 4.3 Validar Compose dev/prod, ausência de `mei`/`mei-worker`, specs e change OpenSpec strict, mantendo flags de comunicação OFF e sem egress live.
  Depende de: 4.1, 4.2.
