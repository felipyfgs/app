## Context

A vertical Caixa Postal já existe (`Integra/Mailbox/*`, `MailboxMessageController`, inbox Nuxt). LISTAR persiste metadados; DETALHE grava corpo no vault via consult manual. A UI baixa o corpo com `window.open` e não faz preview. `monitoring-url-canonical` exige path-only em `/monitoring/mailbox` — filtros não podem ir na query Nuxt.

## Goals / Non-Goals

**Goals:**
- Enfileirar DETALHE automaticamente após LISTAR (até N mensagens sem corpo).
- Preview autenticado do corpo + estado sem corpo + labels pt-BR.
- Deep-link cliente → inbox com filtro via handoff em memória.
- Faixa de alerts (`GET /alerts`) no topo da página.
- Testes Feature/Unit/Vitest na mesma change.

**Non-Goals:**
- Anexos oficiais SERPRO (catálogo DETALHE sem campos de anexo).
- Query string de filtros na URL Nuxt.
- Marcar leitura oficial / dismiss de alerts / busca `q`.
- Ligar `FEATURE_MAILBOX_*` em produção.

## Decisions

1. **Enqueue pós-LISTAR no adapter** — Após `applyList` com sucesso, serviço dedicado seleciona até `max_detail_fetches_per_sync` (default 10) mensagens `has_body=false` (unread/recent first) e enfileira runs `DETALHE` via `FiscalMonitoringRunService`, espelhando `mailbox_detail` do ManualConsult. Alternativa rejeitada: DETALHE síncrono na mesma run (estoura bilhetagem/timeout).

2. **Idempotência** — Não reenfileirar se já houver run pendente/ativa para o mesmo office+client+`isn`/external_id.

3. **Fail-closed** — Sem módulo/flag mailbox habilitados, zero enqueue.

4. **Preview UI** — `fetch` autenticado do endpoint body (credentials/cookies do client API); render `text/plain` com `whitespace-pre-wrap`; HTML só com sanitização mínima ou fallback download. Alternativa rejeitada: `window.open` (não preenche o painel).

5. **Deep-link sem query** — Composable/handoff (sessionStorage ou estado módulo) setado na aba cliente antes de `navigateTo('/monitoring/mailbox')`; a página mailbox consome e limpa o handoff. Respeita `monitoring-url-canonical`.

6. **Alerts** — Só leitura; sem API de dismiss nesta change.

7. **Enable path (ops)** — Documentar: `FEATURE_MAILBOX_ENABLED`, driver SERPRO real, procuração e-CAC poder 00006, egress gate. Defaults OFF.

## Risks / Trade-offs

- [Bilhetagem SERPRO] → Cap `N` por sync + idempotência; N configurável.
- [Corpo sensível em UI] → Preview só para usuário autenticado com permissão de leitura; audit `VIEW`/`DOWNLOAD` já existente; sem embutir bytes no JSON de list/show.
- [Handoff perdido em hard refresh] → Aceitável; operador reabre pelo cliente.
- [DETALHE falha] → Mensagem permanece `has_body=false`; UI mostra “Corpo ainda não sincronizado”.

## Migration Plan

- Deploy código com flag OFF (comportamento idêntico exceto enqueue só quando módulo ativo).
- Rollback: reverter change; runs DETALHE já enfileiradas completam ou falham isoladas.
- Sem migration de schema.

## Open Questions

- Nenhuma bloqueante; campos de anexo SERPRO futuros tratados em change separada.
