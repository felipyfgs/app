## Why

Na visão Fila de Tarefas (`/work`), o detalhe ficou sob demanda (densificação): sem tarefa selecionada o painel some e a lista ocupa a tela inteira. O operador vê só uma lista — não o mestre–detalhe estilo inbox/chat (lista estreita + painel à direita) prometido pela Fila.

## What Changes

- Restaurar split desktop na Fila: lista estreita + painel de detalhe sempre presente quando o detalhe está aberto (inclui empty state sem seleção).
- Auto-selecionar a primeira tarefa ao abrir `/work` na Fila desktop quando a fila tem itens e não há id no path.
- Manter toggle de colapsar detalhe e slideover no mobile; visão Lista tabular intacta.
- Atualizar gates UI que afirmavam “sem auto-select”.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `inbox-master-detail-density`: reverter o contrato de Fila (Tarefas) para mestre–detalhe com auto-seleção; Mailbox permanece densificada.

## Impact

- Web: `WorkQueueWorkspace.vue`, gates `painel-responsivo-mobile-gate` / `work-orchestration`
- OpenSpec: delta em `inbox-master-detail-density`

### Non-goals

- Redesign do painel de detalhe / comentários
- Mudar visão Lista
- Alterar Mailbox

### Dependências entre changes

- Nível: **C0**
- Relação: ajusta comportamento introduzido por `densificar-inbox-tarefas-mailbox` só na Fila de Tarefas
