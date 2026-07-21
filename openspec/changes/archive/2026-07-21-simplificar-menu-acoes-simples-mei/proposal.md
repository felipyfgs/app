## Why

O menu **Mais ações** da carteira `/monitoring/simples-mei` (aba Simples / PGDAS-D) mistura Regime, DEFIS e jargão SERPRO. Essa superfície é só da **declaração PGDAS-D**; itens fora desse domínio confundem e não deveriam aparecer aqui (DEFIS e regime têm outros hubs).

## What Changes

- Reduzir o menu da seleção PGDAS-D a **ações básicas do domínio PGDAS**: comunicação local (preferências / destinatários / histórico), histórico de busca PGDAS-D, abrir cliente, limpar seleção.
- Renomear o gatilho para **Ações** (padrão do cadastro de clientes).
- **Remover** do menu e da wiring desta página: Regime (calendário/opção/resolução) e DEFIS (declarações/última/específica) e consultas batch SERPRO associadas.
- Remover modais/confirmações de regime e DEFIS montados só por essa carteira PGDAS-D, se ficarem sem uso.
- Manter o botão primário **Consultar** (PGDAS-D) e os atalhos de linha já existentes.

Non-goals:
- Não apagar backends/serviços de Regime ou DEFIS do produto (só tirar desta superfície).
- Não alterar a aba MEI/PGMEI nem `/monitoring/declarations`.
- Não ligar flags SERPRO nem mudar API.

## Capabilities

### New Capabilities
- `simples-mei-selection-actions-menu`: menu de ações da seleção na carteira PGDAS-D restrito ao domínio PGDAS (sem Regime/DEFIS).

### Modified Capabilities
- (nenhuma — main specs vazias)

## Impact

- Web: `pgdasd-action-items.ts`, `SelectionActions.vue`, `simples-mei/index.vue` (handlers/modais órfãos), testes unitários.
- API / Compose: nenhum.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: carteira Simples/MEI e domínio PGDAS-D
- Depende de: nenhuma
- Capability/contrato: `simples-mei-selection-actions-menu`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: menu enxuto só PGDAS
- Paralelismo: ok com `pgdasd-history-period-layout`
