## Why

Na coluna Pagamento da carteira PGDAS-D, o estado `PAID` (“Em dia”) ainda abre um popover verboso (Situação + Detalhe com frase longa sobre “DAS do período esperado…”) e o sinal visual de sucesso precisa ficar inequívoco: badge verde. Operadores precisam de um feedback limpo quando o pagamento está em dia — sem jargão técnico no detalhe.

## What Changes

- Encurtar o copy humano do estado `PAID` (descrição do meta / detalhe do popover).
- No popover Pagamento com `payment_state=PAID`, apresentar detalhe limpo (sem frase longa técnica; sem reason codes).
- Garantir que a badge da coluna Pagamento use cor `success` (verde) quando o estado for `PAID` / label “Em dia”.
- Sem mudança de resolver, contrato de API, nem regra de derivação do `payment_state`.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-das-payment-column`: badge `PAID` MUST usar cor de sucesso (verde) na coluna Pagamento.
- `pgdasd-payment-popover-cliente`: quando `PAID`, popover MUST usar descrição humana curta/limpa (não a frase longa atual sobre “DAS do período esperado localizado até a consulta”).

## Impact

- Web: `apps/web/app/utils/pgdasd.ts` (PAYMENT_META), eventual ajuste em `PaymentValue.vue`, testes unitários `pgdasd.test.ts`.
- API: nenhuma.
- Non-goals: conciliação PAGTOWEB; alteração de precedência do `payment_state`; live SERPRO; parecer jurídico; mutações fiscais; flags ON; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: specs `pgdasd-das-payment-column`, `pgdasd-payment-popover-cliente`
- Depende de: nenhuma
- Desbloqueia: UX clara de “Em dia” na coluna Pagamento
- Paralelismo: ownership = copy/cor da badge e popover PAID no web; não editar resolver/API das changes `reconciliar-pagamento-pgdasd-com-pagtoweb`, `pgdasd-pa-pago-qualquer-das`, `persist-pgdasd-operation-das-amount`, `enrich-pgdasd-payment-open-amounts`. Coordenação só no delta de `pgdasd-payment-popover-cliente` (esta change restringe copy PAID; a change PAGTOWEB MAY acrescentar menção humana a PAGTOWEB depois).
