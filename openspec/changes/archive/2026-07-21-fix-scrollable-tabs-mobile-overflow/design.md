## Context

`ShellScrollableTabs` usa `TOUCH_SCROLL_X` + `SCROLLABLE_TABS_UI` (`root`/`list` com `w-max`, triggers `min-w-max`) para manter pills compactas com scroll horizontal no mobile. Em `MonitoringKpiStrip` e nas tabs de submódulo (Simples/MEI), a faixa fica larga demais para a viewport.

O bug: o wrapper de scroll tem `overflow-x-auto` e `min-w-0`, mas **não** limita a largura ao pai (`w-full` / `max-w-full`). Com filhos `w-max`, o container cresce até o conteúdo — o overflow nunca engata e a página estoura horizontalmente.

## Goals / Non-Goals

**Goals:**

- Contêineres scrolláveis de tabs ocupam no máximo a largura do pai e rolam horizontalmente quando o conteúdo excede.
- KPIs e submódulos do `ModuleTable` herdam a correção sem mudar semântica de filtro/seleção.
- Gate de regressão via teste unitário de tokens/layout.

**Non-Goals:**

- Trocar pills por grid de cards (`ShellKpiStrip`).
- Permitir wrap em múltiplas linhas nas tabs (padrão do painel continua nowrap + scroll).
- Alterar cores/variantes do tema Nuxt UI Tabs.
- Mudanças de API ou contadores.

## Decisions

1. **Corrigir no token `TOUCH_SCROLL_X` (não só num consumidor)**  
   - **Escolha:** acrescentar `w-full max-w-full` em `TOUCH_SCROLL_X` em `list-filter-layout.ts`.  
   - **Por quê:** todos os usos (ScrollableTabs, faixas de filtro) precisam do mesmo bound de largura para o scroll engatar.  
   - **Alternativa rejeitada:** só `class="w-full"` em `KpiStrip` — deixa DCTFWeb/declarações/submódulos vulneráveis ao mesmo bug.

2. **Manter `SCROLLABLE_TABS_UI` com `w-max` no `UTabs` interno**  
   - O conteúdo continua intrínseco; o **wrapper** é quem limita e faz scroll. Não voltar `min-w-full` no root das tabs (teste de navegação já proíbe).

3. **Reforço defensivo em `MonitoringKpiStrip` / bloco KPI**  
   - `w-full min-w-0 max-w-full` no host da faixa, alinhado a `ListFilterToolbar`.  
   - Barato e documenta o contrato no componente de domínio.

## Risks / Trade-offs

- **[Risk]** `w-full` em `TOUCH_SCROLL_X` pode afetar usos que esperavam shrink-to-content fora de um pai full-width.  
  → **Mitigation:** usos atuais já estão em faixas `w-full` / toolbar; validar testes de `list-filter-layout` / navigation.

- **[Trade-off]** Scroll horizontal continua sem scrollbar sempre visível (SO/browser).  
  → Aceitável: padrão já adotado no painel; overflow da página é o defeito a eliminar.

## Migration Plan

- Deploy só front; sem migração de dados.
- Rollback: reverter tokens/classes.

## Open Questions

- Nenhuma bloqueante.
