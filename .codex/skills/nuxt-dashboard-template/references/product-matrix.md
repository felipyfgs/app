# Matriz template ↔ produto NFS-e

## Shell

| Template | Produto | Notas |
|----------|---------|--------|
| `layouts/default.vue` | `frontend/app/layouts/default.vue` | Forma idêntica; nav via `utils/navigation.ts` |
| `TeamsMenu.vue` | `OfficeIdentity.vue` | Sem troca de office |
| `UserMenu.vue` | `UserMenu.vue` | Logout, 2FA, tema; sem billing demo |
| `NotificationsSlideover.vue` | `NotificationsSlideover.vue` | Dados reais / vazio seguro |
| `useDashboard.ts` | `useDashboard.ts` | Shortcuts + me + flags permissão |
| `app.vue` | `app.vue` | `UApp`, loading indicator |
| — | `layouts/auth.vue` | Login / 2FA (fora do template dashboard) |

## Navegação

| Template link | Produto |
|---------------|---------|
| Home `/` | Dashboard `/` |
| Inbox `/inbox` | Notas `/notes` (mestre–detalhe) |
| Customers `/customers` | Clientes `/clients` |
| Settings `/*` | Detalhe cliente seções; Admin se ADMIN |
| — | Exportações `/exports`, Sincronizações `/syncs` |
| Feedback/Help externos | Docs ADN (secundário) |

## Páginas

| Rota produto | Arquétipo | Arquivo(s) template a copiar | Pontos dinâmicos |
|--------------|-----------|------------------------------|------------------|
| `/` | Home | `pages/index.vue`, `components/home/*` (só se chart/stats) | KPIs ops, refresh API, quick actions |
| `/clients` | Lista | `pages/customers.vue`, `customers/AddModal.vue` | list/page/q server-side, create modal, row → `/clients/:id` |
| `/clients/[id]` | Settings seções | `pages/settings.vue` + cards de `settings/index.vue` / members | seções resumo/estab/cert/sync; sem PFX raw |
| `/notes` | Mestre–detalhe | `pages/inbox.vue`, `inbox/*` | filtros, cursor, preview nota |
| `/notes/[accessKey]` | Detalhe (mail) ou página full | `InboxMail.vue` como base visual | XML/projeções; sem material sensível |
| `/exports` | Lista | `customers.vue` | jobs export, status badge |
| `/syncs` | Lista | `customers.vue` | cursores, bloqueios, last NSU |
| `/admin` | Lista ou settings | `customers.vue` ou `settings/*` | só ADMIN confirmado |
| Login / 2FA | Auth (fora) | — | `layouts/auth.vue` |

## Componentes de domínio já no produto

| Produto | Papel | Arquétipo de origem |
|---------|-------|---------------------|
| `clients/ClientCreateModal.vue` | Create | `customers/AddModal.vue` |
| `clients/ClientEstablishments.vue` | Lista em detalhe | members list / table |
| `clients/ClientCredentialPanel.vue` | Form card | settings form cards |
| `clients/ClientSyncPanel.vue` | Ações + status | settings + badges |
| `notes/NotesWorkspace.vue` | Split view | `inbox.vue` |
| `notes/NotesCatalog.vue` | Lista | `InboxList` |
| `notes/NotesDetail.vue` | Painel | `InboxMail` |
| `notes/NotesFilters.vue` | Toolbar filtros | toolbar home/customers |

## Regras de domínio que a UI deve respeitar

- Produto **interno do escritório** (não portal de cliente final)
- Um e-CNPJ A1 por raiz; CNPJ 14 chars texto uppercase
- Sem avançar NSU na UI; sync é job/backend
- Nunca exibir PFX, senha, private key, PEM
- `office_id` só da sessão autenticada
