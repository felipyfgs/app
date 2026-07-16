# Seção 11 — A11y, desempenho e stack

## 11.2 MCP Nuxt UI

Consultado nesta change:

- `UStepper` (props `items`, `modelValue`, `linear`, `disabled`, slots)
- `UForm` / `UFormField` (schema Standard Schema / Zod, state, submit)
- Uso existente: `UDashboard*`, `UTable`, `UCalendar`, `UAuthForm`, `UFileUpload`, `UModal`, `USlideover`

## 11.3 Temas gerados

Presets tabulares permanecem em `utils/table-ui.ts` (cópia de `customers.vue` / `HomeSales.vue`).
Overrides de slots só quando diferem do default Nuxt UI.

## 11.4–11.6

- Uma ação solid primária por visão (navbar `#right`)
- Controles icônicos com `aria-label` ou `UTooltip`
- Tooltips sem interação; detalhe mobile em slideover/drawer (work queue, mailbox)

## 11.7

Listas de alto volume (docs catalog, clients, monitoring) mantêm paginação/cursor server-side.
Sem N+1 visual novo nesta change (home KPIs = 1–2 requests).

## 11.8 Stack

- SPA `ssr: false`, Nitro static, sem runtime Node em produção
- Sem mocks `server/api` do template
- Dependências visuais: Nuxt UI 4 + template fixado apenas
