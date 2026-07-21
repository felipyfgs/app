## Why

Na carteira `/monitoring/simples-mei`, ao marcar linhas a toolbar ganha um botão primário **Consultar** ao lado de **Ações**, fugindo do padrão compacto do cadastro de clientes (um único **Ações** com badge de contagem). O atalho de consulta em lote continua necessário, mas não precisa ocupar um botão solto na faixa de filtros.

## What Changes

- Remover o botão primário **Consultar** da toolbar de seleção PGDAS-D (`SelectionActions`).
- Incluir **Consultar** como item do menu **Ações** (com confirmação explícita já existente), visível só com seleção.
- Alinhar PGMEI (`BulkActions`) ao mesmo padrão compacto: consulta em lote dentro de **Ações**, sem botão solto.
- Manter atalho de consulta por linha (coluna) e fail-closed de permissão.
- Atualizar testes de superfície que exigem o botão solto.

Non-goals:
- Não alterar `ListFilterToolbar` canônico nem o adapter `ModuleToolbar`.
- Não mudar API/enfileiramento SERPRO nem flags fail-closed.
- Não redesenhar colunas da tabela nem o botão **Associar clientes**.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `simples-mei-selection-actions-menu`: consulta PGDAS-D da seleção passa a viver no menu **Ações**, não em botão primário solto.
- `simples-mei-portfolio-ux`: consulta rápida em lote deixa de exigir botão toolbar dedicado; permanece via **Ações** + atalho de linha.

## Impact

- Web: `SelectionActions.vue`, `pgdasd-action-items.ts`, `BulkActions.vue` (PGMEI), testes `simples-mei-quick-consult` / action-items.
- API / Compose: nenhum.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: carteira Simples/MEI, domínio PGDAS-D/PGMEI
- Depende de: `simplificar-menu-acoes-simples-mei` (capability `simples-mei-selection-actions-menu`, marco `apply`, relação `bloqueante`); `simples-mei-minimal-consult` (capability `simples-mei-portfolio-ux`, marco `apply`, relação `coordenada`)
- Capability/contrato: `simples-mei-selection-actions-menu`, `simples-mei-portfolio-ux`
- Marco exigido: `apply` das upstream
- Relação: `bloqueante` + `coordenada`
- Desbloqueia: toolbar de seleção compacta (paridade com `/clients`)
- Paralelismo: não paralelizar com edits nos mesmos arquivos de SelectionActions / BulkActions
