## Why

Na carteira Simples Nacional (`/monitoring/simples`) e demais carteiras padronizadas, a coluna **Ações** fica no meio da grade (antes de Comunicação/Consulta) e o controle é só o ícone ⋮ — difícil de reconhecer e fora do padrão visual dos botões rotulados da toolbar. O operador precisa de Ações no fim da linha, como botão com rótulo.

## What Changes

- **BREAKING** (contrato de UI): spine canônica passa a terminar em **Ações** (não mais Consulta).
- Ordem nova: `… · Cliente · Comunicação · Consulta · Ações`.
- Célula Ações deixa de ser botão quadrado só com ícone ⋮; passa a ser o mesmo padrão Nuxt UI das Ações em massa: `UDropdownMenu` + `UButton` com `label: 'Ações'`, `icon` e `variant: 'subtle'`.
- Ajuste do helper compartilhado `buildMonitoringActionsMenuCell` e reordenação nas builders das carteiras por cliente (PGDAS-D, PGMEI, DCTFWeb, MIT, SITFIS, FGTS, Declarações).
- Testes de contrato de ordem/source atualizados.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `monitoring-portfolio-columns`: spine com Ações por último; célula Ações como botão rotulado (não só ⋮).

## Impact

- Web: `apps/web/app/utils/monitoring-table-columns.ts`, builders `pgdasd-table`, `pgmei-table`, `dctfweb-table`, `sitfis-table`, `declarations-table`, página `fgts.vue`, testes em `monitoring-portfolio-columns.test.ts` / layout.
- Specs: delta em `openspec/specs/monitoring-portfolio-columns`.
- Sem mudança de API, backend ou flags.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: `openspec/specs/monitoring-portfolio-columns`
- Depende de: nenhuma
- Desbloqueia: implementação UI das carteiras
- Paralelismo: não conflita com changes ativas de domínio PGDAS/SITFIS (ownership de capability distinto)

### Non-goals

- Não altera itens do menu ⋮ nem fluxo de consulta/comunicação.
- Não liga envio externo, SERPRO live nem flags fail-closed.
- Não redesenha o shell; não mexe em carteiras fora da spine compartilhada (Guias, Parcelamentos, etc.) salvo se já usarem o helper canônico.
