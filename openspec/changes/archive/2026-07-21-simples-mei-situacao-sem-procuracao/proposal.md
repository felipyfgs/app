## Why

Na carteira Simples/MEI, clientes sem procuração e-CAC aparecem como «Não verificado» na coluna Situação, confundindo ausência de outorga com declaração nunca consultada. O escritório precisa ver **Sem procuração** nesse caso.

## What Changes

- Incluir `procuracao_status` (projeção oficial) no `detail` do portfolio `simples_mei`.
- Na coluna Situação (PGDAS-D e PGMEI), se status for `missing`, exibir badge **Sem procuração** (precedência sobre `declaration_state` / `debt_state`).

## Capabilities

### New Capabilities

- `simples-mei-portfolio-procuracao-situation`: situação da carteira Simples/MEI reflete ausência de procuração e-CAC.

### Modified Capabilities

- (nenhuma — main specs vazias)

## Impact

- API: `ModulePortfolioQueryService::detailSimplesMei`
- Web: `pgdasd-table.ts`, `pgmei-table.ts`, tipos `SimplesMeiClientDetail`
- Reuso: `ClientProcuracaoValidityResolver`, labels em `procuracao.ts`

### Non-goals

- Não criar KPI/filtro novo «Sem procuração» nesta change.
- Não sync SERPRO na listagem; só projeção local já sincronizada.
- Não alterar coluna Situação de outros módulos.

### Dependências entre changes

- Nível: `C0`
- Depende de: nenhuma
- Desbloqueia: leitura operacional da carteira sem confundir UNVERIFIED fiscal com falta de e-CAC
