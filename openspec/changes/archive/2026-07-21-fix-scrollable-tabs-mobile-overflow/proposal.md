## Why

Nas carteiras de monitoramento (ex.: Simples Nacional | MEI), as faixas `ShellScrollableTabs` (submódulos e KPIs Total / Em dia / …) estouram a viewport em telas estreitas: o container deveria rolar horizontalmente, mas cresce com o conteúdo e empurra o layout da página.

## What Changes

- Corrigir a cadeia de largura do scroll touch em `ShellScrollableTabs` / `TOUCH_SCROLL_X` para o overflow horizontal ficar contido (`w-full` / `max-w-full`) sem mudar o padrão visual pill.
- Garantir que `MonitoringKpiStrip` e o bloco de KPIs do `ModuleTable` respeitem `min-w-0` + largura máxima da viewport.
- Cobrir o contrato com teste unitário de layout (gate de regressão).

## Capabilities

### New Capabilities

- `panel-scrollable-tabs-overflow`: contrato de overflow horizontal contido para faixas scrolláveis do painel (tabs locais e filtros por situação).

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capability prévia para este contrato)

## Impact

- Front: `apps/web/app/utils/list-filter-layout.ts`, `apps/web/app/components/shell/ScrollableTabs.vue`, `apps/web/app/components/monitoring/KpiStrip.vue`, eventualmente `ModuleTable.vue`; testes em `apps/web/tests/unit/`.
- Sem mudança de API, filtros semânticos ou comportamento de seleção de KPI/submódulo.
- Non-goals: redesenhar KPIs como grid de cards; wrap em múltiplas linhas; alterar tema visual das pills; SERPRO live; flags ON; mei no Compose.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs / archive
- Depende de: nenhuma
- Capability/contrato: `panel-scrollable-tabs-overflow`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply desta change
- Paralelismo: ok com changes de monitoring que não toquem `list-filter-layout` / `ScrollableTabs`
