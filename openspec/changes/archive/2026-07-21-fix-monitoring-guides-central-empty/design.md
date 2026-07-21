## Context

`ClientGuidesQueryService` já une tax_guides + DAS + DARF por `client_id`. A central chama a lista sem `client_id` e cai em `GuideQueryService` (só tax_guides). O KPI usa overview de carteira de clientes.

## Goals / Non-Goals

**Goals:** lista office-wide unificada; KPI = contagem de guias por pagamento; ações seguras para IDs virtuais.

**Non-Goals:** materializar tax_guides; redesign carteira; SERPRO.

## Decisions

1. Generalizar `paginate(Office, ?int $clientId, …)` — null = office inteiro.
2. `TaxGuideController::index` sempre usa esse serviço.
3. Incluir `payment_counters` no JSON da lista (totais office-wide antes da paginação, após filtro payment_status opcional? → contadores **sem** filtro de payment para o strip refletir o universo; filtro só afeta `data`).
4. Frontend: `guideCounters` mapeados CONFIRMED→up_to_date, NOT_CONFIRMED→pending, UNKNOWN→unknown, PARTIAL→attention; não passar `total-clients` do overview de clientes.
5. KPI click: mapear situation → payment_status (UP_TO_DATE→CONFIRMED, PENDING→NOT_CONFIRMED, UNKNOWN→UNKNOWN, ATTENTION→PARTIAL).
6. Ações: só `Number.isFinite(id)` para detalhe/download; virtual → botão Cliente.

## Risks / Trade-offs

- [Memória office-wide] → aceitável para volumes atuais (~centenas); paginação em memória.
- [KPI situation vs payment] → mapeamento documentado; labels do strip permanecem Em dia/Pendências.
