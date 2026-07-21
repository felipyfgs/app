## Why

A Situação PGDAS-D da carteira passou a significar só a **entrega do PA**. O escritório ainda precisa ver se há DAS/guia em aberto na Receita (`dasPago` / `payment_located`) sem misturar isso com a entrega da declaração.

## What Changes

- Nova coluna **Pagamento** na carteira PGDAS-D (spine), derivada só de evidência local dos DAS do PA esperado.
- Enum/estado operacional de pagamento: `PAID` · `UNPAID` · `NO_DAS` · `UNVERIFIED`, com labels Em dia / Pendências / Sem DAS / Não verificado.
- API enriquece o resumo PGDAS do portfolio com `payment_state` (+ motivo/contagem opcional).
- Situação de entrega permanece intocada (não funde eixos).
- Sem live SERPRO; sem alterar hub Guias além do que a carteira já consome localmente.

## Capabilities

### New Capabilities

- `pgdasd-das-payment-column`: coluna Pagamento da carteira PGDAS-D e contrato do estado de pagamento do DAS do PA esperado.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — spine PGDAS atualizada no contrato novo)

## Impact

- API: `PgdasdMonitoringQueryService` (batch), enum novo, eventual sort subquery na carteira; testes unitários.
- Web: `pgdasd-table.ts`, `pgdasd.ts`, types; testes unitários.
- DB: sem migration obrigatória (usa `pgdasd_operations.payment_located` existente); opcional cache em projeção só se performance exigir (fora do MVP).
- Non-goals: malha, MAED, fundir com Situação, materializar `tax_guides`, live SERPRO, flags ON.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: `pgdasd_operations.payment_located` (CONSDECLARACAO13)
- Depende de: `pgdasd-situacao-entrega-pa` (capability `pgdasd-pa-delivery-situation`, marco `apply`, relação `coordenada` — Situação permanece só entrega)
- Desbloqueia: nenhuma
- Paralelismo: não editar artefatos/código de Situação da change irmã além de ler o contrato
