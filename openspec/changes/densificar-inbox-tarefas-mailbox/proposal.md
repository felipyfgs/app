## Why

As superfícies de Tarefas (`/work`, `/work/tasks/:id`) e Caixa Postal (`/monitoring/mailbox`) usam mestre–detalhe com detalhe desktop quase sempre ocupando metade da tela (Tarefas ainda auto-seleciona a primeira tarefa). Depois do Atendimento com painel colapsável, o operador sente as mesmas telas espremidas e quer o mesmo padrão de densidade: lista larga por default, detalhe sob demanda.

## What Changes

- Em Tarefas (visão Fila): remover auto-seleção desktop; detalhe abre só ao selecionar e pode fechar, expandindo a lista; toggle na navbar da lista (ícone de painel/documento) para reabrir o detalhe da seleção atual.
- Em Mailbox: detalhe desktop sob demanda (toggle); lista ganha largura quando fechado; chrome de monitoramento (card/banner) recolhível para liberar altura do inbox.
- Mobile: slideover de detalhe permanece; mesmo modelo mental de abrir/fechar.
- Gates de UI (`painel-responsivo-mobile-gate`, `work-orchestration`, `monitoring-workspace-ui-gate` / mailbox) atualizados para o contrato densificado.
- **Não** implementar a visão Lista tabular de Tarefas aqui — isso fica em `adicionar-visao-lista-tarefas`.

## Capabilities

### New Capabilities

- `inbox-master-detail-density`: densidade do mestre–detalhe em Tarefas (Fila) e Caixa Postal — detalhe colapsável, lista prioritária, chrome secundário recolhível na mailbox.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — contratos de layout dessas superfícies ainda não estão nas main specs; a change `colapsar-contexto-atendimento` / `communication-workspace-ui` é referência de padrão, não delta desta change.)

## Impact

- Frontend: `WorkQueueWorkspace.vue`, `WorkTaskDetailPanel.vue`, `pages/monitoring/mailbox.vue`, `MailboxMail.vue` / card de monitoramento, testes/gates de painel.
- API: nenhuma.
- UX: Nuxt UI (`UDashboardPanel`, `UButton`, `UTooltip`, `USlideover`, `UCollapsible` se necessário para o card).

### Non-goals

- Visão Lista de Tarefas (`view=lista`) — change `adicionar-visao-lista-tarefas`.
- Terceira coluna de “contexto” extraída do detalhe (A′ do explore).
- Redesign do shell, mutações fiscais, SERPRO live, flags ON, mei no Compose, ops backup/restore.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: código atual de work/mailbox + receita de `colapsar-contexto-atendimento` (já completa no tree).
- Depende de: **nenhuma** (change ativa bloqueante)
- Capability/contrato: `inbox-master-detail-density` (nova)
- Marco exigido: n/a
- Relação: n/a
- Coordenação informal:
  - `adicionar-visao-lista-tarefas` — ownership de `view=lista` / `WorkTaskListView`; esta change só toca comportamento da **Fila** (split). Relação **coordenada** se ambas editarem `WorkQueueWorkspace` — aplicar em ondas ou merges cuidadosos.
  - `operacionalizar-caixa-postal-ecac-monitoramento-economico` — ownership de fluxo econômico mailbox; esta change só layout UI. Relação **coordenada**, não bloqueante.
- Desbloqueia: implementação frontend de densidade inbox
- Paralelismo: ok com changes que não editem os mesmos Vue de workspace
