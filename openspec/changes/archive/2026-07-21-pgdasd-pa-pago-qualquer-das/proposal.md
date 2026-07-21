## Why

A coluna Pagamento e o popover “Pendências” tratam todo `dasPago=false` (CONSDECLARACAO13) como débito do PA. Na doc SERPRO, `dasPago` só diz se **aquele número de DAS** foi pago — guias reemitidas ficam `false` mesmo com o PA já quitado. Isso diverge da Situação Fiscal (SITFIS: “Não há débitos apurados no Simples Nacional no âmbito da RFB”) e gera falsos positivos (ex.: A. F. Coelho com DAS pagos no PA e ainda assim “Pendências”).

## What Changes

- Reinterpretar quitação do PA: se **qualquer** operação DAS local do `period_key` tiver `payment_located=true` (`dasPago`), o PA MUST ser tratado como pago para badge e para lista de competências em aberto.
- Ajustar `PgdasdDasPaymentStateResolver` (PA esperado) e `openPaymentCompetencies` (histórico) à mesma regra.
- Manter fail-closed: PA sem nenhum DAS pago e com ao menos um `payment_located=false` continua `UNPAID` / entra em pendências.
- Sem live SERPRO no portfolio; sem misturar SITFIS na badge Pagamento nesta change.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-das-payment-column`: precedência do estado — PA pago quando existe ao menos um DAS com `payment_located=true` (guias `false` do mesmo PA não forçam `UNPAID`).
- `pgdasd-payment-popover-cliente`: `payment_open_competencies` só lista PAs em que **nenhum** DAS local tem `payment_located=true`.

## Impact

- API: `PgdasdDasPaymentStateResolver`, `PgdasdMonitoringQueryService::openPaymentCompetencies` (+ testes Feature/Unit).
- Web: sem mudança de contrato obrigatória (mesmo shape); labels já existentes.
- Gap/amount enrich: jobs que varrem `payment_located=false` MAY continuar no DAS individual; a superfície de “pendência” do cliente passa a filtrar por PA quitado.
- Non-goals: live SERPRO no GET; parecer jurídico; mutações fiscais; flags ON; cruzar SITFIS na coluna Pagamento; alterar Situação de entrega; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: specs `pgdasd-das-payment-column`, `pgdasd-payment-popover-cliente` (e enrich de `amount_cents` já em main/archive)
- Depende de: nenhuma change ativa
- Desbloqueia: UI Pagamento alinhada à quitação real do PA; reduz falsos positivos vs SITFIS
- Paralelismo: ownership = resolver + `openPaymentCompetencies`; não conflita com persist de `amount_cents` se só leitura/agregação mudar
