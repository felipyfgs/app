## Why

O popover Pagamento lista competências unpaid, mas `amount_cents` fica `—`: MONITOR não traz valor, e o enrich só por `tax_guides`/GERAR_DAS quase nunca encontra fonte. Os PDFs CONSEXTRATO16 já locais trazem na seção 6 o Total da última guia emitida (`Número` + `Total`); o valor precisa ser persistido no ingest Integra (como RBT12) e só lido na carteira — sem parse na web nem SERPRO no GET do portfolio.

## What Changes

- Persistir montante por `das_number` em `pgdasd_operations` (`amount_cents`, `amount_source`, metadados de parser/auditoria).
- No pós-consulta CONSEXTRATO16: parsear seção 6 do PDF e gravar na operação DAS correspondente.
- No pós-consulta GERAR_DAS (quando houver): gravar `valores.total` estruturado na mesma operação (preferência sobre PDF).
- `openPaymentCompetencies` passa a usar `pgdasd_operations.amount_cents` após `tax_guides` (antes do fallback vault transitório).
- Job/background pós-MONITOR para DAS unpaid sem montante e sem extrato recente (espelho do pipeline RBT12) — sem chamada Integra no load da carteira.
- Web: sem mudança de contrato (`payment_open_competencies[].amount_cents`); só consome cents já resolvidos.

Non-goals: emitir GERARDAS12 live no MONITOR/portfolio; `dataConsolidacao` assistida; parse de DECLARACAO/RECIBO; lógica de valor na Nuxt; flags ON.

## Capabilities

### New Capabilities

- `pgdasd-operation-das-amount`: persistência no ingest Integra (CONSEXTRATO §6 / GERAR_DAS) e resolução no portfolio via `pgdasd_operations.amount_cents` (contrato web de `payment_open_competencies` inalterado).

### Modified Capabilities

<!-- nenhuma — capability de enrich anterior permanece; esta change acrescenta a fonte persistida na operação DAS -->

## Impact

- API: migration `pgdasd_operations`; parser extrato §6; `PgdasdPostConsultService` / caminho documental; `openPaymentCompetencies`; job de gap; testes Feature/Unit.
- Web: nenhuma alteração obrigatória.
- Integra Contador: só nos fluxos já existentes (extrato pós-MONITOR / GERAR_DAS assistido); portfolio permanece read-only local.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main `pgdasd-das-payment-column`; change completa `enrich-pgdasd-payment-open-amounts` (código de enrich no portfolio)
- Depende de: `enrich-pgdasd-payment-open-amounts`
  - Capability/contrato: `pgdasd-payment-open-amount-enrichment`
  - Marco exigido: `apply`
  - Relação: `bloqueante`
- Desbloqueia: valores no popover quando houver extrato ou GERAR_DAS local persistido
- Paralelismo: não editar artefatos da change `monitoring-url-sem-query`; ownership = PGDASD amount ingest/read
