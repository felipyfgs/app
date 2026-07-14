# Stack frontend AI (skills + MCPs) — este monorepo

Tudo o que um agent precisa para UI em `frontend/` fica **encadeado**. Não usar um pedaço isolado.

## Camadas (ordem de autoridade)

```text
1. Domínio / OpenSpec / AGENTS.md     → o que o produto permite
2. /nuxt-dashboard-template          → FORMA da tela (copiar arquétipo)
3. MCP nuxt-ui + skill nuxt-ui       → API real dos componentes U*
4. MCP nuxt + skill nuxt             → Nuxt 4 (app/, rotas, middleware, SPA)
5. Código em frontend/               → API Laravel, permissões, tenancy
```

Se 2 e 3 conflitarem (ex.: prop nova no MCP vs markup do template), **ganha o template** na estrutura/slots/classes; use o MCP só para preencher props/eventos sem mudar a composição.

## Skills

| Skill | Escopo | Papel neste repo |
|-------|--------|------------------|
| **`nuxt-dashboard-template`** | **Projeto** (`.grok/skills/…`) | Base visual/interacional. Obrigatória em toda página autenticada. |
| **`nuxt-ui`** | Global (`~/.agents/skills/nuxt-ui`) | Padrões de theming, forms, overlays, 125+ componentes. |
| **`nuxt`** | Global (`~/.agents/skills/nuxt`) | Framework Nuxt 4: `app/`, auto-imports, middleware, `nuxt.config`. |

Invocação explícita quando útil:

- `/nuxt-dashboard-template` — implementar ou realinhar UI
- `/nuxt-ui` — dúvida de componente/theming fora do que o template já mostra
- `/nuxt` — routing, middleware, SPA, config, data fetching Nuxt

Skill orquestradora do projeto: **`/frontend-nuxt-stack`**.

## MCPs (já configurados nos agents)

| MCP | URL | Use para |
|-----|-----|----------|
| **`nuxt-ui`** | `https://ui.nuxt.com/mcp` | Props/slots/examples de `UButton`, `UTable`, `UDashboard*`, ícones, templates oficiais |
| **`nuxt`** | `https://nuxt.com/mcp` | Docs Nuxt 4, getting started, migration, deploy (referência; prod é SPA + Nginx) |

### Ferramentas MCP Nuxt UI (prioridade no painel)

1. `search_components` — achar o `U*` certo
2. `get_component` / `get_component_metadata` — props e slots (use `sections` para enxugar)
3. `search_icons` — nomes `i-lucide-*` alinhados ao template
4. `get_example` / `list_examples` — trechos oficiais
5. `list_templates` / `get_template` — lembrar que **nossa** base fixa é o clone local, não um template novo

### Ferramentas MCP Nuxt

1. `get_documentation_page` / `list_documentation_pages` — API de framework
2. `get_getting_started_guide` — setup (já feito no monorepo)
3. `get_migration_guide` — só se houver upgrade de major

## Protocolo combinado (implementar 1 tela)

```text
A. Ler matriz em product-matrix.md → arquétipo + arquivo em .reference/
B. Copiar markup do template (skill nuxt-dashboard-template)
C. Se prop/slot/evento de U* for incerto → MCP nuxt-ui (get_component)
D. Se middleware, useFetch vs $fetch, pages/app structure → skill/MCP nuxt
E. Ligar useApi + permissões + office da sessão (AGENTS.md)
F. Checklist references/checklist.md
```

## Anti-padrões do stack

| Errado | Certo |
|--------|-------|
| Só Nuxt UI MCP e inventar layout | Template primeiro |
| `npx nuxi` novo dashboard | Usar `.reference/nuxt-dashboard-template` @ `0f30c09` |
| SSR/Node em prod “porque Nuxt default” | SPA estática + API Laravel (design do monorepo) |
| Ícones inventados | `search_icons` ou os do template (`i-lucide-*`) |
| Ignorar skill local e copiar de memória | Abrir o arquivo em `.reference/` |

## Onde cada coisa vive

```text
Projeto:
  .grok/skills/nuxt-dashboard-template/   # esta skill
  .grok/skills/frontend-nuxt-stack/       # orquestrador
  .reference/nuxt-dashboard-template/     # código base UI
  frontend/                               # app real

User (global):
  ~/.agents/skills/nuxt/
  ~/.agents/skills/nuxt-ui/
  ~/.grok/config.toml  → mcp_servers.nuxt + nuxt-ui
  ~/.codex/config.toml → idem
  ~/.config/opencode/opencode.jsonc → mcp.nuxt + nuxt-ui
```
