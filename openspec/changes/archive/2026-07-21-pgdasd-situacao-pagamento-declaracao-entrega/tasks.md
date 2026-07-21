## 1. Colunas PGDAS-D (N0)

- [x] 1.1 Em `pgdasd-table.ts`: coluna Situação renderiza pagamento (`PaymentValue`) + override Sem procuração; tooltip do header esclarece pagamento DAS; colapsar sem evidência para `—` (sem “Não verificado”)
- [x] 1.2 Em `pgdasd-table.ts`: remover coluna Pagamento; renomear header Últ. Declaração → Declaração; enriquecer tooltip/aria de entrega no `DeclarationIndicator`
- [x] 1.3 Ajustar `DeclarationIndicator.vue` / `PaymentValue.vue` se necessário (test ids, copy, popover só para estados de negócio)

## 2. Testes e contrato (N1, depende de 1.x)

- [x] 2.1 Atualizar `monitoring-portfolio-columns.test.ts` para ordem Situação · Declaração · RBT12 · Cliente (sem payment) e header Declaração
- [x] 2.2 Ajustar testes unitários `pgdasd.test.ts` / labels se copy ou meta de pagamento mudarem na UI

## 3. Gates (N2, depende de 2.x)

- [x] 3.1 `pnpm run test` (ou subset unitário tocado) em `apps/web` + `openspec validate --specs --strict` com a change ativa
