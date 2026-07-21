## 1. N0 — Escopo de regime no portfolio

- [x] 1.1 Adicionar `applySimplesMeiSubmoduleScope` em `ModulePortfolioQueryService` e invocá-lo em `scopedClientIdsQuery` para `FiscalModuleKey::SimplesMei` (PGDASD→SN, PGMEI→MEI via `TaxRegimeCode::storageFilterValues`)
- [x] 1.2 Criar `ModulePortfolioSimplesMeiSubmoduleTest` cobrindo lista PGDASD/PGMEI com clientes mistos, overview alinhado e exclusão de outro office

## 2. N1 — Gates

- [x] 2.1 Rodar `php artisan test --filter=ModulePortfolioSimplesMei` e `vendor/bin/pint --test` nos arquivos tocados
  - Depende de: 1.1, 1.2
  - Evidência: 5 passed (12 assertions); pint PASS 2 files via `docker compose exec php`
- [x] 2.2 Validar change: `npx @fission-ai/openspec@1.6.0 validate simples-mei-portfolio-regime-scope --strict --no-interactive`
  - Depende de: 1.1, 1.2
  - Evidência: Change 'simples-mei-portfolio-regime-scope' is valid
