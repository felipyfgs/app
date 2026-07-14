---
name: nuxt-dashboard-template
description: >
  Usar o template oficial Nuxt UI Dashboard (fixado em .reference/nuxt-dashboard-template)
  como base obrigatória de qualquer UI do painel: copiar o arquétipo, adaptar só nomes,
  navegação e pontos dinâmicos. Encadeia com skills/MCPs nuxt e nuxt-ui. Use quando
  criar/alterar páginas Vue do frontend, shell, tabelas, settings, mestre–detalhe,
  modais, dashboard, fidelidade visual, ou /nuxt-dashboard-template /frontend-nuxt-stack.
---

# Nuxt Dashboard Template (base obrigatória)

## Stack relacionado (sempre)

Esta skill **não** trabalha sozinha. No monorepo, a pilha é:

| Camada | O quê | Quando |
|--------|--------|--------|
| **Esta skill** | Forma da tela (arquétipo em `.reference/`) | Sempre em UI autenticada |
| **Skill + MCP `nuxt-ui`** | Props/slots/examples dos `U*`, ícones, theming | Dúvida de API de componente |
| **Skill + MCP `nuxt`** | Nuxt 4 (`app/`, middleware, pages, config) | Framework, não layout visual |
| **AGENTS.md / OpenSpec** | Domínio fiscal, tenancy, auth | Regras de negócio |

Fluxo combinado e anti-padrões: [references/stack.md](./references/stack.md).  
Orquestrador do projeto: skill **`frontend-nuxt-stack`** (`/frontend-nuxt-stack`).

**Conflito template vs docs MCP:** estrutura/slots/classes do template vencem; MCP só refina props sem redesenhar.

### MCP — chamar quando

- **`nuxt-ui`**: `search_components`, `get_component` / `get_component_metadata`, `search_icons` (lucide)
- **`nuxt`**: docs de routing/middleware/SPA se o adaptador Nuxt for incerto

Não use MCP para “inventar” um dashboard novo — a base fixa é o clone local @ `0f30c09`.

## Fonte de verdade

| Item | Valor |
|------|--------|
| Path | `.reference/nuxt-dashboard-template/` |
| Commit fixado | `0f30c09` |
| App destino | `frontend/` |
| Demo oficial | https://dashboard-template.nuxt.dev/ |

**Regra de ouro:** copiar o código do template → adaptar o mínimo → **não** reimplementar “parecido”.

Ler também, sob demanda:

- [references/stack.md](./references/stack.md) — skills + MCPs amarrados
- [references/archetypes.md](./references/archetypes.md) — arquétipos e inventário
- [references/product-matrix.md](./references/product-matrix.md) — template ↔ rotas do produto
- [references/checklist.md](./references/checklist.md) — checklist por página

## Fluxo obrigatório (toda implementação de UI)

```text
1. Classificar a tela no arquétipo (shell / home / lista / mestre-detalhe / settings / modal)
2. Abrir o arquivo-fonte EXATO em .reference/nuxt-dashboard-template/
3. Copiar markup + slots + classes + ordem de blocos do template
4. Se prop/slot/ícone U* for incerto → MCP nuxt-ui (não mudar a composição)
5. Se dúvida de Nuxt 4 (middleware, pages, SPA) → skill/MCP nuxt
6. Adaptar APENAS:
   - labels / títulos (pt-BR do produto)
   - rotas e itens de navegação
   - types / fetch → API real (useApi, Sanctum) em vez de /api/* mock
   - permissões (ADMIN/OPERATOR/VIEWER)
   - office_id / tenancy (nunca seletor de escritório livre)
   - dados dinâmicos (loading, vazio, erro, paginação server-side)
7. Remover mocks, demos, TeamsMenu multi-tenant, cookie toast de marketing
8. Diff mental: a árvore de componentes Nuxt UI deve continuar reconhecível vs origem
9. Não avançar NSU/fiscal/backend sem necessidade — escopo é UI
```

### Proibido

- Reescrever layout “do zero” com Nuxt UI “equivalente”
- Importar `server/api/*` do template ou mocks como verdade de negócio
- Copiar seletor de times (`TeamsMenu`) como seletor de escritório
- Client-side pagination do demo se a API for server-side (manter **visual** da tabela; trocar só a fonte de páginas)
- Expor PFX, senha, PEM, material sensível em UI/logs
- Inventar design system paralelo

### Permitido (adaptação mínima)

- Renomear `Customers` → `Clientes`, ids de panel, strings
- Trocar `useFetch('/api/...')` por composable tipado da API Laravel
- Esconder colunas/ações por permissão
- `UPagination` com `total`/`page` da API (manter barra inferior do template)
- Textos e empty states em pt-BR

## Arquétipos (escolha rápida)

| Arquétipo | Origem no template | Usar quando |
|-----------|-------------------|-------------|
| **Shell** | `app/layouts/default.vue` + `UserMenu` + `NotificationsSlideover` + `useDashboard` | Layout autenticado, nav, command palette |
| **Home / dashboard** | `app/pages/index.vue` + `components/home/*` | Painel com navbar + toolbar + cards/stats/charts |
| **Lista admin** | `app/pages/customers.vue` + `components/customers/*` | Tabela + filtros + ação primária + paginação |
| **Mestre–detalhe** | `app/pages/inbox.vue` + `components/inbox/*` | Lista lateral + painel detalhe; mobile = slideover |
| **Settings / seções** | `app/pages/settings.vue` + `settings/*` | Navbar + toolbar com tabs/nav horizontal + formulários em cards |
| **Modal form** | `components/customers/AddModal.vue` | Criar/editar curto com `UModal` + `UForm` + Zod |
| **Lista em card** | `settings/members.vue` + `MembersList` | Search no header do card + lista |

Detalhes e slots: [references/archetypes.md](./references/archetypes.md).

## Anatomia canônica de uma página autenticada

```vue
<UDashboardPanel id="…">
  <template #header>
    <UDashboardNavbar title="…">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #right>
        <!-- ação primária OU notificações + plus -->
      </template>
    </UDashboardNavbar>
    <!-- opcional: UDashboardToolbar com filtros/tabs -->
  </template>
  <template #body>
    <!-- conteúdo do arquétipo -->
  </template>
</UDashboardPanel>
```

### Tabela admin (classes a preservar)

Copiar o `:ui` de `customers.vue`:

```ts
{
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
}
```

## Matriz produto (atalho)

| Rota produto | Arquétipo | Fonte template |
|--------------|-----------|----------------|
| Shell `layouts/default.vue` | Shell | `layouts/default.vue` |
| `/` | Home | `pages/index.vue` |
| `/clients` | Lista | `pages/customers.vue` |
| `/clients/[id]` | Settings (seções) | `pages/settings.vue` + subpáginas |
| `/notes` (+ detalhe) | Mestre–detalhe | `pages/inbox.vue` |
| `/exports`, `/syncs`, `/admin` | Lista (ou home se só KPIs) | `customers.vue` / `index.vue` |
| Modais create | Modal form | `customers/AddModal.vue` |
| Header escritório | Header sidebar | **não** `TeamsMenu` — `OfficeIdentity` (só leitura / office da sessão) |

Matriz completa: [references/product-matrix.md](./references/product-matrix.md).

## Pontos dinâmicos (o que sempre se troca)

1. **Navegação** — `links` / `mainDestinations` / atalhos `defineShortcuts` em `useDashboard`
2. **Títulos e labels** — navbar, colunas, empty, toasts (pt-BR)
3. **Dados** — `useFetch` mock → `useApi()` + tipos em `frontend/app/types`
4. **Estados** — `loading`, vazio, erro 403/422, sucesso toast
5. **Auth/perfil** — esconder ações sem `canManage*` / `hasConfirmedAdminAccess`
6. **Tenancy** — office vem da sessão; nunca confiar em office_id do client
7. **Command palette groups** — destinos + ações reais (sem “View page source” do template)

## Shell: o que copiar vs o que já foi adaptado

**Manter forma do template:**

- `UDashboardGroup unit="rem"`
- `UDashboardSidebar` collapsible + resizable + `class="bg-elevated/25"`
- `UDashboardSearchButton` + `UDashboardSearch`
- Dois `UNavigationMenu` (principal + `mt-auto` secundário)
- Footer `UserMenu`
- `NotificationsSlideover` global

**Já domínio (não reverter para demo):**

- `OfficeIdentity` no lugar de `TeamsMenu`
- Links filtrados por permissão
- Sem toast de cookie marketing
- Sem grupo “Code / View page source”

## Ordem de trabalho sugerida

1. Shell (se mudou nav)
2. Página alvo: copiar arquétipo
3. Wire API + permissões
4. Empty/loading/error
5. Mobile (breakpoints `lg` como no inbox)
6. Checklist [references/checklist.md](./references/checklist.md)

## Resposta ao usuário

Ao implementar, declarar em 1–2 linhas:

- Arquétipo escolhido
- Arquivo(s) copiados de `.reference/nuxt-dashboard-template/...`
- O que foi adaptado (nav / API / permissões)

Se não houver arquétipo claro, **perguntar** antes de inventar layout.
