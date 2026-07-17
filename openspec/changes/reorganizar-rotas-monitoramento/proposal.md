## Why

O painel representa submódulos fiscais por query string, produzindo URLs pouco canônicas como `/monitoring/simples-mei?submodule=PGDASD`. A identidade da página deve estar no caminho, mantendo query string apenas para filtros de carteira.

## What Changes

- Adotar rotas canônicas por caminho para módulos e submódulos do monitoramento fiscal.
- Manter filtros efêmeros no estado local das páginas, sem query string no navegador.
- Redirecionar URLs legadas com `submodule`/`tab` para a rota canônica equivalente, preservando filtros independentes.
- Manter a barra horizontal rolável com seus rótulos e ordem anteriores e a sidebar com todos os módulos diretamente acessíveis; resumir somente os rótulos do submenu `Monitoramento`, omitir ícones na faixa horizontal e nos submenus e preservá-los nos grupos principais.
- Remover ações globais dos cabeçalhos dos módulos; associar, consultar e exportar ficam disponíveis somente após seleção de linhas.
- Separar áreas operacionais e de gestão na sidebar por grupos nativos do `UNavigationMenu`.
- Manter APIs, permissões por Office e comportamento fiscal existentes.
- Non-goals: alterar APIs/backend, executar live smoke SERPRO, habilitar flags, realizar mutações fiscais, tratar tickets externos ou questões jurídicas/LGPD.

## Capabilities

### New Capabilities

- `navegacao-monitoramento-fiscal`: rotas canônicas e compatibilidade de deep-links do painel de monitoramento.

### Modified Capabilities

Nenhuma.

## Impact

- `frontend/app/pages/monitoring/**`: redirecionamentos e leitura dos parâmetros de rota.
- `frontend/app/utils/navigation.ts`: links canônicos na navegação existente.
- `frontend/tests/unit/**`: contratos de rotas e migração de URLs legadas.
- Sem alteração de endpoints Laravel, payloads fiscais, dependências ou isolamento multi-tenant; o Office continua vindo exclusivamente da sessão.
