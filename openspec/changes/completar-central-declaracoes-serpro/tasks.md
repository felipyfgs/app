## 1. N0 — Contrato de cobertura oficial

- [x] 1.1 Implementar `DeclarationIntegrationCoverageService` para projetar PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb, MIT, FGTS Digital e DIRF a partir do snapshot versionado, com whitelist sanitizada; adicionar teste unitário de estados, contadores e ausência de coordenadas/segredos.
- [x] 1.2 Ampliar `GET /api/v1/fiscal/declarations/catalog` com `integration_coverage` tipado e preservar `obligations`/`calendar`; adicionar teste Feature autenticado e tenant-safe.

## 2. N0 — Portfolio por obrigação

- [x] 2.1 Aceitar `DASN_SIMEI` e `MIT` em `FiscalModuleKey::Declarations` e mapear ambos para os códigos canônicos em overview/lista/detalhe; ampliar `ModulePortfolioDeclarationsSubmoduleTest` para provar isolamento entre obrigações.

## 3. N1 — Contrato Nuxt e seletor completo

- [x] 3.1 Tipar o catálogo/matriz de cobertura no cliente Nuxt e ampliar helpers/tabs para PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb, MIT, FGTS Digital e DIRF.
  Depende de: 1.2, 2.1
- [x] 3.2 Manter a matriz de cobertura tipada no contrato, sem card descritivo permanente na página; apresentar erros e fontes externas somente nos estados localizados necessários.
  Depende de: 1.1, 3.1

## 4. N2 — Fluxos completos da central

- [x] 4.1 Refatorar `/monitoring/declarations` para carregar o catálogo em paralelo ao portfolio e renderizar as sete tabs no padrão dos filtros sem bloquear a tabela.
  Depende de: 3.2
- [x] 4.2 Ligar ações DASN-SIMEI e MIT aos modais locais existentes, abrir `MeiPublicServicesModal` diretamente em DASN e garantir ausência de consulta/transmissão implícita; atualizar testes da página e dos componentes.
  Depende de: 3.1
- [x] 4.3 Ajustar colunas, empty states, copy responsiva e testes fidelity/artifacts para o viewport de referência, preservando o shell e a URL canônica.
  Depende de: 4.1, 4.2

## 5. N1 — Matriz executável das operações oficiais

- [x] 5.1 Implementar catálogo sanitizado das 33 ações com contagem exata por obrigação/estado, `action_id` público, metadados curados e teste negativo de coordenadas/schemas; provar 23 produtivas com handler e 10 prospecções bloqueadas.
  Depende de: 1.1
- [x] 5.2 Completar metadados, aliases e handlers das 13 operações produtivas de leitura/apoio, incluindo validação por ação e teste request/worker tenant-safe.
  Depende de: 5.1
- [x] 5.3 Implementar codecs server-side das 10 operações produtivas mutantes, com limites de JSON, campos obrigatórios, rejeição de chaves técnicas e testes unitários baseados nos contratos oficiais.
  Depende de: 5.1
- [x] 5.4 Refatorar request factory/transport/autorização tipada para transportar apenas dados validados após policy persistida, mantendo flags/cohorts OFF e cobrindo idempotência, timeout incerto e reconciliação sem reenvio.
  Depende de: 5.3
- [x] 5.5 Expor fachada tenant-safe por `action_id` para preflight, execução, estado e reconciliação, com testes Feature de papéis, cross-tenant, prospecção e ausência de egress em bloqueio.
  Depende de: 5.2, 5.4

## 6. N2 — Central de operações na UI

- [x] 6.1 Tipar ações, disponibilidade, parâmetros e estados; criar composable reutilizável para catálogo, execução, polling e mensagens de erro.
  Depende de: 5.5
- [x] 6.2 Criar central modal acionada por botão compacto junto às tabs, com filtros, status, acessibilidade, loading/erro/vazio e formulário guiado/importação JSON validada, sem cards descritivos na página.
  Depende de: 6.1
- [x] 6.3 Integrar consulta manual, preflight/confirmação e acompanhamento/reconciliação à página, sem chamadas implícitas e preservando busca/filtros/paginação da carteira.
  Depende de: 6.2
- [x] 6.4 Adicionar testes de componentes, composable, utilitários e contrato da página para todas as obrigações e estados.
  Depende de: 6.3

## 7. N3 — Gates integrados

- [x] 7.1 Rodar gates API da área (`composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, testes focados e `php artisan test`) e corrigir regressões.
  Depende de: 5.5, 6.4
- [x] 7.2 Rodar gates web (`pnpm run lint`, `typecheck`, `generate`, `test`, `test:fidelity`, `test:artifacts`) e corrigir regressões.
  Depende de: 6.4
  Bloqueios externos à change em 2026-07-21: lint em `dashboard-theme-selector.test.ts`; fixture `template-parity-matrix.md` e scanner `tests/security/scan-artifacts.mjs` ausentes. Typecheck, generate, lint do escopo, 268 testes unitários e Playwright da central passaram.
- [x] 7.3 Validar visualmente em 1366×639 e mobile, comparar com as referências e registrar evidência de abas, KPIs, busca, paginação e estados.
  Depende de: 6.4
- [x] 7.4 Validar a change e main specs com OpenSpec estrito, registrar o status final e manter intactas as mudanças concorrentes do worktree.
  Depende de: 7.1, 7.2, 7.3
