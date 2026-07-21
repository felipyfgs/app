## Context

As grades desktop das carteiras (`ModuleDataTable` + `table-fixed`) marcam a coluna Cliente com `min-w-48 w-full`. Em viewports estreitas (≥ md, onde ainda há tabela e não cards), a soma das colunas fixas + 12rem do Cliente ultrapassa a largura útil e aparece barra de rolagem horizontal. `FiscalClientCell` já aplica `truncate`/ellipsis, mas o `min-w-48` impede o encolhimento efetivo.

## Goals / Non-Goals

**Goals:**

- Coluna Cliente encolhe com a viewport (`min-w-0 w-full`) e o nome truncado com ellipsis.
- Meta canônica compartilhada (`MONITORING_CLIENT_COLUMN_META`) usada por todas as carteiras com `FiscalClientCell`.
- Nome completo permanece acessível via `title`/tooltip do link.

**Non-Goals:**

- Alterar lista admin `/clients` (`min-w-48` permanece).
- Forçar `horizontalScroll` ou `min-w` artificial na `<table>`.
- Mudar ordem/spine de colunas.

## Decisions

1. **`w-full max-w-0` (não `min-w-48`)** — Em `table-fixed`, `max-w-0` + `w-full` deixa a coluna absorver o resto e encolher abaixo do min-content do texto; sem isso o nome longo estica a tabela. `max-w-xs` e afins ficam fora (faixa vazia).

2. **Constante `MONITORING_CLIENT_COLUMN_META`** — Evita drift entre builders/páginas.

3. **`FiscalClientCell` só com ellipsis CSS** — Texto completo no DOM + `truncate`/`title`; sem corte por contagem de caracteres (não adapta à tela).

4. **`horizontalScroll=false` no Portfolio Simples/MEI** — O escape hatch `overflow-x-auto` era o que materializava a barra inferior; com a coluna encolhendo, a grade cabe na viewport.

## Risks / Trade-offs

- [Nomes longos ficam mais curtos em telas médias] → Mitigação: `title` com nome completo; em telas largas a coluna ainda absorve o espaço livre via `w-full`.
- [Asserts de layout quebram] → Atualizar `list-table-layout.test.ts` no mesmo PR.

## Migration Plan

Deploy FE atômico. Rollback: reverter PR.

## Open Questions

Nenhuma.
