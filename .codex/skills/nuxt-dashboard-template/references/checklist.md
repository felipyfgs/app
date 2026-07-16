# Checklist por entrega de UI

Usar ao finalizar qualquer página/componente derivado do template.

## Origem

- [ ] Arquétipo identificado e registrado (shell / home / lista / mestre–detalhe / settings / modal)
- [ ] Arquivo(s) lidos em `.reference/nuxt-dashboard-template/` (commit `0f30c09`)
- [ ] Estrutura de slots (`#header` / `#body` / leading / right) preservada

## Cópia vs adaptação

- [ ] Componentes `UDashboard*` e ordem visual alinhados à origem
- [ ] Classes críticas da tabela/cards/toolbar mantidas (ou preset que expande igual)
- [ ] Só mudou: labels, rotas, API, permissões, dados, empty/error
- [ ] Zero mocks `server/api` do template em runtime
- [ ] Sem `TeamsMenu` / seletor de escritório arbitrário

## Dados e estados

- [ ] Loading visível (table/panel)
- [ ] Estado vazio com ícone/texto (como empty do inbox)
- [ ] Erro de rede/API com toast ou banner; não engolir falha
- [ ] 422 mapeado em `UFormField` quando formulário
- [ ] Lista grande usa blocos server-side com auto-load e indicador transitório; sem footer, `UPagination`, `Carregar mais` ou “Fim da lista”
- [ ] Mudança de filtro/sorting/tenant reseta o feed e ignora resposta obsoleta
- [ ] Sorting visível é global/manual no servidor; ordem fixa não simula sorting local
- [ ] Header ordenável anuncia coluna, direção atual e próxima ação no nome acessível
- [ ] Checkbox existe só com ação em massa real e `getRowId` estável
- [ ] Virtualização só em linhas previsíveis dentro de contêiner com altura controlada

## Auth e segurança

- [ ] Ações respeitam perfil (ADMIN / OPERATOR / VIEWER)
- [ ] Rotas admin inacessíveis sem confirmação
- [ ] Nenhum segredo (PFX, senha, PEM) em UI, log ou export

## Responsivo e a11y

- [ ] `UDashboardSidebarCollapse` presente no navbar
- [ ] Breakpoint `lg` para split (se mestre–detalhe)
- [ ] Mobile: slideover ou stack sem overflow horizontal grave
- [ ] Botões com label ou `aria-label` / tooltip

## Navegação

- [ ] Item na sidebar / command palette se for destino de primeiro nível
- [ ] Shortcut atualizado em `useDashboard` se aplicável
- [ ] Títulos navbar em pt-BR

## Stack (skills + MCP)

- [ ] Forma veio do template (skill `nuxt-dashboard-template`), não de layout inventado
- [ ] Props/slots duvidosos de `U*` conferidos no MCP **nuxt-ui** (se aplicável)
- [ ] Dúvidas de framework (middleware, pages, SPA) via skill/MCP **nuxt** (se aplicável)
- [ ] Não se usou MCP para substituir o arquétipo fixado em `.reference/`
- [ ] Orquestração coerente com `/frontend-nuxt-stack` se a tarefa era multi-camada

## Conferência final

- [ ] Diff legível contra o arquivo do template (mesma “forma”)
- [ ] Nenhuma regressão no shell ao editar uma página isolada
