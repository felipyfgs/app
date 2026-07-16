## 1. Primitiva de layout

- [x] 1.1 Criar `DashboardContent` com variantes literais `comfortable`, `wide` e `full`, mantendo `w-full`, `min-w-0` e o espaçamento responsivo definido por cada página.
- [x] 1.2 Adicionar teste unitário estrutural para as variantes e para a ausência dos limites antigos nos shells migrados.

## 2. Migração das superfícies autenticadas

- [x] 2.1 Migrar settings e consoles administrativos para a variante `comfortable`, preservando navbar, toolbar, permissões e `NuxtPage`.
- [x] 2.2 Migrar detalhes de processo para `comfortable` e detalhes densos de cliente/monitoramento para `wide`, sem alterar seus fluxos.
- [x] 2.3 Confirmar por auditoria que listas, home, calendário, tabelas, workspaces, inputs e modais mantêm suas larguras específicas.
- [x] 2.4 Sincronizar e validar as regras de largura nas skills locais `panel-ui` e `ui-archetype` de `.grok`, `.codex` e `.opencode`.

## 3. Verificação

- [x] 3.1 Executar os testes unitários de superfície/layout do frontend. (31 testes passaram em 2026-07-16.)
- [x] 3.2 Executar `pnpm run lint`, `pnpm run typecheck` e `pnpm run generate`. (Todos passaram em 2026-07-16.)
- [x] 3.3 Validar a change com `openspec validate padronizar-largura-conteudo-painel --type change --strict --json`. (1/1 válida em 2026-07-16.)

## 4. Encerramento

- [x] 4.1 Após aceite visual, sincronizar/arquivar a change e commitar no mesmo dia os specs principais e o histórico arquivado.
