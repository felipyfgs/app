## 1. N0 — Copy e cor PAID

- [x] 1.1 Em `apps/web/app/utils/pgdasd.ts`, definir descrição de `PAYMENT_META.PAID` como `Pagamento localizado.` e garantir `color: 'success'`
- [x] 1.2 Em `apps/web/tests/unit/pgdasd.test.ts`, assertir label “Em dia”, `color === 'success'` e detalhe do popover PAID curto (sem a frase longa antiga)

## 2. N1 — Gates web / OpenSpec

- [x] 2.1 Rodar `pnpm run test -- tests/unit/pgdasd.test.ts` em `apps/web`
  Depende de: 1.1, 1.2
- [x] 2.2 Validar change: `npx @fission-ai/openspec@1.6.0 validate --changes --strict` (ou equivalente do change `pgdasd-pagamento-em-dia-ui`)
  Depende de: 1.1, 1.2
