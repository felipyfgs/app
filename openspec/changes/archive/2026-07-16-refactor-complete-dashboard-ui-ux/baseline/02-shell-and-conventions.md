# Seção 2 — Shell e convenções (evidência)

## 2.3 Layout `default.vue`

Árvore idêntica ao template `layouts/default.vue`:

- `UDashboardGroup unit="rem"`
- `UDashboardSidebar` collapsible + resizable + `bg-elevated/25`
- header → `OfficeIdentity` (não `TeamsMenu`)
- search button + 2× `UNavigationMenu` (primário + `mt-auto`)
- footer → `UserMenu`
- `UDashboardSearch` + `NotificationsSlideover`

## 2.4 OfficeIdentity

- Troca só entre memberships da API `tenants/*`
- `aria-label` / `title` no estado recolhido
- Sem seletor livre de `office_id`

## 2.5 UserMenu

- Perfil + papel + e-mail
- 2FA (setup se pendente; indicador se ADMIN)
- Configurações/Admin por permissão
- Aparência claro/escuro
- Logout sem itens de demonstração do template

## 2.6 NotificationsSlideover

- Estados: loading / empty / error / error com dados preservados
- Deep-links sanitizados (`/health`, `/clients`, `/syncs`, …)
- Reset em `sessionEpoch` e troca de identidade

## 2.7 Navegação

- Fonte única: `utils/navigation.ts` + `quickActions` + `defineShortcuts` em `useDashboard`
- Atalhos: `g-h/c/n/d/e/f/s/o/m/w/k/u/a`, `n` alertas

## 2.8 OperationalContext

- Componente: `components/OperationalContext.vue`
- Apenas chips; não envolve `UDashboardPanel`

## 2.9 Tabelas

- Únicos presets: `DASHBOARD_TABLE_UI`, `DENSE_DASHBOARD_TABLE_UI`, `COMPACT_DASHBOARD_TABLE_UI` em `utils/table-ui.ts`
- Anatomia de slots de `customers.vue` / `HomeSales.vue`

## 2.10 / 2.11

- Helpers: `utils/async-ui.ts`, `utils/overlay-ui.ts`
- Testes: `tests/unit/async-ui.test.ts`

## 2.12 Reset tenant

- `bumpSessionEpoch` + `resetTenantScopedUi` + full reload em `switchTo`
- Páginas com `watch(sessionEpoch)` já existentes (monitoring, notes, settings, …)
