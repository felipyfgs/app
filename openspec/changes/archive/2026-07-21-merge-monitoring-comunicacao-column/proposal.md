## Why

Nas carteiras de monitoramento, a coluna **Hist. comunicação** ocupa largura relevante com um rótulo longo, mas a célula só exibe um ícone de rastreio. Envio (Send + switch) e rastreio são o mesmo bloco semântico; separá-los desperdiça espaço sem ganho de contexto.

## What Changes

- Fundir **Envio** e **Hist. comunicação** numa única coluna **Comunicação** na spine das carteiras por cliente.
- Célula: Send · Switch de automático · ícone de rastreio local (cada um com tooltip/`aria-label`).
- Cabeçalho: rótulo **Comunicação** (filtro “Envio” / `send_status` permanece — só o header da grade muda).
- Remover a coluna isolada Hist. comunicação da grade; Ações continua só com ⋮.
- Atualizar contrato OpenSpec, builders de coluna e testes source/UX afetados.

Non-goals: alterar API de preferência/send/tracking, filtro Envio do popover, kill-switch fail-closed, SERPRO live, ou redesign do shell.

## Capabilities

### New Capabilities

<!-- nenhuma -->

### Modified Capabilities

- `monitoring-portfolio-columns`: spine passa a usar coluna única Comunicação no lugar do par Envio · Hist. comunicação; requisito de colunas distintas é substituído.
- `simples-mei-portfolio-ux`: coluna canônica de comunicação deixa de ser “Hist. comunicação” isolada e passa a ser a célula casada Comunicação (rastreio continua um único ícone).

## Impact

- Web: `apps/web/app/utils/monitoring-table-columns.ts` e builders (`pgdasd-table`, `pgmei-table`, `dctfweb-table`, `declarations-table`, `sitfis-table`, FGTS e correlatos); labels mobile/`column-labels`; testes unitários de layout/colunas.
- Specs: `openspec/specs/monitoring-portfolio-columns`, `openspec/specs/simples-mei-portfolio-ux`.
- API: sem mudança de contrato HTTP.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs `monitoring-portfolio-columns`, `simples-mei-portfolio-ux`
- Depende de: nenhuma
- Capability/contrato: `monitoring-portfolio-columns`, `simples-mei-portfolio-ux`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação UI da coluna casada
- Paralelismo: pode rodar em paralelo com changes que não toquem builders/spine de comunicação das carteiras
