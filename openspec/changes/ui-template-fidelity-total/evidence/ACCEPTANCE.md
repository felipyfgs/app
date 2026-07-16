# Evidência parcial — ui-template-fidelity-total

Data: 2026-07-16  
Template: `.reference/nuxt-dashboard-template` @ `0f30c09d697160ef5dd0aaaec27fae8d7195d930`  
Estado global: **PENDING**

## Decisão vigente

O produto determinou migração integral ao template e revogou as exceções de UI/UX das changes anteriores. `ui-template-fidelity-total` substitui `padronizar-tabelas-carregamento-incremental`; ver `SUPERSESSION.md`.

“Integral” significa:

- bundle canônico único por superfície;
- chrome, DOM, slots, ordem, classes críticas, breakpoints e interação copiados diretamente da fonte;
- nenhuma casca de chrome, híbrido, infinite scroll, sentinel, sticky/virtualização custom ou footer ausente;
- `LIST` com footer, contagem e `UPagination` de `customers.vue`, alimentados pelo servidor;
- `MASTER_DETAIL` com segundo painel desktop e slideover mobile de `inbox.vue`;
- adaptações somente de textos, rotas, dados/API, permissões, tenancy, estados e segurança.

## Estado real

| Critério | Estado | Evidência/pendência |
|----------|--------|--------------------|
| Inventário nominal | `PASS BASELINE` | 51 páginas registradas |
| Fontes canônicas lidas | `PASS PLANEJAMENTO` | `TEMPLATE-SOURCES.md` |
| Supersessão registrada | `PASS PLANEJAMENTO` | `SUPERSESSION.md` |
| Gate lexical antigo | `HISTÓRICO` | confere nomes, mas aceita wrappers agora proibidos |
| Manifesto semântico | `PENDING` | ainda precisa substituir a matriz Markdown |
| Gate AST/DOM | `PENDING` | ainda precisa validar bundles diretos e ordem renderizada |
| Purga de wrappers | `PENDING` | runtime ainda possui cascas/renomes equivalentes |
| Paginação Customers | `PARCIAL` | paginação foi reintroduzida, mas footer permanece encapsulado em wrapper em partes do app |
| Documentos Inbox | `PENDING` | workspace tabela+modal ainda precisa ser removido |
| 51 rotas/casos | `PENDING` | nenhuma linha possui todo o conjunto de evidências sob a regra nova |
| A11y/teclado | `PENDING` | falta execução integral autenticada |
| Visual 1440/390 e overflow 360 | `PENDING` | baselines antigos não comprovam a nova composição |
| Segurança de artefatos | `PENDING` | scan integral obrigatório |
| CI completo | `PENDING` | falta tornar todos os gates bloqueantes |

Estado quantitativo desta revisão: **0/51 páginas com aceite integral sob a regra nova**.

## Implementação parcial existente

`PURGE-2026-07-16.md` registra uma direção parcialmente correta:

- removeu infinite/virtualização de várias listas;
- reintroduziu paginação server-side e o footer visual;
- removeu classes de viewport em diversas páginas.

Ela ainda não passa porque mantém wrappers de chrome, atualmente encontrados com nomes como `ShellListShell`, `ShellTableFooter`, `MonitoringModuleTable` e `DocsWorkspace` (ou equivalentes anteriores), além do detalhe de Documentos em modal.

Renomear um wrapper não satisfaz a migração. O markup precisa estar diretamente na página ou no pai Nuxt canônico.

## Baseline lexical histórico

Comando já executado:

```bash
pnpm --dir frontend test:fidelity -- --json
# pages=51, matrixEntries=51, issues=[]
```

Esse resultado prova somente inventário nominal. Ele não valida bundle único, ausência de wrapper, DOM renderizado, visual, interação, papéis, tenancy, a11y ou segurança.

## Gates necessários para `FINAL: PASS`

1. Retirar a change incremental do conjunto ativo sem sync.
2. Atualizar skills/checklists versionados para a decisão atual.
3. Remover wrappers e chrome paralelo descritos em `VILLAINS.md` e no manifesto.
4. Migrar cada página para um único bundle canônico.
5. Implementar paginação read-only server-side compatível com `UPagination` onde necessário.
6. Reescrever o gate como manifesto + AST/DOM, sem evidência por texto/import.
7. Vincular cada página a funcional, estados, papéis/tenancy, a11y, visual 1440/390, overflow 360 e segurança.
8. Executar lint, typecheck, Vitest/component, generate, Playwright, a11y e scan no CI.
9. Declarar `FINAL: PASS` somente quando todas as páginas restantes estiverem verdes e cada `N/A` tiver justificativa verificável.

## Comandos de fechamento

```bash
openspec validate ui-template-fidelity-total --json
pnpm --dir frontend lint
pnpm --dir frontend typecheck
pnpm --dir frontend test
pnpm --dir frontend generate
pnpm --dir frontend test:fidelity
pnpm --dir frontend exec playwright test
pnpm --dir frontend test:artifacts
```

Este documento não autoriza archive enquanto permanecer como “Evidência parcial” ou enquanto qualquer linha estiver `PENDING`/`FAIL`.
