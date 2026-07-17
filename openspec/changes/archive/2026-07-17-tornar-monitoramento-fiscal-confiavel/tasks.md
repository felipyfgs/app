## 1. Coordenação e contrato backend

- [x] 1.1 Concluir os gates/encerramento da change `reorganizar-rotas-monitoramento` antes de editar as páginas compartilhadas, confirmando a baseline com `openspec status --change reorganizar-rotas-monitoramento` e `git status --short`.
- [x] 1.2 Expandir `ModuleCountersDto` para as nove situações canônicas, mantendo resposta aditiva e cobrindo chaves/defaults em `backend/tests/Feature/Fiscal/ModulePortfolio/ModulePortfolioApiTest.php`; executar `cd backend && php artisan test --filter=ModulePortfolioApiTest`.
- [x] 1.3 Refatorar a agregação do portfolio para produzir total e contadores no mesmo agrupamento, remover somente `situation` do escopo do overview e normalizar valor inesperado para `UNKNOWN`; executar `cd backend && php artisan test --filter=ModulePortfolioApiTest`.
- [x] 1.4 Cobrir carteira integralmente bloqueada, coexistência dos nove estados, soma igual ao total e filtro de situação aplicado apenas à lista em `ModulePortfolioApiTest`; executar `cd backend && php artisan test --filter=ModulePortfolioApiTest`.
- [x] 1.5 Cobrir isolamento dos agregados por `CurrentOffice` e ausência de autoridade de `office_id` do cliente HTTP em `MonitoringTenantIsolationGateTest`; executar `cd backend && php artisan test --filter=MonitoringTenantIsolationGateTest`.

## 2. Contrato e apresentação frontend

- [x] 2.1 Expandir `FiscalModuleCounters`, `FiscalKpiKey` e os mapeamentos situação↔KPI para os nove estados, atualizando `fiscal-modules-types.test.ts` e `fiscal-status.test.ts`; executar `cd frontend && pnpm run test -- tests/unit/fiscal-modules-types.test.ts tests/unit/fiscal-status.test.ts`.
- [x] 2.2 Tornar `MonitoringKpiStrip` dirigido pelo catálogo, mantendo Total, contagens positivas e o estado ativo em zero; cobrir bloqueados, desconhecidos e ausência de cápsulas zeradas em `monitoring-counter-tabs.test.ts` e executar o teste focado.
- [x] 2.3 Adicionar a `MonitoringModuleTable` o bloco compartilhado de proveniência/frescor com alerta persistente para `DEMO`/`SIMULATED`, metadado `LIVE` e fallbacks fail-closed; cobrir em `fiscal-portfolio-ui.test.ts` e executar o teste focado.
- [x] 2.4 Propagar `data_origin`, rótulo, fonte e `as_of` dos composables para todas as carteiras que usam o portfolio, sem alterar rotas, navegação ou shell; atualizar `fiscal-module-portfolio.test.ts` e executar `cd frontend && pnpm run test -- tests/unit/fiscal-module-portfolio.test.ts`.
- [x] 2.5 Atualizar `/monitoring` para identificar a origem de cada módulo, mostrar aviso sintético global e excluir módulos sintéticos dos indicadores produtivos; cobrir em `dashboard-metrics.test.ts` e executar o teste focado.

## 3. Loading, vazio e troca de Office

- [x] 3.1 Centralizar a detecção de filtro ativo sobre defaults normalizados e passar loading real ao resolvedor do vazio; cobrir defaults `all`, primeira carga, erro e vazio filtrado em `monitoring-table-empty-contract.test.ts` e executar o teste focado.
- [x] 3.2 Limpar overview, origem, contadores, linhas e seleção na troca de `CurrentOffice`, descartando respostas atrasadas por época/abort; cobrir a corrida em `fiscal-module-portfolio.test.ts` e executar o teste focado.
- [x] 3.3 Garantir que `UNKNOWN`, `UNSUPPORTED`, `BLOCKED` e `ERROR` não usem apresentação positiva ou ação de sucesso, atualizando `fiscal-status.test.ts` e `fiscal-portfolio-ui.test.ts`; executar ambos os testes focados.

## 4. Gates de verificação

- [x] 4.1 Executar os gates da baseline compartilhada: `cd backend && vendor/bin/pint --test && php artisan test --filter=ModulePortfolioApiTest && php artisan test --filter=MonitoringTenantIsolationGateTest`; `cd frontend && pnpm run test:gate && pnpm run generate && pnpm run test:fidelity && pnpm run test:artifacts`; e validações OpenSpec estritas, sem Playwright/E2E.

## 5. Responsabilidade, payload e documento por página

- [x] 5.1 Implementar um registro backend tipado para todas as superfícies de `page-payload-matrix.md`, declarando rota, responsabilidade, canal, `operation_keys`, estado oficial, `result_kind` e política de evidência; validar cada coordenada contra `official-service-catalog.v2026-07-16.json` e cobrir que operação não produtiva/ausente falha fechada.
- [x] 5.2 Adicionar aos DTOs tenant-scoped o descritor público de documento gerado no servidor a partir de `FiscalEvidenceArtifact`; endurecer o download para `CurrentOffice`, nome/MIME sanitizados e `no-store`; cobrir ausência de Base64, XML bruto, envelope, coordenadas SERPRO, protocolo, hash, run/vault IDs e paths nas respostas públicas.
- [x] 5.3 Aplicar o contrato útil e a ação de evidência, sem redesign de shell, individualmente em `/monitoring`, PGDAS-D, PGMEI, DASN-SIMEI, Regime, DCTFWeb, MIT, FGTS, Parcelamentos, SITFIS, Caixa Postal lista/detalhe, Declarações, Guias, Cadastros, Processos e detalhe do cliente; componentes agregadores SHALL delegar ao módulo originador.
- [x] 5.4 Criar testes backend e Vitest dirigidos pela matriz que enumerem todas as superfícies e comprovem `STRUCTURED`, `PDF`, `ASYNC_PDF`, `AGGREGATE` e `UNAVAILABLE`, botão somente com artefato real, ausência de ação para MIT/Caixa Postal/Cadastros/e-Processo e isolamento cruzado por Office.

## 6. Gates após a ampliação page-by-page

- [x] 6.1 Executar `cd backend && vendor/bin/pint --test` e testes fiscais/arquiteturais focados; `cd frontend && pnpm run test:gate && pnpm run generate && pnpm run test:fidelity && pnpm run test:artifacts`; `openspec validate tornar-monitoramento-fiscal-confiavel --type change --strict` e `openspec validate --specs --strict`, sem Playwright/E2E, live SERPRO ou mutação.

## 7. Encerramento

- [x] 7.1 Após aceite e gates aprovados, sincronizar e arquivar a change e commitar no mesmo dia o código, `openspec/specs/` atualizado e `openspec/changes/archive/`.
