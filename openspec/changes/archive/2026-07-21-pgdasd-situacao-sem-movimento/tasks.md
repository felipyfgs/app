## 1. Copy e meta

- [x] 1.1 Em `pgdasd.ts`, `NO_DAS`: label “Sem movimento” e description curta
- [x] 1.2 Atualizar testes unitários de label/`pgdasdPaymentDetailItems` para `NO_DAS`

## 2. UI Situação

- [x] 2.1 Em `PaymentValue.vue`, `NO_DAS`: `UTooltip` limpo + cartão curto no popover (como Em dia), sem lista Situação/Detalhe

## 3. Verificação

- [x] 3.1 `pnpm run test` (filtro pgdasd) e `openspec validate --change pgdasd-situacao-sem-movimento --strict`
