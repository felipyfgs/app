---
name: frontend-nuxt-stack
description: >
  Orquestra o stack de UI deste monorepo: skill local nuxt-dashboard-template + skills
  e MCPs globais nuxt e nuxt-ui. Use ao implementar ou refatorar o frontend Nuxt/Nuxt UI,
  alinhar ao template dashboard, ou quando o usuário pedir /frontend-nuxt-stack, stack
  nuxt, ou UI completa do painel.
---

# Frontend Nuxt Stack (orquestrador)

Ponto de entrada para **qualquer** trabalho de UI em `frontend/`. Encadeia as peças; não substitui o domínio (OpenSpec / AGENTS.md).

## Peças

| Peça | Tipo | Onde |
|------|------|------|
| **nuxt-dashboard-template** | Skill **projeto** | `.grok/skills/nuxt-dashboard-template/` |
| **nuxt-ui** | Skill global + MCP | `~/.agents/skills/nuxt-ui` · MCP `https://ui.nuxt.com/mcp` |
| **nuxt** | Skill global + MCP | `~/.agents/skills/nuxt` · MCP `https://nuxt.com/mcp` |
| Template código | Referência fixa | `.reference/nuxt-dashboard-template` @ `0f30c09` |

Detalhe da amarração: [../nuxt-dashboard-template/references/stack.md](../nuxt-dashboard-template/references/stack.md).

## Protocolo (seguir nesta ordem)

### 1. Domínio (sempre)

- Ler `AGENTS.md` (tenancy, papéis, segredos, SPA + Sanctum).
- Se change OpenSpec ativo de UI: `openspec` tasks/design — não inventar escopo.

### 2. Forma visual → **nuxt-dashboard-template**

Carregar e seguir `.grok/skills/nuxt-dashboard-template/SKILL.md`:

1. Classificar arquétipo (shell / home / lista / mestre–detalhe / settings / modal).
2. **Copiar** o arquivo em `.reference/nuxt-dashboard-template/...`.
3. Adaptar só nomes, nav, API, permissões, estados.

Sem esse passo, **não** implementar UI “do zero” com Nuxt UI.

### 3. Componentes → **MCP + skill nuxt-ui**

Quando o template não documentar uma prop/slot/evento:

- MCP `nuxt-ui`: `search_components` → `get_component` / `get_component_metadata`
- Ícones: `search_icons` (prefixo `lucide` → `i-lucide-*`)
- Skill `nuxt-ui` para theming/forms/overlays em geral

**Não** redesenhar a página a partir do MCP; só completar a API do `U*`.

### 4. Framework → **MCP + skill nuxt**

Quando for Nuxt (não cosmético):

- `app/` srcDir, pages, layouts, middleware, `nuxt.config`, data fetching
- MCP `nuxt`: páginas de docs oficiais
- Lembrar: **produção = SPA estática + Nginx + Laravel** (não SSR Node)

### 5. Fechar

- Checklist: `nuxt-dashboard-template/references/checklist.md`
- Declarar ao usuário: arquétipo, arquivos copiados, MCPs consultados (se houver)

## Atalhos de decisão

| Pedido do usuário | Ação principal |
|-------------------|----------------|
| “Faz a tela X igual ao template” | Só nuxt-dashboard-template (+ API) |
| “Qual prop do UTable?” | MCP nuxt-ui, mantendo `:ui` do template |
| “Como configurar middleware auth?” | skill/MCP nuxt + código em `frontend/app/middleware` |
| “Refatorar dashboard inteiro” | stack completo 1→5 por rota (matriz product-matrix) |
| “Novo projeto Nuxt” | **Não** — este monorepo já tem `frontend/`; estender, não scaffold |

## Proibido neste stack

- Ignorar `.reference/nuxt-dashboard-template` e “compor” com Nuxt UI de memória
- Trocar o template fixado por outro starter (`nuxi` template novo)
- Usar MCP Nuxt para justificar SSR em produção
- Duplicar design system fora de Nuxt UI + template
- Expor PFX/senha/PEM; seletor de escritório livre

## Resposta mínima ao concluir

```text
Stack: template=<arquétipo/arquivo> · nuxt-ui=<sim/não MCP> · nuxt=<sim/não>
Adaptado: <nav|API|permissões|estados>
```
