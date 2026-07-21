## Why

Na carteira Simples/MEI, o item de linha **Excluir do monitoramento** abre um `ShellConfirmModal` de confirmação direta. O fluxo esperado é abrir o modal **Associar clientes** (`AssociateMonitoringClientsModal`), onde o usuário manipula inclusão/exclusão da carteira — o mesmo controle usado pelo botão da toolbar.

## What Changes

- Ação de linha **Excluir do monitoramento** (PGDAS-D e PGMEI) passa a abrir o modal de membership (associar/excluir), em vez do modal de confirmação.
- Remover o `ShellConfirmModal` e o fluxo `requestExcludeFromMonitoring` / exclude imediato da linha em `Portfolio.vue`.
- Atualizar testes unitários que fixam a confirmação de exclusão por linha.
- Non-goals: não alterar a API de membership; não colocar Associar/Excluir no menu **Ações** da seleção; não pré-selecionar o cliente no modal nesta change; não mudar DCTFWeb nem bulk exclude de outras carteiras.

## Capabilities

### New Capabilities

<!-- nenhuma — reusa contratos já deltaados em changes de portfolio -->

### Modified Capabilities

- `simples-mei-selection-actions-menu`: Excluir pela linha abre o modal de membership, não confirmação destrutiva.
- `simples-mei-portfolio-ux`: Alinhar salvaguarda de membership — Associar e Excluir (linha) usam o modal dedicado.

## Impact

- Web: `apps/web/app/components/monitoring/simples-mei/Portfolio.vue`, teste `apps/web/tests/unit/simples-mei-quick-consult.test.ts`.
- Sem mudança de API, Compose ou SERPRO.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: modal `AssociateMonitoringClientsModal`, membership API, toolbar Associar (changes `monitoring-rail-and-portfolio-membership`, `compact-simples-mei-selection-actions`)
- Depende de: nenhuma (change ativa)
- Capability/contrato: `simples-mei-selection-actions-menu`, `simples-mei-portfolio-ux`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: UX correta de exclusão por linha na carteira
- Paralelismo: evitar editar o mesmo `Portfolio.vue` em paralelo com changes que toquem membership de linha
