## 1. N0 — Backend broadcast imediato

- [x] 1.1 Trocar `CommunicationEventCommitted` para `ShouldBroadcastNow` mantendo `$afterCommit`
- [x] 1.2 Ajustar/estender Feature test de communication para cobrir broadcast imediato (sem depender da fila `default`)
  - Evidência: `docker compose exec -T php php artisan test --filter=CommunicationApiTest`

## 2. N0 — Frontend init e composer

- [x] 2.1 Em `useCommunicationWorkspace`, inicializar via `watch(canView)` / `sessionEpoch` (idempotente; reinicia em troca de sessão)
- [x] 2.2 Composer clear-on-success: limpar rascunho só quando `sendMessage` retorna `true` (`Composer.vue` + `communication.vue`)

## 3. N1 — Hydrate e cursor realtime

- [x] 3.1 Normalizar cursor WS (number|string) e disparar `synchronize` (sem bump otimista — `after` é exclusivo)
  - Depende de: 2.1
- [x] 3.2 Em `hydrateFromEvents`, sempre recarregar conversa selecionada tocada pelo evento e preservar/merge na lista mesmo fora do filtro OPEN
  - Depende de: 2.1

## 4. N1 — Testes unitários web

- [x] 4.1 Cobrir init quando `canView` vira true, cursor string dispara sync e contrato clear-on-success
  - Depende de: 2.1, 2.2, 3.1, 3.2
  - Evidência: `pnpm run test -- tests/unit/communication.test.ts` (e arquivo novo se necessário)

## 5. N2 — Gates integrados (fase 1)

- [x] 5.1 Validar OpenSpec da change e gates da área tocada (API pint/test filtro + web lint/typecheck/test communication)
  - Depende de: 1.2, 4.1
  - Evidência: `npx @fission-ai/openspec@1.6.0 validate --changes --strict` (ou equivalente da change); testes da área

## 6. N0 — Auth de canal = REST

- [x] 6.1 Expor `canAccessInbox` / broadcast helpers em `CommunicationAccess` e reescrever `routes/channels.php`
- [x] 6.2 Feature tests: platform privileged autoriza; operador sem membership nega; admin do office autoriza
  - Evidência: `docker compose exec -T php php artisan test --filter=CommunicationApiTest`

## 7. N1 — Client canal honesto + poll

- [x] 7.1 Estado realtime só `connected` com canal `.subscribed()`; force resubscribe no reconnect; `dispose` reseta init
  - Depende de: 6.1
- [x] 7.2 Poll `synchronize()` a cada 5s enquanto workspace montado sem canal pronto; hydrate receipts por `inbox_id`
  - Depende de: 7.1
- [x] 7.3 Atualizar testes unitários web (contrato plugin/workspace)
  - Depende de: 7.1, 7.2
  - Evidência: `pnpm exec vitest run tests/unit/communication.test.ts`

## 8. N2 — Gates fase 2

- [x] 8.1 Pint/test API + vitest/eslint communication + openspec validate change
  - Depende de: 6.2, 7.3
