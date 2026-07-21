## 1. N0 — Enrichment Declarações (API)

- [x] 1.1 Criar serviço de enriquecimento PGDAS para o hub de declarações (cruzar projeções PGDAS_D com `pgdasd_operations` DECLARATION + artefatos)
- [x] 1.2 Integrar enrichment em `DeclarationHubController::index` / `toPublicArray` enriquecido (situação efetiva, `declaration_number`, `document` opcional)
- [x] 1.3 Teste Feature/Unit: declaração consultada → `UP_TO_DATE` + número; sem op → permanece PENDING

## 2. N0 — Read-model Guias DAS (API)

- [x] 2.1 Criar mapeamento DAS → shape público de guia (`payment_located` → `payment_status`, `issued_at` → emissão)
- [x] 2.2 Estender listagem `GET /fiscal/guides?client_id=` para unir `tax_guides` + DAS virtuais com dedupe por número
- [x] 2.3 Teste: cliente com DAS e zero tax_guides → lista não vazia; dedupe quando guia emitida existe

## 3. N1 — UI detalhe do cliente

- [x] 3.1 Ajustar colunas Declarações/Guias em `[clientId].vue` para nº declaração e DAS/competência
  Depende de: 1.2, 2.2

## 4. N2 — Gates

- [x] 4.1 `php artisan test` nos filtros novos + `vendor/bin/pint --test` nos arquivos tocados
  Depende de: 1.3, 2.3, 3.1
- [x] 4.2 `openspec validate --change wire-client-guias-declaracoes-pgdasd --strict` (ou equivalente do repo)
  Depende de: 1.3, 2.3, 3.1
