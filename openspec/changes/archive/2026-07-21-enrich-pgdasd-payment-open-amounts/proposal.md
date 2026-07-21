## Why

No popover Pagamento PGDAS-D, competências unpaid quase sempre mostram `—`: o MONITOR (CONSDECLARACAO13) não traz valor, e o enrich atual só lê `tax_guides`, que raramente existe. Já há valor em evidências locais de GERAR_DAS (`dados.total`) que o portfolio não consulta.

## What Changes

- Enriquecer `payment_open_competencies[].amount_cents` com fallback local: evidência GERAR_DAS bem-sucedida do office (`dados.numeroDocumento` ↔ `das_number`, `dados.total` → cents).
- Manter `tax_guides.amount_cents` como fonte preferencial quando existir.
- Sem inventar valor quando nenhuma fonte local resolver; sem live SERPRO.

Non-goals: parse de PDF/extrato; usar RBT12 como proxy; materializar `tax_guides` nesta change; badges/KPI; flags ON.

## Capabilities

### New Capabilities

- `pgdasd-payment-open-amount-enrichment`: resolução de `amount_cents` nas competências unpaid do portfolio via `tax_guides` e, se ausente, evidência GERAR_DAS local.

### Modified Capabilities

<!-- nenhuma — contrato de lista/popover fica na change irmã; esta change só define o enrich do valor -->

## Impact

- API: `PgdasdMonitoringQueryService::openPaymentCompetencies` (+ helper de leitura de evidência GERAR_DAS); testes Feature do enrich.
- Web: sem mudança obrigatória (já formata cents / `—`).
- Vault/evidência: leitura office-scoped; não logar payload bruto nem segredos.

### Dependências entre changes

- Nível: `C2`
- Bases estáveis: main `pgdasd-das-payment-column` (badge/estado)
- Depende de: `pgdasd-pagamento-e-cnpj-cliente`
  - Capability/contrato: `pgdasd-payment-popover-cliente` (`payment_open_competencies`)
  - Marco exigido: `apply`
  - Relação: `bloqueante`
- Desbloqueia: valores monetários no popover quando houver GERAR_DAS local
- Paralelismo: não editar artefatos da change irmã; só estender `openPaymentCompetencies` após o contrato da lista existir no código
