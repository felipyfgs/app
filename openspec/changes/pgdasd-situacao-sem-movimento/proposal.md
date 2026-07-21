## Why

O estado `NO_DAS` aparece na coluna Situação como “Sem DAS” com popover em lista Situação/Detalhe e texto técnico. O operador precisa de rótulo de negócio (“Sem movimento”) e um tooltip limpo.

## What Changes

- **BREAKING (copy UI):** label humano de `NO_DAS` passa de “Sem DAS” para “Sem movimento”.
- Tooltip/detalhe de `NO_DAS` fica curto e legível (sem linhas Situação | Detalhe).
- Enum/código de máquina `NO_DAS` permanece inalterado.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-das-payment-column`: label humano `NO_DAS` → Sem movimento.
- `pgdasd-payment-popover-cliente`: detalhe de `NO_DAS` via tooltip/cartão limpo, sem lista Situação/Detalhe.

## Impact

- Web: `apps/web/app/utils/pgdasd.ts`, `PaymentValue.vue`, testes unitários.
- API/contrato JSON: sem mudança (`payment_state` continua `NO_DAS`).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `pgdasd-das-payment-column`, `pgdasd-payment-popover-cliente`
- Depende de: nenhuma
- Desbloqueia: nenhuma
- Paralelismo: pode seguir em paralelo a changes que não toquem copy de Situação/`NO_DAS`

### Non-goals

- Não altera classificação backend de `NO_DAS`.
- Não liga flags SERPRO/MEI nem canais SEFAZ.
- Não muda RBT12, Declaração nem coluna de competências unpaid.
