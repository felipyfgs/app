## Context

Na carteira PGDAS-D, Situação usa `payment_state`. `NO_DAS` hoje mostra “Sem DAS” e popover com pares Situação/Detalhe (“Consulta válida sem DAS…”).

## Goals / Non-Goals

**Goals:**
- Rótulo `NO_DAS` → “Sem movimento”.
- Detalhe limpo via tooltip (ou cartão curto no popover), sem lista Situação/Detalhe.

**Non-Goals:**
- Mudar enum/API `NO_DAS`.
- Alterar PAID/UNPAID ou pipeline RBT12.

## Decisions

1. **Copy:** `label = Sem movimento`; `description` curta (ex.: “Nenhum DAS gerado no período.”).
2. **UX:** para `NO_DAS`, `UTooltip` no badge com a description; no popover, mesmo padrão de cartão limpo de “Em dia” (ícone + título + frase), sem linhas Situação/Detalhe.
3. **Root:** manter `UPopover` como root único (compatível com `UTable`/`h()`).

## Risks / Trade-offs

- Copy “Sem movimento” pode confundir com DCTFWeb “Sem movimento” — aceitável: mesmo significado de negócio (período sem guia/DAS).

## Migration Plan

Só frontend; deploy do SPA. Sem migration de dados.

## Open Questions

- Nenhuma.
