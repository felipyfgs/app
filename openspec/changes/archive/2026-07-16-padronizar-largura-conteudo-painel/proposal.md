## Why

As páginas autenticadas usam limites de largura diferentes e, nas superfícies de configuração e detalhe, concentram o conteúdo em colunas estreitas que desperdiçam espaço no desktop e deixam ações visualmente desconectadas. O painel precisa de uma regra responsiva única por arquétipo, mantendo leitura confortável sem reduzir listas, tabelas ou fluxos mestre–detalhe.

## What Changes

- Padronizar a largura do conteúdo autenticado conforme o arquétipo de tela: configuração/detalhe, lista/home e mestre–detalhe.
- Ampliar configurações e detalhes para um container central confortável no desktop, com largura fluida em telas menores.
- Preservar listas, tabelas, dashboards e workspaces na largura disponível quando a densidade da informação exigir.
- Centralizar a decisão de layout em uma primitiva reutilizável, evitando novos limites `max-w-*` divergentes nas páginas.
- Cobrir o padrão com testes de superfície e executar os gates do frontend.
- Non-goals: alterar regras de negócio, APIs, tenancy, papéis, feature flags, dados fiscais, credenciais, canais SEFAZ/SERPRO ou fluxos de autenticação.

## Capabilities

### New Capabilities

- `layout-responsivo-painel`: Regras de largura e espaçamento responsivo para os arquétipos das páginas autenticadas do painel.

### Modified Capabilities

Nenhuma.

## Impact

- Frontend Nuxt em `frontend/app/`, principalmente shells de settings e páginas de detalhe/configuração.
- Testes unitários de estrutura das superfícies autenticadas e gates `lint`, `typecheck` e `generate`.
- Skills locais `panel-ui` e `ui-archetype` nas engines `.grok`, `.codex` e `.opencode` (não versionadas), para preservar a regra em trabalhos futuros.
- Sem alteração de contratos HTTP, banco, dependências ou runtime de produção.
