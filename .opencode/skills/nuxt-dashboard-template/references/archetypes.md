# Inventário e arquétipos do template

Path: `.reference/nuxt-dashboard-template/` · commit `0f30c09`

## Árvore relevante

```text
app/
├── app.vue                          # UApp + NuxtLayout/Page + theme-color
├── app.config.ts                    # tokens Nuxt UI
├── assets/css/main.css
├── composables/useDashboard.ts      # shortcuts + slideover notifications
├── error.vue
├── layouts/default.vue              # SHELL
├── types/index.d.ts
├── utils/index.ts
├── components/
│   ├── NotificationsSlideover.vue
│   ├── TeamsMenu.vue                # NÃO usar multi-escritório
│   ├── UserMenu.vue
│   ├── customers/AddModal.vue       # modal + form Zod
│   ├── customers/DeleteModal.vue
│   ├── home/                        # stats, chart, sales, period, range
│   ├── inbox/InboxList.vue + InboxMail.vue
│   └── settings/MembersList.vue
├── pages/
│   ├── index.vue                    # HOME
│   ├── customers.vue                # LISTA ADMIN
│   ├── inbox.vue                    # MESTRE–DETALHE
│   ├── settings.vue                 # SETTINGS layout + NuxtPage
│   └── settings/{index,members,notifications,security}.vue
server/api/                          # mocks — NUNCA como runtime do produto
```

## Shell (`layouts/default.vue`)

Estrutura:

```text
UDashboardGroup
├── UDashboardSidebar
│   ├── #header → TeamsMenu | (produto: OfficeIdentity)
│   ├── #default
│   │   ├── UDashboardSearchButton
│   │   ├── UNavigationMenu (links principais)
│   │   └── UNavigationMenu.mt-auto (secundários)
│   └── #footer → UserMenu
├── UDashboardSearch :groups
├── <slot />  (página)
└── NotificationsSlideover
```

Estado: `open` (sidebar mobile).  
Atalhos em `useDashboard`: `g-h`, `g-i`, `g-c`, `g-s`, `n`.

## Home (`pages/index.vue`)

```text
UDashboardPanel#home
├── #header
│   ├── UDashboardNavbar "Home"
│   │   ├── #leading → SidebarCollapse
│   │   └── #right → bell (chip) + UDropdownMenu plus
│   └── UDashboardToolbar → DateRangePicker + PeriodSelect
└── #body
    ├── HomeStats
    ├── HomeChart
    └── HomeSales
```

**Adaptar:** títulos, cards KPI do domínio, fontes de dados; toolbar só se houver filtro temporal real.

## Lista admin (`pages/customers.vue`)

```text
UDashboardPanel#customers
├── #header
│   └── UDashboardNavbar
│       ├── #leading → Collapse
│       └── #right → CustomersAddModal (ação primária)
└── #body
    ├── toolbar: UInput search | delete bulk | USelect filter | Display columns
    ├── UTable (ui elevado, loading)
    └── footer: contagem selected + UPagination
```

Padrões de coluna: checkbox select, avatar+nome, sort no header (botão ghost), badge status, actions ellipsis.

**Produto:** paginação **server-side** (meta da API); manter visual do footer e do `UTable` `:ui`.

## Mestre–detalhe (`pages/inbox.vue`)

```text
UDashboardPanel#inbox-1 (resizable, default-size 25%)
├── UDashboardNavbar + badge count + UTabs
└── InboxList

InboxMail (desktop, se selecionado) | empty icon
USlideover (mobile lg-) com InboxMail
```

**Produto:** `/notes` com lista + preview; detalhe full page pode coexistir se necessário, mas o split desktop/mobile segue este arquétipo.

## Settings (`pages/settings.vue`)

```text
UDashboardPanel#settings (body: lg:py-12)
├── #header
│   ├── UDashboardNavbar "Settings"
│   └── UDashboardToolbar → UNavigationMenu highlight (seções)
└── #body
    └── div max-w-2xl mx-auto → <NuxtPage />
```

Subpágina form (`settings/index.vue`):

```text
UForm
├── UPageCard naked horizontal (título + Save)
└── UPageCard subtle
    └── UFormField row (label+description | input) + USeparator…
```

Lista em settings (`members.vue`): `UPageCard` header com search + lista.

**Produto:** detalhe cliente (`/clients/[id]`) usa o **mesmo shell de seções** (toolbar nav), não a lista customers.

## Modais

- `AddModal`: `UModal` trigger = botão New; body `UForm` Zod; Cancel + Create.
- `DeleteModal`: confirmação com count.

## Componentes Nuxt UI recorrentes

| Peça | Componentes |
|------|-------------|
| Shell | `UDashboardGroup`, `UDashboardSidebar`, `UDashboardSearch`, `UNavigationMenu` |
| Página | `UDashboardPanel`, `UDashboardNavbar`, `UDashboardToolbar`, `UDashboardSidebarCollapse` |
| Dados | `UTable`, `UPagination`, `UBadge`, `UCheckbox`, `USelect`, `UInput` |
| Forms | `UForm`, `UFormField`, `UPageCard`, `USeparator`, `UTextarea`, `UModal` |
| Overlay | `USlideover`, `UTooltip`, `UDropdownMenu`, `UChip`, toast via `useToast` |
| Feedback | `UIcon` empty states, loading na table |

## O que NÃO copiar para o produto

| Template | Motivo |
|----------|--------|
| `TeamsMenu` multi-team | Um office por sessão; usar `OfficeIdentity` |
| `server/api/*` | Mocks; API é Laravel/Sanctum |
| Cookie consent toast | Marketing demo |
| “View page source” no search | Irrelevante |
| Seletor livre de cores primárias (opcional) | Produto controla tema |
| Paginação 100% client do demo | Só se a API for local; senão adaptar server-side |
