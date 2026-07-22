## 1. N0 — UI colapsável do contexto

- [x] 1.1 Em `TimelinePanel.vue`, trocar o botão de contexto para `i-lucide-user` sempre visível, com estado ativo quando o contexto estiver aberto, e emitir `toggleContext` (prop `contextOpen` opcional).
- [x] 1.2 Em `ContextPanel.vue`, expor botão fechar também no desktop e limitar largura com `resizable` + sizes no `UDashboardPanel`.
- [x] 1.3 Em `communication.vue`, condicionar a coluna desktop do contexto a `contextOpen`, usar `lg+` alinhado à timeline, fechar ao trocar/selecionar conversa (default fechado) e ligar toggle/close/Escape.

## 2. N1 — Evidência e gate

- [x] 2.1 Atualizar `communication-workspace-ui-gate.test.ts` para o painel colapsável (ícone usuário, `contextOpen`, sem coluna permanente sem guarda).
  Depende de: 1.1, 1.2, 1.3
- [x] 2.2 Rodar `pnpm run test -- tests/unit/communication-workspace-ui-gate.test.ts` (e typecheck/lint se tocado o gate CI da área).
  Depende de: 2.1
