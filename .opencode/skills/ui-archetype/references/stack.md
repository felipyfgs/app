# Stack UI (skills + MCPs) — este monorepo

## Camadas (ordem de autoridade)

```text
1. Domínio / OpenSpec / AGENTS.md     → o que o produto permite
2. /ui-archetype                     → FORMA da tela (copiar arquétipo)
3. MCP nuxt-ui + skill nuxt-ui       → API real dos componentes U*
4. MCP nuxt + skill nuxt             → Nuxt 4 (app/, rotas, middleware, SPA)
5. Código em frontend/               → API Laravel, permissões, tenancy
```

Se 2 e 3 conflitarem, **ganha o template** na estrutura/slots/classes; MCP só preenche props/eventos.

## Skills

| Skill | Escopo | Papel |
|-------|--------|-------|
| **`ui-archetype`** | Projeto (``.opencode/skills/` / discovery `.agents/skills/``) | Forma visual. Obrigatória em UI autenticada. |
| **`panel-ui`** | Projeto (``.opencode/skills/` / discovery `.agents/skills/``) | Orquestra domínio + arquétipo + nuxt/nuxt-ui. |
| **`nuxt-ui`** | Global (`~/`.opencode/skills/` / discovery `.agents/skills/`nuxt-ui`) | Theming, forms, overlays, 125+ componentes. |
| **`nuxt`** | Global (`~/`.opencode/skills/` / discovery `.agents/skills/`nuxt`) | Framework Nuxt 4. |

Invocação: `/ui-archetype`, `/panel-ui`, `/nuxt-ui`, `/nuxt`.

## MCPs

| MCP | URL | Uso |
|-----|-----|-----|
| **nuxt-ui** | `https://ui.nuxt.com/mcp` | Props/slots `U*`, ícones, examples |
| **nuxt** | `https://nuxt.com/mcp` | Docs Nuxt 4 (prod = SPA + Nginx) |

Prioridade MCP nuxt-ui: `search_components` → `get_component` → `search_icons` → examples.

## Protocolo (1 tela)

```text
A. product-matrix.md → arquétipo + arquivo em .reference/
B. Copiar markup (skill ui-archetype)
C. Prop U* incerta → MCP nuxt-ui
D. Middleware/pages/SPA → skill/MCP nuxt
E. useApi + permissões + office da sessão (AGENTS.md)
F. checklist.md
```

## Anti-padrões

| Errado | Certo |
|--------|-------|
| Só Nuxt UI MCP e inventar layout | Template primeiro |
| `nuxi` novo dashboard | `.reference/nuxt-dashboard-template` @ `0f30c09` |
| SSR em prod | SPA estática + API Laravel |
| Ícones inventados | `search_icons` / `i-lucide-*` do template |

## Onde vive

```text
Projeto (canônico, multi-agent):
  `.opencode/skills/` / discovery `.agents/skills/`ui-archetype/
  `.opencode/skills/` / discovery `.agents/skills/`panel-ui/
  .reference/nuxt-dashboard-template/
  frontend/

User (global):
  ~/`.opencode/skills/` / discovery `.agents/skills/`nuxt/
  ~/`.opencode/skills/` / discovery `.agents/skills/`nuxt-ui/
  ~/`.opencode/skills/` / discovery `.agents/skills/`git-commit/

MCP:
  ~/.config/opencode/opencode.jsonc  → nuxt + nuxt-ui
  .codex/config.toml / .grok/config.toml → playwright (projeto)
```
