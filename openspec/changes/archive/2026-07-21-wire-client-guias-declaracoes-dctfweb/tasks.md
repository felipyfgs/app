## 1. N0 — Enrichment Declarações DCTFWeb

- [x] 1.1 Criar `DeclarationDctfwebEnrichmentService` (enriquecer projeções + sintéticos sem projeção)
- [x] 1.2 Encadear no `DeclarationHubController::index` após enrichment PGDAS
- [x] 1.3 Teste: recibo → UP_TO_DATE + número; sintético sem projeção aparece

## 2. N0 — Guias DARF

- [x] 2.1 Estender `ClientGuidesQueryService` com DARFs virtuais + dedupe
- [x] 2.2 Teste: DARF aparece; ausência de DARF após só recibo não inventa guia

## 3. N1 — UI

- [x] 3.1 Labels DARF/recibo em `[clientId].vue`
  Depende de: 1.2, 2.1

## 4. N2 — Gates

- [x] 4.1 `php artisan test --filter=ClientDetailDctfweb` + pint
  Depende de: 1.3, 2.2, 3.1
- [x] 4.2 `openspec validate --change wire-client-guias-declaracoes-dctfweb --strict`
  Depende de: 1.3, 2.2, 3.1
