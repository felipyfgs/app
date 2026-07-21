## Why

No popover Pendências PGDAS-D, o `amount_cents` do PA soma todos os DAS locais `NOT_FOUND`/`unpaid` do período. Guias reemitidas do mesmo PA (mesmo valor facial, vários `das_number`) inflam o débito — ex.: E DE A BRITO em 06/2026 mostra R$ 706,25 (= 5× R$ 141,25) em vez do débito representativo. O operador precisa ver o valor em débito do PA (indicativo claro, em vermelho) sem multiplicar reemissões.

## What Changes

- Agregar `payment_open_competencies[].amount_cents` por PA **sem somar reemissões**: usar o montante representativo do débito do PA (máximo entre valores resolvíveis dos DAS unpaid, quando todos tiverem valor; `null` se algum unpaid ficar sem valor — fail-closed já existente).
- Na UI do popover Pendências, exibir o valor monetário como **indicativo de débito** (cor de erro / vermelho semântico), mantendo `—` quando `amount_cents` for null.
- Sem mudar regra de inclusão do PA na lista (continua: nenhum DAS `PAID` PAGTOWEB + cobertura negativa completa/fresca).

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `pgdasd-payment-popover-cliente`: montante do PA unpaid deixa de ser soma N× reemissões; UI destaca valor como débito.

## Impact

- API: `PgdasdMonitoringQueryService::openPaymentCompetencies` (agregação); testes Feature de open competencies.
- Web: `PaymentValue.vue` (estilo do valor unpaid); testes unitários do popover se cobrirem markup/classe.
- Non-goals: live SERPRO; SITFIS na badge; mudar `PAID`/`UNPAID` do PA esperado; parecer jurídico; mutações; flags ON; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: specs `pgdasd-payment-popover-cliente`, `pgdasd-das-payment-column`
- Depende de:
  - `reconciliar-pagamento-pgdasd-com-pagtoweb` — lista só com cobertura PAGTOWEB — marco `apply` — relação `coordenada`
  - `enrich-pgdasd-payment-open-amounts` — resolução local de `amount_cents` — marco `apply` — relação `coordenada`
- Desbloqueia: popover Pendências com débito PA honesto + sinal visual de dívida
- Paralelismo: ownership = agregação open competencies + estilo unpaid no web; não editar digest PAGTOWEB nem copy PAID
