## 1. N0 — Config e enqueue DETALHE

- [x] 1.1 Adicionar `max_detail_fetches_per_sync` em `config/fiscal_monitoring.php` (default 10)
- [x] 1.2 Implementar serviço/método que, pós-LISTAR, seleciona mensagens `has_body=false` e enfileira DETALHE com idempotência e fail-closed
- [x] 1.3 Integrar o enqueue em `CaixaPostalListAdapter` após `applyList` bem-sucedido
- [x] 1.4 Testes PHP: cap N, fail-closed, idempotência (`php artisan test --filter=Mailbox`)

## 2. N1 — API Feature tests e Content-Type do body

- [x] 2.1 Garantir Content-Type/charset adequado no download de body para preview texto
  - Depende de: 1.1
- [x] 2.2 Feature tests: list/show/triage/body/state/alerts e invariante triagem ≠ leitura oficial
  - Depende de: 1.4

## 3. N1 — UI preview, labels, handoff, alerts

- [x] 3.1 Composable de handoff de filtro de cliente (sem query Nuxt) + consumo em `mailbox.vue`
  - Depende de: 1.1
- [x] 3.2 Preview autenticado do corpo + estado sem corpo + labels pt-BR em `MailboxMail.vue`
  - Depende de: 2.1
- [x] 3.3 Deep-link da aba cliente (botão + linhas) para inbox/detalhe com handoff
  - Depende de: 3.1
- [x] 3.4 Faixa de alerts no topo consumindo `api.fiscal.mailbox.alerts()`
  - Depende de: 3.1
- [x] 3.5 Vitest: handoff/clientId, labels de triagem, preview texto vs erro
  - Depende de: 3.2, 3.3

## 4. N2 — Gates integrados

- [x] 4.1 Gate API: pint --test + `php artisan test --filter=Mailbox`
  - Depende de: 1.4, 2.2
- [x] 4.2 Gate Web: `pnpm run typecheck` + `pnpm run test` (área mailbox)
  - Depende de: 3.5
- [x] 4.3 `openspec validate --specs --strict` + validate da change
  - Depende de: 4.1, 4.2
