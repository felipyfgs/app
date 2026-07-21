## Context

A coluna Situação da carteira Simples/MEI usa `declaration_state` (PGDAS-D) ou `debt_state` (PGMEI). Clientes sem outorga e-CAC (`procuracao_status=missing`) caem em «Não verificado» fiscal. O catálogo de clientes já projeta `procuracao_status` via `ClientProcuracaoValidityResolver`; o portfolio não.

## Goals / Non-Goals

**Goals:**

- Expor `procuracao_status` no detail do portfolio `simples_mei` (batch, sem N+1).
- Coluna Situação: se `missing`, badge «Sem procuração» com precedência sobre estado fiscal.

**Non-Goals:**

- KPI strip / filtro novo.
- Chamada SERPRO na listagem.
- Outros módulos de monitoring.

## Decisions

1. **Reusar `ClientProcuracaoValidityResolver` + `ClientProcuracaoSync`** (canônico; snapshot só fallback por environment ativo do office/profile).  
2. **Precedência UI só para `missing`** — pedido explícito «Sem procuração»; `expired` continua podendo mostrar estado fiscal ou, se desejado depois, «Vencida» (fora desta change).  
3. **Campo no `detail`**, não no top-level `situation` FiscalSituation — evita distorcer KPIs Em dia/Pendente.

## Risks / Trade-offs

- **[Snapshot ausente = unverified]** Cliente nunca sincronizado não vira «Sem procuração» até sync oficial marcar `missing` → aceitável (fail-closed sem inventar ausência).
- **[N+1]** Mitigação: carregar syncs/snapshots em lote por `client_ids`.

## Migration Plan

- Deploy API+web; sem migration.
- Rollback: remover campo e precedência UI.
