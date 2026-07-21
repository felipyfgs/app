## Context

A coluna Pagamento (`PaymentValue.vue`) usa `pgdasdDasPaymentMeta` / `pgdasdPaymentDetailItems` de `apps/web/app/utils/pgdasd.ts`. Para `PAID`, o meta já declara `color: 'success'` e label “Em dia”, mas a descrição humana é longa (“Pagamento do DAS do período esperado localizado até a consulta.”) e o popover monta duas linhas Situação | Detalhe — redundante com a própria badge.

Há changes ativas de domínio PAGTOWEB (`reconciliar-pagamento-pgdasd-com-pagtoweb` e irmãs) que tocam o mesmo contrato de popover; esta change é só polish de UI no web.

## Goals / Non-Goals

**Goals:**

- Copy curto e legível no detalhe/popover quando `payment_state=PAID`.
- Badge da coluna Pagamento visualmente verde (`color: 'success'`) para `PAID`.
- Testes unitários cobrindo label, cor e ausência da frase longa.

**Non-Goals:**

- Alterar resolver PHP, `payment_located`, PAGTOWEB, `payment_open_competencies`.
- Redesign do popover UNPAID (lista de competências).
- Trocar UPopover por UTooltip (mantém o mesmo shell; só limpa o conteúdo PAID).

## Decisions

1. **Copy PAID** → descrição canônica: `Pagamento localizado.`
   - Alternativa rejeitada: frase paralela à Situação (“O pagamento do período esperado foi localizado.”) — ainda longa para o popover.
   - Alternativa rejeitada: remover a linha Detalhe e deixar só “Em dia” — perde o reforço humano curto.

2. **Estrutura do popover** → manter Situação | Detalhe para PAID/NO_DAS/UNVERIFIED; só encurtar o Detalhe de PAID. Não inventar layout novo.

3. **Cor** → reafirmar `color: 'success'` no meta e cobrir com teste (`pgdasdDasPaymentMeta('PAID').color === 'success'`). `PaymentValue.vue` já passa `:color="meta.color"`; sem override CSS.

4. **Ownership** → apenas `apps/web/app/utils/pgdasd.ts` + testes; não editar artefatos/código das changes PAGTOWEB.

## Risks / Trade-offs

- [Copy diverge da change PAGTOWEB futura] → Mitigation: delta desta change exige descrição curta; a change PAGTOWEB MAY acrescentar sufixo humano (“confirmado via PAGTOWEB”) sem restaurar a frase longa atual.
- [Operador espera jargão “DAS / período esperado”] → Mitigation: a badge “Em dia” + Situação no popover bastam; detalhe curto evita tooltip denso.
