# Relatório final de fidelidade do frontend

## Resultado

O frontend Nuxt foi revisado contra a referência congelada `0f30c09d697160ef5dd0aaaec27fae8d7195d930`, corrigido e validado nos arquétipos Dashboard, Customers, Settings e Inbox. A matriz completa de origem, destino e exceções está em `reference-matrix.md`.

Correções funcionais encontradas durante a revisão:

- a rota canônica `/notes/:accessKey` não renderizava porque o auto-import do Nuxt era chamado com prefixo duplicado;
- ações de Estabelecimentos e Certificado A1 estavam em um slot inexistente de `UPageCard` e nunca apareciam;
- classes literais de `UTable` estavam ocultas em um preset compartilhado;
- falhas de Clientes, Exportações, Sincronizações e Notas eram apresentadas apenas por toast e podiam se confundir com estado vazio;
- a locale interna do Nuxt UI não estava fixada em `pt-BR`;
- o teste da rota de nota móvel procurava o painel mestre ocultado corretamente pelo slideover, em vez do diálogo ativo.

## Evidências executadas

```bash
docker compose exec -T frontend-dev pnpm lint
docker compose exec -T frontend-dev pnpm typecheck
docker compose exec -T frontend-dev pnpm test
docker compose exec -T frontend-dev pnpm build
docker compose exec -T frontend-dev pnpm test:e2e --workers=2
docker compose exec -T frontend-dev pnpm test:artifacts
```

Resultados finais:

- ESLint: aprovado, sem erros ou avisos;
- Nuxt typecheck: aprovado;
- Vitest: 6 arquivos e 23 testes aprovados;
- build Nuxt estático: aprovado, 12 rotas prerenderizadas;
- Playwright: 129 testes aprovados e 60 skips condicionais esperados entre projetos;
- regressão visual: 43 snapshots sintéticos em `1440×900` e `390×844`, incluindo overlays e amostra escura;
- largura mínima: sete rotas autenticadas e login sem overflow em `360×800`;
- artefatos e bundle: varredura aprovada sem PFX, senha, chave privada, PEM, XML, cookie, token, `vault_object_id` ou resposta ADN.

Os avisos do build são externos ao código da aplicação: sourcemap do polyfill do Nuxt e comentários `#__PURE__` do VueUse removidos pelo Rollup.

## Rastreabilidade e exceções

- `reference-matrix.md` registra árvore, versões, matriz origem→destino, diferenças permitidas e exceções técnicas.
- `tests/e2e/diagnostic.spec.ts` grava páginas integrais somente em `test-results`, sem promovê-las a baseline.
- `tests/e2e/visual.spec.ts` mantém baselines por zona, evitando mascarar geometria com screenshot integral.
- `.reference/nuxt-dashboard-template` permaneceu limpa, ignorada pelo Git principal e fora do runtime/build.

## Reconciliação com mudanças relacionadas

- `refactor-frontend-dashboard-ux`: continua com 57/57 tarefas; esta revisão reforçou as evidências e removeu o preset compartilhado de tabela para permitir auditoria literal.
- `build-nfse-adn-capture-system`: as tarefas 9.2–9.9 permanecem coerentes com o frontend validado. A tarefa 9.10 não foi marcada porque exige explicitamente o MCP oficial do Playwright, indisponível nesta execução; os testes Playwright locais não substituem essa evidência nominal.
- As tarefas 10.5–10.7 de carga, smoke mTLS restrito e piloto progressivo permanecem fora do escopo e não foram alteradas.
