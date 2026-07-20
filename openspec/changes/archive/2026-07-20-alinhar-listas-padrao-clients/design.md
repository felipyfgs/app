## Context

Inventário (~85 pages em `apps/web/app/pages`) classificado em buckets A–E. Referência ouro: `/clients` + `ClientCatalogList` (ShellDataTable `monitoring-compact`, perPage 20, toolbar shell, footer `mt-auto`, mobile cards). Kit de componentes já entregue/em apply na change `shell-ui-kit` (`panel-shell-kit`).

Esta change é **adoção**, não criação do kit. Escopo W0+W1+W2 (listas + chrome). Settings/split ficam fora (justificativa: outra anatomia ui-archetype; manter ≤1 capability e sessão focada).

## Goals / Non-Goals

**Goals:**

- Toda lista offset do inventário B sem `<UTable`.
- Listas A alinhadas ao checklist ouro (preset, toolbar, footer, chrome).
- Chrome canônico `ShellPagePanel`/`ShellPageNavbar` nas listas autenticadas cobertas.
- Gate vitest cobre o catálogo migrado.

**Non-Goals:**

- Mailbox, WorkQueue, calendar, TeamList/Departments, home, auth.
- Forçar per-page em feeds cursor (syncs/health/docs Catalog) — usar footer off ou InfiniteLoader.
- Copiar domínio de clientes (KPIs A1, bulk categorias, etc.).

## Decisions

### D1 — Régua = ClientCatalogList, não ModuleTable

- ModuleTable já compõe ShellDataTable; o ouro de anatomia de page é clients (filhos no `#body`, KPI+toolbar).
- Carteiras monitoring mantêm ModuleTable; alinham densidade/footer via kit já composto.

### D2 — Preset `monitoring-compact` vs `dashboard`

- Listas operacionais densas (clients, closing, work, imports, monitoring) → `monitoring-compact`.
- Reporting admin (serpro usage, settings usage) → pode permanecer `dashboard` se a densidade atual for intencional; documentar na migração.

### D3 — Docs Catalog é cursor

- Migrar grade para ShellDataTable com `show-footer=false` (ou footer só contagem) + `ShellInfiniteTableLoader` / load-more existente.
- Não fingir paginação offset.

### D4 — Detalhe `monitoring/clients/[clientId]`

- Migrar UTables de seção para ShellDataTable uma a uma (mini-listas); não transformar a page inteira em lista customers.

### D5 — Dependência `shell-ui-kit`

- Código do kit deve existir no tree antes do apply desta change.
- Ownership: não editar `openspec/changes/shell-ui-kit/**`.

## Risks / Trade-offs

- **[Risk] shell-ui-kit incompleto/regredido** → Mitigation: smoke nos componentes Shell* antes de W1; relação bloqueante.
- **[Risk] Docs cursor + DataTable conflita** → Mitigation: D3; testes manuais load-more.
- **[Trade-off] Settings/split fora** → Aceito; inventário no design serve de backlog.

## Migration Plan

1. W0 audit + alinhamento A (preset/chrome/toolbar).
2. W1 migrar B na ordem: ClientListDashboard → docs Catalog/ByClient → serpro → usage → clientId tables.
3. W2 ShellPagePanel em listas cobertas.
4. Gate + vitest.
5. Rollback: reverter commits; sem migração de API.

## Mapa de dependências

```text
C0 shell-ui-kit (panel-shell-kit) --bloqueante:apply--> C1 alinhar-listas-padrao-clients
                                                              └─ desbloqueia (coordenada):
                                                                   shell-ui-docs-workspace
                                                                   shell-ui-settings
                                                                   shell-ui-split
```

## Inventário (catálogo permanente desta change)

### A — Já ShellDataTable (alinhar)

clients (ouro), exports, closing, docs/imports(+id), work/processes|templates, admin/offices, syncs, health, monitoring carteiras (ModuleTable).

### B — UTable residual (migrar)

docs Catalog/ByClient (+ pages docs), ClientListDashboard, admin/serpro catalog|contracts|usage, settings/usage, monitoring/clients/[clientId] seções.

### C/D/E — Fora do escopo

C mestre–detalhe; D settings forms; E home/auth/stubs.

## Open Questions

- Nenhuma bloqueante.
