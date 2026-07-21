## Context

`buildPgdasdSelectionMenu` hoje inclui comunicação + REGIMEAPURACAO + DEFIS + navegação. A aba PGDAS-D de `/monitoring/simples-mei` monitora declaração PGDAS; Regime e DEFIS não pertencem a este menu. O cadastro usa **Ações** com lista curta.

## Goals / Non-Goals

**Goals:**

- Menu **Ações** só com itens PGDAS: preferências, destinatários/documentos, histórico de comunicação, histórico PGDAS-D, abrir cliente, limpar seleção.
- Labels curtos, sem códigos SERPRO.
- Remover batch consult regime/DEFIS de `SelectionActions` e limpar handlers/modais órfãos em `simples-mei/index.vue`.
- Consultar PGDAS-D continua no botão primário.

**Non-Goals:**

- Remover capabilities Regime/DEFIS de outras rotas (ex.: Declarações).
- Redesign PGMEI além do necessário.

## Decisions

1. **Cortar Regime e DEFIS desta superfície** — não aninhar; remover. Alternativa (submenu) rejeitada pelo produto: “não faz sentido aí”.
2. **Manter comunicação PGDAS** — preferências/destinatários/tracking são do fluxo de comunicação da obrigação PGDAS-D já na linha; continuam como ações básicas.
3. **Botão `Ações`** — paridade com cadastro; test-id estável.

## Risks / Trade-offs

- **[Acesso a Regime/DEFIS]** Deixa de existir atalho nesta carteira → Mitigação: usam hub Declarações / outras superfícies; não apagar API.
- **[Código morto]** Modais no index sem caller → Mitigação: remover wiring junto nesta change.

## Migration Plan

- Só front; rollback = reverter arquivos tocados.

## Open Questions

- Nenhum.
