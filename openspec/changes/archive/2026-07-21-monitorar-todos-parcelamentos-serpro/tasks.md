## 1. N0 — Contratos oficiais e normalização

- [x] 1.1 Evoluir `ParcelamentoServiceCatalog` para expor oito modalidades produtivas e PAEX/SIPADE em prospecção, resolver somente `operation_key` executável e cobrir catálogo/bloqueios em PHPUnit.
- [x] 1.2 Implementar codec oficial para `parcelamentos`, `listaParcela`/`listaParcelas`, consolidação, demonstrativo, datas e valores; adicionar testes unitários com payloads reais representativos e variações das oito famílias.

## 2. N1 — Projeção e orquestração por modalidade

- [x] 2.1 Refatorar `ParcelamentoProjectionService` para estrutura por pedido, chaves tenant/modalidade/pedido/parcela e situações honestas; cobrir dois pedidos, pagamentos, vencimentos, ausência de pedido e isolamento por `Office`.
  Depende de: 1.2
- [x] 2.2 Corrigir `ParcelamentoReadAdapter` para chamadas oficiais limitadas (`PEDIDOSPARC`, `OBTERPARC`, uma `PARCELASPARAGERAR`) sem N+1 de `DETPAGTOPARC`; testar payloads, operation keys, falha parcial e evidência normalizada.
  Depende de: 1.1, 1.2
- [x] 2.3 Implementar serviço “monitorar todos” que enfileira runs independentes/idempotentes para as oito modalidades e retorna resultado por modalidade; cobrir bloqueio parcial e ausência de modalidades em prospecção.
  Depende de: 1.1

## 3. N2 — API, agendamento e carteira backend

- [x] 3.1 Evoluir `TaxInstallmentController`/rotas com catálogo tipado e bulk enqueue office-scoped, limites e RBAC; adicionar Feature tests para Admin/Operator, Viewer, cross-tenant e PAEX/SIPADE.
  Depende de: 2.3
- [x] 3.2 Corrigir o agendador de Parcelamentos para reutilizar as oito modalidades produtivas em vez de `INSTALLMENTS/INSTALLMENTS`, preservando idempotência, budget e fail-closed; adicionar testes do scheduler.
  Depende de: 2.3
- [x] 3.3 Corrigir `ModulePortfolioQueryService` para agregação “Todas”, filtro/enrichment pela mesma modalidade e detalhe local de pedidos/parcelas/pagamentos; adicionar Feature tests de múltiplas modalidades e tenants.
  Depende de: 2.1

## 4. N3 — Interface Nuxt/Nuxt UI

- [x] 4.1 Tipar catálogo, bulk enqueue, pedidos, parcelas e agregados no client/composables do Nuxt; adicionar testes Vitest dos mapeamentos, disponibilidade e feedback parcial.
  Depende de: 3.1, 3.3
- [x] 4.2 Completar `/monitoring/installments` com tabs das oito modalidades, PAEX/SIPADE desabilitadas, visão agregada, confirmação “Consultar todos” e slideover local navegável; preservar shell, cores semânticas, URL canônica e contrato de sort, com testes unitários/fidelity.
  Depende de: 4.1

## 5. N4 — Gates integrados

- [x] 5.1 Executar API: `composer validate --strict --no-check-publish`, `vendor/bin/pint --test` e `php artisan test`; registrar qualquer limitação externa real.
  Depende de: 3.1, 3.2, 3.3
  Evidência: Composer/Pint verdes; PHPUnit completo com 431 testes e 9.445 asserções.
- [x] 5.2 Executar Web: `pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity` e `pnpm run test:artifacts`.
  Depende de: 4.2
  Evidência: lint, typecheck e generate verdes; Vitest completo com 63 arquivos e 261 testes. Os gates fidelity/artifacts foram executados, mas permanecem indisponíveis no baseline porque o repositório não contém `tests/fixtures/template-parity-matrix.md` nem `tests/security/scan-artifacts.mjs`.
- [x] 5.3 Executar `openspec validate --change monitorar-todos-parcelamentos-serpro --strict`, `openspec validate --specs --strict` e auditar o diff para garantir ausência de segredos, egress live, serviços Compose ou perda das alterações paralelas.
  Depende de: 5.1, 5.2
  Evidência: change e 54 specs válidas em modo strict; `git diff --check` limpo; Compose dev/prod válido sem `mei`/`mei-worker`; leitura de detalhe limitada à API local; nenhuma credencial/certificado detectado nos arquivos da change.
