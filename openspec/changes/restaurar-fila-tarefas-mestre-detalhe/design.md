## Context

`densificar-inbox-tarefas-mailbox` removeu auto-seleção e escondeu o detalhe até haver seleção + `detailOpen`, fazendo `/work` parecer só lista. A Fila precisa voltar ao padrão inbox (lista + detalhe).

## Goals / Non-Goals

**Goals:** split Fila desktop; auto-select primeira tarefa; empty state no detalhe; toggle/colapso preservados; Lista intacta.

**Non-Goals:** Mailbox; redesign do `WorkTaskDetailPanel`; bulk/sort.

## Decisions

1. **`detailPaneVisible`** — `isFila && !isMobile && detailOpen` (não exige `selectedTaskId`). Sem seleção o painel mostra empty state.
2. **Default `detailOpen`** — `true` ao entrar na Fila desktop; toggle continua fechando e expandindo a lista.
3. **Auto-select** — após `loadQueue`, se Fila desktop, sem id no path e `items.length > 0`, selecionar `items[0]` (navega para `/work/tasks/{id}`). Não re-selecionar logo após dismiss explícito na mesma sessão de lista vazia intencional — apenas quando não há seleção e há itens (abrir `/work` / trocar aba/filtro que limpa path).
4. **Mobile** — slideover inalterado.

## Risks / Trade-offs

- [Volta densidade menor na Fila] → aceito; toggle ainda colapsa para quem quiser lista larga.

## Migration Plan

Só frontend; rollback reverte `WorkQueueWorkspace` + gates.
