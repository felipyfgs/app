## Context

A spine compartilhada das carteiras por cliente (`monitoring-table-columns` + builders PGDAS/PGMEI/DCTF/SITFIS/FGTS/Declarações) coloca **Ações** entre Cliente e Comunicação, com célula = `UButton` `square` só com ícone `ellipsis-vertical`. Em `/monitoring/simples` isso deixa o controle pouco legível e fora do padrão dos botões com label da toolbar (ex.: bulk «Ações»).

## Goals / Non-Goals

**Goals:**

- Coluna Ações como última coluna da spine (após Consulta).
- Trigger do dropdown como botão com label «Ações» (e trailing icon de menu), reutilizando o helper compartilhado.
- Atualizar contrato OpenSpec + testes de source/ordem.

**Non-Goals:**

- Mudar itens do dropdown ou permissões.
- Alterar coluna Comunicação ou atalho de consulta na coluna Consulta.
- Backend/API.

## Decisions

1. **Ordem:** `… · Comunicação · Consulta · Ações` — Ações no fim é o padrão de tabelas operacionais e evita competir visualmente com Send/Switch.
2. **Botão rotulado no helper (padrão Nuxt UI das Ações em massa):** alterar `buildMonitoringActionsMenuCell` para `UButton` com `label: 'Ações'`, `icon: 'i-lucide-ellipsis-vertical'`, `variant: 'subtle'`, `color: 'neutral'`, `size: 'xs'` (mesmo contrato visual de `ModuleBulkActions` / `SelectionActions`, sem `square`). Meta da coluna usa `w-0 whitespace-nowrap`.
3. **Escopo transversal justificado:** uma capability (`monitoring-portfolio-columns`); todos os builders que usam o helper reordenam Ações para o fim — evita spine divergente entre módulos.
4. **Alternativa rejeitada:** só mover em PGDASD — quebraria o contrato canônico e o fidelity entre carteiras.

## Risks / Trade-offs

- [Largura da grade] → botão com texto ocupa mais que ⋮; mitigar com `xs` + `COMPACT_BUTTON_LABEL_UI` / meta `w-0 whitespace-nowrap` ou `min-w` enxuto.
- [Testes de source] → strings «Ações · Comunicação · Consulta» e cenários «só ⋮» quebram; atualizar na mesma change.
- [Mobile cards] → labels em `MONITORING_SHARED_COLUMN_LABELS` já usam «Ações»; sem mudança de id.

## Migration Plan

Deploy só frontend. Rollback = reverter a change. Sem migração de dados.
