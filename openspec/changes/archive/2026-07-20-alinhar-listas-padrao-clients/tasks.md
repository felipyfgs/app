## 1. N0 — Auditar e alinhar inventário A

- [x] 1.1 Checklist vs ouro `/clients`: preset, toolbar, footer/per-page nas pages A (exports, closing, imports, work, offices, syncs, health)
- [x] 1.2 Alinhar `ui-preset="monitoring-compact"` nas listas operacionais densas que ainda usam só `dashboard`
- [x] 1.3 Confirmar carteiras ModuleTable já compostas; ajustar só gaps de chrome/footer se houver
- [x] 1.4 Evidência: nota curta no PR/checklist do que ficou `dashboard` de propósito (reporting/cursor)

## 2. N1 — Migrar inventário B (UTable residual)

- [x] 2.1 Migrar `ClientListDashboard` → `ShellDataTable`
- [x] 2.2 Migrar `docs/Catalog.vue` e `docs/ByClient.vue` → `ShellDataTable` (cursor: sem footer offset falso)
- [x] 2.3 Migrar `admin/serpro/catalog.vue`, `contracts.vue`, `usage.vue` → `ShellDataTable`
- [x] 2.4 Migrar `settings/usage.vue` (+ `/conta/consumo`) → `ShellDataTable`
- [x] 2.5 Migrar UTables de seção em `monitoring/clients/[clientId].vue` → `ShellDataTable`

## 3. N2 — Chrome Shell nas listas cobertas

- [x] 3.1 Trocar casca `UDashboardPanel`+navbar ad hoc por `ShellPagePanel`/`ShellPageNavbar` nas pages de lista A/B (exceto ModuleTable que já encapsula painel)
- [x] 3.2 Padronizar `ShellNavbarRefresh` / `ShellNavbarBack` onde houver atualizar/voltar
- [x] 3.3 Alinhar `clients.vue` chrome ao mesmo contrato (manter fragmento `#body` ouro)

## 4. N3 — Gate e verificação

- [x] 4.1 Expandir `tests/unit/shell-list-migration-gate.test.ts` com inventário A+B migrado
- [x] 4.2 Rodar vitest do gate + testes de layout/lista tocados
- [x] 4.3 Checklist manual: `/clients`, uma carteira monitoring, `/docs` catalog, `/exports` — densidade/footer/chrome coerentes
