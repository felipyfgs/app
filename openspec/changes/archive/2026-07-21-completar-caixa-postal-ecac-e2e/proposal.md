## Why

A Caixa Postal e-CAC já tem schema, adapters SERPRO, vault, API REST e UI inbox, mas o e2e de conteúdo está incompleto: LISTAR grava só metadados, DETALHE (corpo) depende de consult manual, e a UI só oferece download sem preview. Operadores não conseguem triar mensagens com o corpo visível nem abrir a caixa filtrada a partir do cliente.

## What Changes

- Após LISTAR bem-sucedido, enfileirar até N runs DETALHE para mensagens sem corpo (`has_body=false`), com config e idempotência.
- UI: preview autenticado do corpo no detalhe (arquétipo Inbox), estado “corpo ainda não sincronizado”, labels de triagem em pt-BR.
- Deep-link da aba cliente → inbox com filtro de cliente via handoff em memória (sem query na URL Nuxt).
- Consumir `GET /alerts` numa faixa compacta no topo da página mailbox.
- Testes Feature/Unit (API) e Vitest (web) cobrindo sync, API e UI.
- Capability OpenSpec nova documentando o contrato mailbox.

Non-goals: anexos oficiais SERPRO (catálogo DETALHE não documenta), busca `q`, marcar leitura oficial na RFB, dismiss de alerts, ligar flags de produção, adapter `caixa_postal.indicador` separado, mutar `monitoring-url-canonical` para aceitar query de filtros.

## Capabilities

### New Capabilities
- `mailbox-caixa-postal`: contrato e2e da Caixa Postal (sync LISTAR→DETALHE, API de leitura/triagem/corpo/alerts, UI inbox com preview e deep-link de cliente sem query Nuxt).

### Modified Capabilities
- (nenhuma) — deep-link de cliente respeita `monitoring-url-canonical` (path-only); filtros permanecem estado local + handoff.

## Impact

- API: `CaixaPostalListAdapter`, `FiscalMonitoringRunService` (enqueue DETALHE), `config/fiscal_monitoring.php`, testes Feature/Unit mailbox.
- Web: `mailbox.vue`, `MailboxMail.vue`, aba mailbox em `clients/[clientId].vue`, handoff de filtro, Vitest.
- Ops: path de enable (`FEATURE_MAILBOX_*`, driver SERPRO, procuração) documentado em design — defaults continuam fail-closed.

### Dependências entre changes
- Nível: **C0**
- Bases estáveis: `monitoring-url-canonical`, `monitoring-insights-dashboard` (archive/main)
- Depende de: nenhuma
- Desbloqueia: uso operacional da caixa com corpo sincronizado
- Paralelismo: pode correr em paralelo com changes de outros módulos (sitfis/pgdasd) sem overlap de ownership
