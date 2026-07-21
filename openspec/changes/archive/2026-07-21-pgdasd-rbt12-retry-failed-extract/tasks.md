## 1. N0 — Retry e fan-out no serviço RBT12

- [x] 1.1 Em `PgdasdRbt12Service::reserve`, reabrir `FAILED` com razão recuperável → `PENDING` (limpar erro/attempt/extract/totais) e retornar a linha para disparo
- [x] 1.2 Em `reserveFromOperations`, limitar disparo automático ao DAS mais recente do PA esperado (projection esperada da run)
- [x] 1.3 Em `enqueueAutomaticRbt12Extract`, re-enqueue quando a run correlacionada estiver `FAILED`

## 2. N1 — Testes

- [x] 2.1 Teste unitário: `FAILED`/`EXTRACT_QUERY_FAILED` é reaberto e job despachado; `PARSED` não
  Depende de: 1.1, 1.2
- [x] 2.2 Teste: fan-out usa só o DAS mais recente do PA esperado
  Depende de: 1.2
- [x] 2.3 Teste: run de extrato `FAILED` correlacionada é recolocada em fila
  Depende de: 1.3

## 3. N2 — Gates

- [x] 3.1 Rodar `vendor/bin/pint --test` e `php artisan test --filter=PgdasdRbt12` (ou filtro dos novos testes) no container PHP
  Depende de: 2.1, 2.2, 2.3
- [x] 3.2 `openspec validate --changes --strict` para esta change
  Depende de: 2.1
