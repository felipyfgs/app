---
name: panel-ui
description: >
  Orquestra UI do painel (frontend/): domínio + ui-archetype + MCP/skills nuxt e nuxt-ui.
  Use ao implementar ou refatorar telas Nuxt/Nuxt UI, alinhar ao template dashboard, ou
  quando o usuário pedir /panel-ui, stack do painel, UI completa (aliases: frontend-nuxt-stack).
---

# panel-ui (orquestrador)

Ponto de entrada para **qualquer** trabalho de UI em `frontend/`. Encadeia as peças; não substitui o domínio (OpenSpec / `AGENTS.md`).

## Peças

| Peça | Tipo | Onde |
|------|------|------|
| **ui-archetype** | Skill **projeto** | `.opencode/skills/ui-archetype/` |
| **nuxt-ui** | Skill global + MCP | `~/.agents/skills/nuxt-ui` · MCP `https://ui.nuxt.com/mcp` |
| **nuxt** | Skill global + MCP | `~/.agents/skills/nuxt` · MCP `https://nuxt.com/mcp` |
| Template código | Referência fixa | `.reference/nuxt-dashboard-template` @ `0f30c09` |

Detalhe: [../ui-archetype/references/stack.md](../ui-archetype/references/stack.md).

## Protocolo (nesta ordem)

### 1. Domínio (sempre)

- Ler `AGENTS.md` (tenancy, papéis, segredos, SPA + Sanctum).
- Se change OpenSpec ativo de UI: tasks/design — não inventar escopo.

### 2. Forma visual → **ui-archetype**

Carregar skill `ui-archetype`:

1. Classificar arquétipo (shell / home / lista / mestre–detalhe / settings / modal).
2. **Copiar** o arquivo em `.reference/nuxt-dashboard-template/...`.
3. Adaptar só nomes, nav, API, permissões, estados.

Sem esse passo, **não** implementar UI “do zero” com Nuxt UI.

### 3. Componentes → **MCP + skill nuxt-ui**

Quando o template não documentar prop/slot/evento:

- MCP `nuxt-ui`: `search_components` → `get_component` / `get_component_metadata`
- Ícones: `search_icons` (`lucide` → `i-lucide-*`)
- Skill `nuxt-ui` para theming/forms/overlays

**Não** redesenhar a página a partir do MCP; só completar a API do `U*`.

### 4. Framework → **MCP + skill nuxt**

- `app/` srcDir, pages, layouts, middleware, `nuxt.config`, data fetching
- **Produção = SPA estática + Nginx + Laravel** (não SSR Node)

### 5. Fechar

- Checklist: `ui-archetype/references/checklist.md`
- Declarar: arquétipo, arquivos copiados, MCPs consultados

## Atalhos

| Pedido | Ação |
|--------|------|
| “Tela X igual ao template” | Só `ui-archetype` (+ API) |
| “Prop do UTable?” | MCP nuxt-ui, mantendo `:ui` do template |
| “Middleware auth?” | skill/MCP nuxt + `frontend/app/middleware` |
| “Refatorar dashboard” | stack 1→5 por rota (product-matrix) |
| “Novo projeto Nuxt” | **Não** — estender `frontend/` |

## Proibido

- Ignorar `.reference/nuxt-dashboard-template` e compor Nuxt UI de memória
- Scaffold Nuxt novo / trocar o template fixado
- Justificar SSR em produção via MCP Nuxt
- Expor PFX/senha/PEM; seletor de escritório livre

## Resposta mínima

```text
Stack: template=<arquétipo/arquivo> · nuxt-ui=<sim/não> · nuxt=<sim/não>
Adaptado: <nav|API|permissões|estados>
```
