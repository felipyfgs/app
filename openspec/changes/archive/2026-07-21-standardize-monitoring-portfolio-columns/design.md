## Context

Carteiras monitoring compartilham `MonitoringModuleTable`, mas cada módulo montava colunas ad hoc. A change anterior introduziu spine + coluna de envio misturada (status+Send+Switch sob Hist. comunicação) e ícones de preview/info em Ações. O produto pediu refino: Situação/Últ. Declaração no início onde couber; Envio e Hist. comunicação separados; Ações só ⋮; Editar cliente no menu.

## Goals / Non-Goals

**Goals**

- Spine refinada (com/sem Últ. Declaração) nas carteiras: PGDAS-D, PGMEI, DCTFWeb, MIT, SITFIS, FGTS, Declarações.
- Coluna **Envio** (Send+Switch) distinta de **Hist. comunicação** (só rastreio).
- Ações = só ⋮; preferências e histórico no menu; Editar cliente via `ClientsClientFormModal`.
- Provider de envio fail-closed (config off) — sem mudança.

**Non-Goals**

- Ligar Mail/WhatsApp em produção por default.
- Listas de artefato (Guias, Parcelamentos, Cadastros, Processos, Mailbox).
- Redesign do formulário de cliente.

## Decisions

1. **Spine com declaração** — `Situação · Últ. Declaração · [valores] · Cliente · Ações · Envio · Hist. comunicação · Consulta` (PGDAS, DCTFWeb, Declarações PGDAS).

2. **Spine sem declaração** — `Situação · Cliente · [domínio] · Ações · Envio · Hist. comunicação · Consulta`.

3. **Envio ≠ Hist.** — Builders `buildMonitoringEnvioColumn` + `buildMonitoringTrackingColumn` (ou o par `buildMonitoringEnvioAndTrackingColumns`).

4. **Ações só ⋮** — Remover ícones message/info da grade; prévia via Send; preferências no ⋮.

5. **Editar cliente** — Composable `useMonitoringClientEdit` + modal existente; refresh da carteira no `saved`.

6. **Associar clientes** — Mantém filtro SN/MEI da change anterior.

## Risks / Trade-offs

- [Tabelas divergentes quebram testes de fidelidade] → Mitigação: atualizar asserts de ordem/ids no mesmo PR.
- [Hub Declarações sem pipeline de envio] → Colunas Envio/Hist. fail-closed quando não há `communication`.

## Migration Plan

Deploy atômico FE. Sem migration de schema. Rollback: reverter PR.

## Open Questions

Nenhuma — decisões fechadas no plano do produto.
