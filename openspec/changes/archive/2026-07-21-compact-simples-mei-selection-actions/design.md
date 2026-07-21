## Context

`ListFilterToolbar` recebe ações via slot `#actions`. Em Simples/MEI, o menu **Ações** da seleção não deve mutar membership (associar/excluir) sem salvaguarda — isso é “muito direto”. Consulta em lote já tem modal; membership precisa do modal dedicado / confirmação.

## Goals / Non-Goals

**Goals:**

- Menu **Ações** (chrome DCTFWeb) só com ações confirmáveis: Solicitar consulta + Limpar (+ Serviços MEI).
- Associar via botão → modal de membership.
- Excluir na linha com `ShellConfirmModal` (tone danger).

**Non-Goals:**

- Mexer no shell `ListFilterToolbar`.
- Remover atalho de consulta por linha.
- Alterar o fluxo interno do modal de associação.

## Decisions

1. **Chrome DCTFWeb no botão Ações** — subtle + `list-checks` + `UKbd`, sem `description`.
2. **Membership fora do menu** — Associar/Excluir no dropdown sem confirmação é inseguro; Associar no botão + modal; Excluir na linha com confirmação.
3. **Menu só consulta + limpar** — Solicitar consulta abre o modal já existente; Limpar é reversível.
4. **Label `Solicitar consulta` + `cloud-download`** — alinhado ao DCTFWeb.
5. **Duas capabilities modificadas (exceção)** — menu + consulta rápida nos mesmos componentes.

## Risks / Trade-offs

- **[Descoberta]** Consultar fica um clique a mais → Mitigação: item no topo; atalho de linha permanece.
- **[Excluir em lote]** Sem item de exclusão em massa no menu → Mitigação: modal Associar cobre revisão; exclusão unitária com confirmação.

## Migration Plan

- Só front; rollback = reverter arquivos da change.

## Open Questions

- Nenhum.
