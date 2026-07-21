## 1. Comunicação — roteamento e documentos (N0)

- [x] 1.1 Restringir `FiscalMonitoringRunService::maybeQueueAutomaticCommunication` a PGDASD/PGMEI explícitos (sem catch-all `simples_mei`)
- [x] 1.2 Em `PgdasdCommunicationService`, centralizar detecção de artefatos locais e usar em preview/summary/`requestSend`/`resolveAutomaticEffective`/`maybeQueueAutomaticAfterConsult` (422 no send sem docs para pgdasd)
- [x] 1.3 Atualizar/adicionar testes Feature: send sem docs → 422; send com docs → ok; automático não enfileira para DEFIS; automático pgdasd sem docs no-op

## 2. Guias e RBT12 (N0)

- [x] 2.1 Refatorar `ClientGuidesQueryService::paginate` para índice leve + hidratação só da página (preservar união/dedupe/counters)
- [x] 2.2 Alinhar `ModulePortfolioQueryService::pgdasdRbt12SortSubquery` à precedência de `portfolioDetails`
- [x] 2.3 Cobrir com testes: lista office-wide DAS; ordenação RBT12 com mismatch period vs id

## 3. Gates (N1)

- [x] 3.1 Rodar testes API afetados + `vendor/bin/pint --test` na API; `openspec validate --specs --strict` e validate da change
