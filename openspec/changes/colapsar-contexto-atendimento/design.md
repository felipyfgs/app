## Context

A página `/communication` já usa o arquétipo Nuxt UI dashboard (lista + timeline + contexto) com `UDashboardPanel` e slideovers em `<lg`. Hoje o `CommunicationContextPanel` renderiza sempre em `xl+` (`hidden xl:flex`), e o botão de contexto na timeline só aparece abaixo de `xl`. Em viewports típicas de laptop isso deixa quatro colunas (nav + lista + chat + contexto) espremidas.

## Goals / Non-Goals

**Goals:**

- Permitir abrir/fechar o Contexto com um único controle (ícone de usuário) na navbar da timeline.
- Em `lg+`, liberar a largura da timeline quando o contexto estiver fechado.
- Em `<lg`, manter `USlideover` com o mesmo estado.
- Alinhar componentes Nuxt UI existentes (`UButton`, `UTooltip`, `UDashboardPanel`, `USlideover`) sem redesenhar o shell.

**Non-Goals:**

- Persistência de preferência (cookie/localStorage).
- Trocar o painel por `UDashboardSidebar side="right"` nesta change (risco de conflito com o sidebar esquerdo do layout).
- Alterar conteúdo de responsável/fila/labels/expurgo.
- Mudanças de API ou realtime.

## Decisions

1. **Estado único `contextOpen`** — reutilizar o `ref` já usado pelo slideover; no desktop, condicionar a renderização do `CommunicationContextPanel` a `contextOpen` em vez de `always-on` em `xl+`.
   - Alternativa: `UDashboardSidebar side="right" collapsible` — rejeitada por agora: o layout já tem sidebar esquerdo no `UDashboardGroup` do shell; um segundo sidebar exige wiring de `v-model:collapsed` e ids no group, maior risco de regressão no shell.

2. **Toggle `i-lucide-user` sempre visível** na timeline — remove `xl:hidden`; quando aberto, variante/cor ativa (`primary`/`soft`) para feedback; emite `toggleContext` (ou reusa `openContext` + close na page).
   - Preferir emit `toggle-context` e, no painel, `@close` / botão X também no desktop.

3. **Default fechado** — ao selecionar conversa, não abrir o contexto automaticamente; o operador abre quando precisa. Escape continua fechando.

4. **Breakpoint desktop do painel: `lg+`** (alinhado à timeline `hidden lg:flex`), não só `xl+`, para que o toggle inline funcione no mesmo range em que a timeline está embutida.

5. **Sizing** — quando aberto no desktop, `UDashboardPanel` do contexto com `default-size`/`min-size`/`max-size` e `resizable` para limitar a coluna e permitir ajuste fino.

6. **Testes** — atualizar `communication-workspace-ui-gate.test.ts` para exigir toggle de usuário, painel condicionado a `contextOpen`, e ausência de coluna permanente `xl:flex` sem guarda de estado.

## Risks / Trade-offs

- [Operador não encontra responsável/fila] → Mitigação: ícone de usuário sempre na navbar da conversa + tooltip “Contexto do contato”; botão fechar no header do painel.
- [Regressão mobile] → Mitigação: slideover permanece; gate continua exigindo `communication-mobile-context`.
- [Conflito com change `cobrir-whatsmeow-conversas-1x1`] → Mitigação: ownership só em pages/components de UI do workspace; sem tocar gateway.

## Migration Plan

- Deploy só frontend; sem migração de dados.
- Rollback: reverter os três arquivos Vue + o gate de teste.

## Open Questions

- Nenhuma bloqueante; persistência de preferência fica para change futura se o time pedir.
