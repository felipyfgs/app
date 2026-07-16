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
| **`ui-archetype`** | Projeto (`.opencode/skills/`) | Forma visual. Obrigatória em UI autenticada. |
| **`panel-ui`** | Projeto (`.opencode/skills/`) | Orquestra domínio + arquétipo + nuxt/nuxt-ui. |
| **`nuxt-ui`** | Global (`~/.agents/skills/nuxt-ui`) | Theming, forms, overlays. |
| **`nuxt`** | Global (`~/.agents/skills/nuxt`) | Framework Nuxt 4. |

## Onde vive

```text
Fonte real (editar aqui):
  .opencode/skills/

Discovery (symlinks → .opencode/skills):
  .agents/skills/   # Codex + OpenCode
  .grok/skills/     # Grok

User (global):
  ~/.agents/skills/nuxt/
  ~/.agents/skills/nuxt-ui/
  ~/.agents/skills/git-commit/
```
