## 1. Catálogo de navegação e rotas

- [x] 1.1 Atualizar o catálogo tipado com os caminhos canônicos, cobrindo-o em `tests/unit/monitoring-nav.test.ts`.
- [x] 1.2 Implementar conversão código ↔ slug e migração de queries legadas, removendo `office_id`, com testes unitários dedicados.

## 2. Navegação existente

- [x] 2.1 Preservar todos os módulos, os rótulos e a ordem anterior da barra rolável, omitir ícones na faixa horizontal e nos submenus, preservar ícones nos grupos principais e atualizar os contratos nos testes.
- [x] 2.2 Resumir somente os rótulos do submenu `Monitoramento`, sem alterar as tabs horizontais; posicionar submódulos antes dos filtros e remover ações globais dos cabeçalhos, preservando ações em massa condicionadas à seleção.
- [x] 2.3 Separar destinos operacionais e de gestão na sidebar com grupos nativos, sem afetar a command palette.

## 3. Rotas canônicas de submódulo

- [x] 3.1 Mover Simples/MEI e DCTFWeb/MIT para páginas `[submodule].vue`, sincronizando tabs e filtros sem `submodule`/`tab` na query.
- [x] 3.2 Criar redirecionamentos para as rotas antigas e atualizar links internos/catálogos para os caminhos canônicos.
- [x] 3.3 Remover filtros efêmeros das URLs de todas as carteiras, Guias e Caixa Postal, mantendo-os somente no estado local e nas requisições da API.

## 4. Verificação

- [x] 4.1 Executar os unit tests de navegação/portfolio e corrigir regressões dentro do escopo.
- [ ] 4.2 Executar `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate` e `pnpm run test:fidelity` no frontend.
- [x] 4.3 Executar `openspec validate reorganizar-rotas-monitoramento --strict` e registrar somente evidência realmente aprovada.

## 5. Encerramento

- [ ] 5.1 Após aceite da implementação, sincronizar/arquivar a change e commitar no mesmo dia os specs principais, o archive e o código.
