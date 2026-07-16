---
name: ui-archetype
description: >
  Copia arquétipos do Nuxt UI Dashboard fixado em .reference/nuxt-dashboard-template
  (0f30c09) para o painel: shell, lista, mestre–detalhe, settings, modal. Use ao
  criar/alterar páginas Vue, tabelas, fidelidade visual, ou /ui-archetype /panel-ui
  (aliases: nuxt-dashboard-template).
---

# ui-archetype (forma da tela)

## Stack relacionado

| Camada | O quê | Quando |
|--------|--------|--------|
| **Esta skill** | Forma (arquétipo em `.reference/`) | Toda UI autenticada |
| **Skill + MCP `nuxt-ui`** | Props/slots dos `U*` | Dúvida de API de componente |
| **Skill + MCP `nuxt`** | Nuxt 4 | Framework, não layout |
| **AGENTS.md / OpenSpec** | Domínio fiscal, tenancy | Regras de negócio |

Fluxo: [references/stack.md](./references/stack.md).  
Orquestrador: skill **`panel-ui`** (`/panel-ui`).

**Conflito template vs MCP:** estrutura/slots/classes do template vencem; MCP só refina props.

### MCP — quando

- **nuxt-ui**: `search_components`, `get_component` / `get_component_metadata`, `search_icons` (lucide)
- **nuxt**: routing/middleware/SPA se o adaptador for incerto

Não inventar dashboard novo — base fixa @ `0f30c09`.

## Fonte de verdade

| Item | Valor |
|------|--------|
| Path | `.reference/nuxt-dashboard-template/` |
| Commit | `0f30c09` |
| App | `frontend/` |
| Demo | https://dashboard-template.nuxt.dev/ |

**Regra:** copiar → adaptar o mínimo → **não** reimplementar “parecido”.

Referências sob demanda:

- [references/stack.md](./references/stack.md)
- [references/archetypes.md](./references/archetypes.md)
- [references/product-matrix.md](./references/product-matrix.md)
- [references/checklist.md](./references/checklist.md)

## Fluxo obrigatório

```text
1. Classificar arquétipo (shell / home / lista / mestre-detalhe / settings / modal)
2. Abrir arquivo EXATO em .reference/nuxt-dashboard-template/
3. Copiar markup + slots + classes + ordem de blocos
4. Prop/slot/ícone U* incerto → MCP nuxt-ui
5. Dúvida Nuxt 4 → skill/MCP nuxt
6. Adaptar APENAS: labels, rotas/nav, API real, permissões, estados
7. Remover mocks, TeamsMenu, cookie toast de marketing
8. Árvore U* deve permanecer reconhecível vs origem
```

### Proibido

- Layout “do zero” com Nuxt UI “equivalente”
- Mocks `server/api/*` do template como verdade de negócio
- `TeamsMenu` como seletor de escritório
- Paginação client se a API for server-side (manter visual; trocar fonte)
- Expor PFX/senha/PEM
- Design system paralelo

### Permitido

- Renomear labels; wire `useApi()`; esconder ações por papel
- `UPagination` com total/page da API
- Empty/loading em pt-BR

## Arquétipos

| Arquétipo | Origem no template | Quando |
|-----------|-------------------|--------|
| **Shell** | `layouts/default.vue` + menus | Layout autenticado |
| **Home** | `pages/index.vue` | Cards/stats/charts |
| **Lista** | `pages/customers.vue` | Tabela + filtros + ação |
| **Mestre–detalhe** | `pages/inbox.vue` | Lista + detalhe / slideover |
| **Settings** | `pages/settings.vue` | Tabs + forms em cards |
| **Modal form** | `customers/AddModal.vue` | Create/edit curto |

Detalhes: [references/archetypes.md](./references/archetypes.md).

## Anatomia canônica

```vue
<UDashboardPanel id="…">
  <template #header>
    <UDashboardNavbar title="…">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #right><!-- ação primária --></template>
    </UDashboardNavbar>
  </template>
  <template #body><!-- arquétipo --></template>
</UDashboardPanel>
```

Tabela admin: copiar `:ui` de `customers.vue` (border-separate, thead elevated, etc.).

## Matriz (atalho)

| Rota produto | Arquétipo | Fonte |
|--------------|-----------|-------|
| Shell | Shell | `layouts/default.vue` |
| `/` | Home | `pages/index.vue` |
| `/clients` | Lista | `pages/customers.vue` |
| `/clients/[id]` | Settings | `pages/settings.vue` |
| `/notes` | Mestre–detalhe | `pages/inbox.vue` |
| Header escritório | — | `OfficeIdentity` (não TeamsMenu) |

Completa: [references/product-matrix.md](./references/product-matrix.md).

## Pontos dinâmicos

1. Nav / shortcuts em `useDashboard`
2. Títulos pt-BR
3. Dados: mock → `useApi()` + types
4. Loading / vazio / erro
5. Papéis ADMIN|OPERATOR|VIEWER
6. Tenancy: office da sessão; nunca `office_id` do client

## Resposta ao usuário

- Arquétipo + arquivo(s) copiados de `.reference/...`
- O que foi adaptado (nav / API / permissões)

Se arquétipo não for claro, **perguntar** antes de inventar layout.
