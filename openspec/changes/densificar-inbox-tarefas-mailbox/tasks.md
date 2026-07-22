## 1. N0 — Tarefas (Fila densificada)

- [x] 1.1 Em `WorkQueueWorkspace.vue`: remover auto-select desktop; introduzir `detailOpen`; deep-link com id abre detalhe; toggle `i-lucide-panel-right` na navbar; seleção abre detalhe; toggle fecha sem limpar URL; X/`clearSelection` limpa path.
- [x] 1.2 Ajustar empty state / painel detalhe `v-if="!isMobile && selectedId && detailOpen"` e Escape fecha detalhe aberto.
  Depende de: 1.1

## 2. N0 — Mailbox densificada

- [x] 2.1 Em `mailbox.vue`: `detailOpen`; detalhe desktop condicionado; toggle na navbar; seleção abre; toggle fecha mantendo rota; X usa `closeDetail`.
- [x] 2.2 Recolher `MonitoringMailboxMonitoringCard` (e chrome associado) com `UCollapsible`/equivalente, default recolhido, sinal de erro no header.
  Depende de: 2.1

## 3. N1 — Gates e evidência

- [x] 3.1 Atualizar gates (`painel-responsivo-mobile-gate`, `work-orchestration` e/ou gate mailbox/`monitoring-workspace-ui-gate`) para sem auto-select, `detailOpen`/toggle e chrome recolhível.
  Depende de: 1.2, 2.2
- [x] 3.2 Rodar subset Vitest tocado + `openspec validate densificar-inbox-tarefas-mailbox --strict`.
  Depende de: 3.1
  Evidência: `pnpm exec vitest run` nos arquivos de gate; validate OpenSpec
