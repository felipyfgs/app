## Context

Listas N1 do painel usam `ShellDataTable` (e `ModuleDataTable` nas carteiras). A referência é `/clients`: `sortHeader` só com whitelist de API, `manualSorting`, reload e URL `sort`/`sort_direction`. Guias, registrations e tax-processes exibem chrome de ordenação sem backend; ByClient ordena no servidor sem sync URL; empty de algumas listas fica fora do `#empty`.

## Goals / Non-Goals

**Goals:**

- Eliminar sort fantasma (UI sem API/reload).
- Ligar ordenação real em Guias ao endpoint já existente.
- Sync URL de sort em ByClient; limpar whitelist `cnpj` em clientes.
- Empty de lista N1 no slot `#empty` do `ShellDataTable`.
- Guardrail de teste `sortHeader` ↔ contrato.

**Non-Goals:**

- Unificar toolbars/KPIs/`DataTableFilterRoot` cru.
- Unificar `ui-preset` (`dashboard` vs `monitoring-compact`).
- Inventar sort em admin/work sem suporte de API.
- Mudanças de API Laravel, flags SERPRO/SEFAZ/MEI, mei no Compose.

## Decisions

1. **Guias: wire sort, não migrar para `useFiscalModulePortfolio` nesta change**  
   A página já usa `useServerPage` + `ModuleTable`. Estender load com `sort`/`direction` e sync de query sem reescrever o portfolio. Default: `due_at` desc. Coluna `id` deixa de ser ordenável.

2. **Registrations / tax-processes: desabilitar UI de sort**  
   API sem parâmetros de sort — preferir header plain + `enableSorting: false` a inventar endpoint.

3. **Installments `situation`: `enableSorting: false`**  
   Evita mapear coluna sem garantia no `SORT_COLUMN_TO_API` do portfolio.

4. **Clientes: dropar `cnpj` da whitelist de sort**  
   Não há coluna CNPJ ordenável na grade (CNPJ vive sob razão social).

5. **Empty parity só em listas N1 óbvias**  
   syncs, health, serpro/contracts (+ ByClient/Catalog se empty estiver fora). Widgets com `show-footer=false` não forçam sort.

## Risks / Trade-offs

- [Guias: `useServerPage().syncUrl()` limpa query] → Mitigação: sincronizar `sort`/`sort_direction` explicitamente no mesmo fluxo de URL, sem apagar filtros.
- [Remover sortHeader muda expectativa visual] → Mitigação: headers plain são o contrato correto quando não há API.
- [ByClient URL sync pode colidir com query do workspace docs] → Mitigação: reusar schema mínimo (`sort`, `sort_direction`) sem alterar outros params.

## Migration Plan

- Deploy frontend-only; rollback = revert do commit web.
- Sem migration de banco nem feature flag.
