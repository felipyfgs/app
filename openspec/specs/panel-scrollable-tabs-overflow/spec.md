## Purpose

Capability `panel-scrollable-tabs-overflow` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Faixas scrolláveis limitam largura ao pai
O painel MUST garantir que wrappers de scroll horizontal usados por tabs locais e filtros por situação (`ShellScrollableTabs` / token `TOUCH_SCROLL_X`) ocupem no máximo a largura do contêiner pai e NÃO expandam a página além da viewport quando o conteúdo das pills exceder essa largura.

#### Scenario: Conteúdo de tabs mais largo que o pai
- **WHEN** uma faixa `ShellScrollableTabs` renderiza itens cuja largura intrínseca supera o contêiner pai
- **THEN** o overflow horizontal MUST permanecer no wrapper da faixa (scroll touch / overflow-x)
- **AND** o layout da página MUST NOT ganhar scroll horizontal por causa dessa faixa

#### Scenario: Token de scroll declara bound de largura
- **WHEN** o token canônico `TOUCH_SCROLL_X` é definido
- **THEN** ele MUST incluir restrição de largura ao pai (`w-full` e `max-w-full`, ou equivalente documentado)
- **AND** MUST preservar `min-w-0` e `overflow-x-auto` para o scroll engatar em flex

### Requirement: KPIs de carteira herdam overflow contido
A faixa operacional de situação da carteira (`MonitoringKpiStrip` no `ModuleTable`) MUST herdar o contrato de overflow contido, inclusive com vários badges de contagem visíveis.

#### Scenario: Carteira Simples Nacional | MEI em viewport estreita
- **WHEN** o usuário abre uma carteira com `MonitoringKpiStrip` (ex.: Simples Nacional | MEI) em viewport estreita
- **THEN** a faixa Total / Em dia / Processando / Pendências / Atenção / … MUST permanecer utilizável via scroll horizontal interno
- **AND** MUST NOT esticar o body do painel horizontalmente
