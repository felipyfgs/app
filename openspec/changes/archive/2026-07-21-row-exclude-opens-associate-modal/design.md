## Context

Em `Portfolio.vue`, `onExclude` da linha chama `requestExcludeFromMonitoring`, que abre `ShellConfirmModal` e, ao confirmar, chama `monitoringMembership.exclude` direto. O botão **Associar clientes** da toolbar já abre `MonitoringAssociateMonitoringClientsModal` (`membershipOpen`), onde o usuário inclui/exclui com revisão.

A change `compact-simples-mei-selection-actions` documentou confirmação na linha como salvaguarda; o feedback de produto agora prefere o modal de membership para ambos os caminhos (toolbar e linha).

## Goals / Non-Goals

**Goals:**

- Item de linha **Excluir do monitoramento** abre o mesmo modal de membership da toolbar.
- Remover confirm modal e código morto de exclude imediato na linha.
- Manter menu **Ações** da seleção sem Associar/Excluir.

**Non-Goals:**

- Pré-filtrar ou pré-selecionar o cliente clicado no modal.
- Alterar API, DCTFWeb, ou bulk exclude de `ModuleBulkActions`.
- Renomear o item de menu (continua "Excluir do monitoramento").

## Decisions

1. **Reusar `membershipOpen`** — `onExclude` só faz `membershipOpen = true`. Evita segundo modal e duplicação de UI.
2. **Remover confirm + exclude da linha** — `excludeConfirmOpen`, `excludePendingIds`, `excludeBusy`, `requestExcludeFromMonitoring`, `confirmExcludeFromMonitoring`, `excludeFromMonitoring` e o `ShellConfirmModal` de exclusão saem de `Portfolio.vue` se não tiverem outro uso.
3. **Label do item permanece** — o usuário chega ao modal de manipulação pelo atalho de linha; o título do modal continua "Associar clientes" (já cobre incluir/excluir).

## Risks / Trade-offs

- **[Menos confirmação de um clique]** → Mitigação: exclusão só ocorre dentro do modal ao clicar no botão de excluir da lista (ação explícita no fluxo de membership).
- **[Cliente da linha não destacado no modal]** → Aceito nesta change; pré-filtro fica fora de escopo.
