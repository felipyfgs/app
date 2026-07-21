## Why

A carteira `/monitoring/simples-mei` mistura Simples Nacional (PGDAS-D) e MEI (PGMEI) em abas locais. O KPI **Total** vale só para a cápsula ativa, mas o título “Simples Nacional | MEI” sugere carteira única — o MEI “some” da leitura operacional. Desacoplar em página própria torna navegação, Total e ações honestos por domínio.

## What Changes

- Criar página de monitoramento **MEI** espelhando o shell da carteira atual (KPIs, tabela, associate, bulk PGMEI), fixa em submodule `PGMEI`.
- Adicionar item dedicado na navegação de monitoramento (sidebar/rail) apontando para a nova rota.
- Restringir `/monitoring/simples-mei` a **Simples Nacional / PGDAS-D**: remover tabs locais SN↔MEI; retitular para Simples Nacional (sem “| MEI”).
- Redirecionar pós-create: regime MEI → nova rota MEI; SN → `/monitoring/simples-mei` (sem sessionStorage de cápsula).
- Manter contrato API `module_key=simples_mei` + `submodule=PGDASD|PGMEI` (sem novo módulo backend nesta change).
- Atualizar testes de navegação, membership pós-create e wiring PGMEI.

Non-goals:
- Novo `FiscalModuleKey` / segmento API separado para MEI.
- SERPRO live, flags ON, mutações fiscais, mei no Compose.
- Redesign do shell do dashboard; mudança de colunas/KPIs além do split de superfície.
- Agregar Total SN+MEI numa visão única.

## Capabilities

### New Capabilities

- `mei-independent-monitoring-page`: superfície de monitoramento MEI (PGMEI) em rota/nav próprias; carteira Simples Nacional só PGDAS-D.

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capability prévia para este contrato)

## Impact

- Web: `monitoring-nav.ts`, `FISCAL_MODULE_LABELS`/`PATHS` (labels/paths de UI), nova `pages/monitoring/mei/` (ou equivalente), slim de `simples-mei/index.vue`, `monitoring-post-create.ts`, associate-filters/helpers, testes unitários de navigation/membership/PGMEI.
- API: sem mudança de contrato nesta change (continua `simples_mei` + submodule); escopo de regime já existe.
- Redirect legado `/monitoring/simples-mei/pgmei` → nova rota MEI (opcional mas desejável).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: archive / main specs vazias; padrão de referência = carteira `simples-mei` + `simples-mei-portfolio-regime-scope` (escopo por regime já aplicado)
- Depende de: nenhuma
- Capability/contrato: `mei-independent-monitoring-page`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply desta change
- Paralelismo: cuidado com changes ativas que editam `simples-mei/index.vue` ou `monitoring-nav.ts` (`compact-simples-mei-selection-actions`, `monitoring-rail-and-portfolio-membership`, `simples-mei-consult-row-skeleton`) — coordenar merge, sem dependência bloqueante
