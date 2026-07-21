## Context

`MonitoringPgdasdHistoryView` e o rodapé de `[clientId].vue` adicionam copy e atalhos que não são o contrato útil do `GET .../pgdasd/clients/{id}/history`.

## Goals / Non-Goals

**Goals:** UI do histórico = payload (estado, PA esperado, última consulta, periods) + filtro de ano + ações explícitas necessárias.

**Non-Goals:** Reescrever layout por PA; mudar API; remover rail de seções fiscais.

## Decisions

1. Card sem `description`; título “PGDAS-D”.
2. Remover bloco “Histórico DAS” + modal na aba (duplicata).
3. Remover pills do rodapé do detalhe do cliente.
4. Empty: uma frase curta (“Nenhum período para {ano}.” / “Nenhum histórico.”).

## Risks / Trade-offs

- [Usuário usava modal Histórico DAS] → Mitigação: a view lista declarações/DAS; modal permanece na carteira Declarações.
- [Merge com layout por PA] → Preservar seletor de ano e superfície enxuta.
