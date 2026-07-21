## Context

`openPaymentCompetencies` agrupa DAS por `client_id|period_key`, exclui PA com qualquer `pagtoweb_payment_status=PAID`, exige todos `NOT_FOUND` frescos, resolve valor por DAS (`pagtoweb_amount_cents` → tax_guides → operation → GERAR_DAS) e **soma** os cents. Em PAs com várias gerações de DAS (reemissão), o mesmo facial aparece N vezes → inflação (Brito 06/2026: 5×14125).

A change `pgdasd-pa-pago-qualquer-das` já trata quitação “any paid”; o montante unpaid ainda herda a soma do enrich.

## Goals / Non-Goals

**Goals:**
- `amount_cents` do PA unpaid = débito representativo (não N× reemissões de mesmo/maior facial).
- Popover: valor em débito com token visual de erro (`text-error` / equivalente semântico).
- Manter fail-closed: qualquer DAS unpaid sem valor resolvido → `amount_cents=null` (`—`).

**Non-Goals:**
- Inferir quitação via SITFIS; live SERPRO no GET; alterar badge PAID; redesign completo do popover.

## Decisions

1. **Agregação = máximo, não soma**  
   Entre DAS unpaid do PA com `amount_cents` resolvido, usar `max(valores)`. Justificativa: reemissões do mesmo PA costumam repetir o facial (ou o mais alto com acréscimos); a soma conta o mesmo débito várias vezes.  
   Alternativa rejeitada: “último DAS por `id`” — frágil se ordem de ingestão variar.  
   Alternativa rejeitada: dedupe só quando todos iguais — o max cobre iguais e escolhe o maior quando há variação leve (juros/reemissão).

2. **Fail-closed de nulls inalterado**  
   Se algum DAS unpaid do PA não tiver valor resolvido → competência com `amount_cents=null` (não inventar a partir do max parcial).

3. **UI débito**  
   Em `PaymentValue.vue`, linhas do popover unpaid com valor monetário usam `text-error`; `—` permanece `text-highlighted`/`text-muted` neutro.

## Risks / Trade-offs

- [Dois débitos reais distintos no mesmo PA] → max pode subestimar. Mitigação: raro no PGDAS (um PA / um principal); SITFIS continua visão de débitos apurados; evoluir depois se GERARDASCOBRANCA exigir soma.
- [Operador acostumado à soma] → copy visual “débito” em vermelho deixa claro que é indicativo do PA, não total de guias.

## Mapa de dependências

| Upstream | Capability | Marco | Relação |
|----------|------------|-------|---------|
| `reconciliar-pagamento-pgdasd-com-pagtoweb` | cobertura PAGTOWEB | `apply` | `coordenada` |
| `enrich-pgdasd-payment-open-amounts` | resolução amount | `apply` | `coordenada` |

- Nível: **C1**
- Ownership: `openPaymentCompetencies` + `PaymentValue.vue`
- Rollout: API + web. Rollback: voltar `+=` soma; remover classe erro.
