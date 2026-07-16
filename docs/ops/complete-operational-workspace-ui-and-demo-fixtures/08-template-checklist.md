# Checklist template — família `/work`

Referência: `.grok/skills/nuxt-dashboard-template/references/checklist.md`  
Template fixado: `.reference/nuxt-dashboard-template` @ `0f30c09`

## `/work` (fila mestre–detalhe)

| Item | Status |
|------|--------|
| Arquétipo Inbox (`inbox.vue` + `InboxList` + `InboxMail`) | OK |
| Slots `#header`/`#body`, dois `UDashboardPanel`, resize 25/20/30 | OK |
| Zero mocks `server/api` do template | OK |
| Loading / vazio / erro | OK |
| ADMIN/OPERATOR/VIEWER em ações | OK |
| Sidebar collapse + split `lg` + slideover mobile | OK |
| Labels pt-BR | OK |

## `/work/calendar`

| Item | Status |
|------|--------|
| Shell Home (navbar + toolbar + body) | OK |
| `UCalendar` só seletor (sem grade horária) | OK |
| Views Mês/Semana/Dia + rail | OK |
| Loading / vazio / erro com last-good | OK |
| Mobile rail em slideover | OK |

## `/work/processes`

| Item | Status |
|------|--------|
| Arquétipo Customers (`customers.vue`) | OK |
| `UTable` + filtros server-side + rodapé | OK |
| Badges status/risco/progresso | OK |

## `/work/processes/{id}`

| Item | Status |
|------|--------|
| Arquétipo Settings (`settings.vue` + seções) | OK |
| `UNavigationMenu` seções URL `section=` | OK |
| Checklist, comentários, histórico allowlisted | OK |
| 404 cross-tenant | OK |

## `/work/templates`

| Item | Status |
|------|--------|
| Lista Customers + modal AddModal-like | OK |
| `UStepper` geração | OK |
| Bloqueio OPERATOR/VIEWER (redirect) | OK |

## Home — Work KPIs

| Item | Status |
|------|--------|
| Bloco separado de fiscal/infra | OK |
| Progresso departamental + deep-links | OK |
| Falha parcial sem desmontar outros blocos | OK |

## Stack

- Forma: skill `nuxt-dashboard-template`
- Props `U*`: MCP Nuxt UI (ver `05-nuxt-ui-mcp-notes.md`)
- Sem substituto de starter/template
