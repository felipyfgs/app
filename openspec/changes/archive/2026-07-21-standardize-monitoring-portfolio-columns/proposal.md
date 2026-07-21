## Why

As carteiras de monitoramento (DCTFWeb, PGDAS, PGMEI, SITFIS, FGTS, Declarações, MIT) precisam de uma spine única e de colunas de comunicação claras. A primeira passada misturou Send/Switch em Hist. comunicação e deixou ícones redundantes em Ações; o produto pediu refino.

## What Changes

- Spine com declaração: `Situação · Últ. Declaração · [valores] · Cliente · Ações · Envio · Hist. comunicação · Consulta` (PGDAS-D, DCTFWeb, Declarações PGDAS).
- Spine sem Últ. Declaração: `Situação · Cliente · [domínio] · Ações · Envio · Hist. comunicação · Consulta`.
- Separar **Envio** (Send + Switch) de **Hist. comunicação** (só rastreio).
- **Ações** = só menu ⋮; item **Editar cliente** (modal de cadastro existente); preferências e histórico no menu.
- Remover ícones de preview/info de comunicação da grade (prévia via Send).
- Manter Associar clientes filtrado (SN/MEI) e provider fail-closed.
- **BREAKING** (UX): supersede o modo “somente leitura / sem switch” de comunicação nas carteiras.

Non-goals: provider Mail/WhatsApp ligado por default; Guias/Parcelamentos/Cadastros/Processos/Mailbox.

## Capabilities

### New Capabilities

- `monitoring-portfolio-columns`: contrato de spine das carteiras por cliente, colunas Envio e Hist. comunicação distintas, Ações só ⋮ com Editar cliente, e filtro de elegibilidade do modal Associar clientes.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — main specs vazias)

## Impact

- Web: `monitoring-table-columns.ts`, builders `*table.ts`, páginas monitoring, `useMonitoringClientEdit`, testes de layout.
- API: já entregue na passada anterior (prefs/send/wrappers); sem mudança obrigatória neste refino.
- Config: kill-switch de provider fail-closed.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: membership, escopo SN/MEI, pipeline de comunicação
- Depende de: nenhuma change ativa bloqueante
- Desbloqueia: envio efetivo com provider (change futura)
