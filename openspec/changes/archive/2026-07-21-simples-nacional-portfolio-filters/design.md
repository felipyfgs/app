## Context

Carteira Simples Nacional usa `MonitoringSimplesMeiPortfolio` com `submodule=PGDASD`. O popover Filtro (`ShellListFilterToolbar` via `ModuleToolbar`) hoje só declara Cliente + Competência; situação vem das KPIs. Comunicação (`tracking_status`) é enriquecida na linha, sem filtro de lista.

Referência UX: DCTFWeb já coloca Situação no popover; Clientes usa o mesmo shell de chips.

## Goals / Non-Goals

**Goals:**

- Popover Filtro PGDASD: Situação · Cliente · Competência · Envio (Enviado / Não enviado).
- Situação: reutilizar API `situation` e sincronizar com KPI.
- Envio: novo param de query (ex. `send_status=sent|not_sent`) aplicado só em `simples_mei` + PGDASD.
- Presets/URL refletem os novos eixos.

**Non-Goals:**

- Alterar filtros da carteira MEI.
- Chip de regime/categorias/procuração.
- Granularidade completa de `CommunicationDispatchStatus` no popover (só binário enviado/não).
- Mudar colunas da grade.

## Decisions

1. **Escopo só PGDASD** — `filterConfig` no ramo `isPgdasd`; ramo PGMEI intacto.

2. **Situação no popover** — `{ key: 'situation', kind: 'option', label: 'Situação' }` com `fiscalSituationFilterItems`. KPI e chip leem/escrevem o mesmo `filters.situation` (como DCTFWeb).

3. **Envio binário** — valores de UI `sent` | `not_sent`:
   - **Enviado:** tracking agregado em `{ SENT, DELIVERED, READ, PARTIAL, … }` (qualquer status “já houve envio/progresso”), exclui `NO_HISTORY` e `NOT_CONFIGURED`.
   - **Não enviado:** `NO_HISTORY` | `NOT_CONFIGURED` (e ausência de dispatch).
   - Param API: `send_status` (CSV opcional). Nome distinto de `delivery_status` (declarações).

4. **SQL de envio** — `whereExists` / agregação alinhada a `PgdasdCommunicationService::trackingStatus` sobre preferências/dispatches do módulo `simples_mei` + superfície PGDAS-D; extrair helper reutilizável se evitar duplicar regras.

5. **FE key** — `sendStatus` em `MonitoringFilterValue` / portfolio filters; chip label «Envio».

## Risks / Trade-offs

- [KPI + chip Situação “duplicados”] → Mitigação: mesmo estado; chip permite combinar com Envio/Cliente sem clicar KPI.
- [Definição binária de Enviado ambígua (QUEUED/FAILED)] → Decisão: `QUEUED`/`FAILED`/`PARTIAL` contam como **Enviado** (já houve tentativa/fila); só `NO_HISTORY`/`NOT_CONFIGURED` = Não enviado. Documentar no spec.
- [Performance do exists em dispatches] → Índice/cliente_id já usados pelo enrichment; testar com office típico.

## Migration Plan

Deploy API+FE atômico. Sem migration de schema. Rollback: reverter PR (param ignorado = lista completa).

## Open Questions

Nenhuma — binário Enviado/Não enviado fechado acima.
