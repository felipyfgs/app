## Why

Nas carteiras de monitoramento, a coluna Cliente usa `min-w-48`, o que impede o nome de encolher em viewports estreitas (tablet / desktop estreito) e força barra de rolagem horizontal na grade.

## What Changes

- Permitir que a coluna Cliente das carteiras fiscais encolha (`min-w-0`) e truncar o nome com ellipsis em tela pequena.
- Centralizar a meta de largura da coluna Cliente no contrato FE de colunas de monitoramento.
- Atualizar asserts de layout que fixavam `min-w-48` nas carteiras.

Non-goals: lista admin de clientes (`clients-table`); redesign de colunas; cards mobile (já substituem a grade &lt; md).

## Capabilities

### New Capabilities

- `monitoring-client-column-fit`: contrato de encolhimento/truncamento da coluna Cliente nas grades de monitoramento para caber na viewport sem scroll horizontal forçado pelo nome.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — main specs vazias)

## Impact

- Web: `monitoring-table-columns.ts`, builders `*table.ts`, páginas FGTS/Parcelamentos, `FiscalClientCell`, testes `list-table-layout`.
- API: nenhuma.
- Config: nenhuma.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: spine de carteiras (`standardize-monitoring-portfolio-columns` arquivável / código já na main)
- Depende de: nenhuma
- Capability/contrato: `monitoring-client-column-fit`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma
- Paralelismo: livre (só FE de layout)
