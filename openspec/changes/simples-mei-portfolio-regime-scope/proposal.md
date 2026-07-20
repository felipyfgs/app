## Why

Na carteira `/monitoring/simples-mei`, as abas Simples Nacional (PGDASD) e MEI (PGMEI) listam a mesma população de clientes ativos da matriz: o `submodule` chega à API, mas `scopedClientIdsQuery` só aplica escopo por obrigação em Declarações — não por regime em Simples/MEI. O resultado parece “vazamento invertido” (MEI na aba Simples e vice-versa), quando na verdade é ausência de filtro. O scheduler já respeita `tax_regime`; a carteira precisa alinhar.

## What Changes

- Filtrar a carteira do módulo `simples_mei` por família de regime conforme `submodule`:
  - `PGDASD` → apenas clientes da família Simples Nacional
  - `PGMEI` → apenas clientes da família MEI
- Aplicar o mesmo escopo em `clients`, `overview` e demais agregações que usam `scopedClientIdsQuery` (fonte única).
- Cobrir com teste feature análogo ao de Declarações (clientes mistos → cada aba só o regime certo).
- Manter isolamento por `office_id` explícito (já existente); sem abrir scopes privilegiados.

## Capabilities

### New Capabilities

- `simples-mei-portfolio-scope`: carteira Simples/MEI filtrada por `submodule` via `clients.tax_regime` (famílias SN vs MEI), sem misturar regimes entre abas e sem relaxar tenancy.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; o contrato novo cobre o comportamento da carteira)

## Impact

- API: `ModulePortfolioQueryService::scopedClientIdsQuery` (+ helper espelhando `applyDeclarationsSubmoduleScope`), uso de `TaxRegimeCode::storageFilterValues()`.
- Testes: feature `ModulePortfolioSimplesMeiSubmoduleTest` (ou equivalente).
- Front: sem mudança de contrato (já envia `submodule=PGDASD|PGMEI`); comportamento da lista passa a refletir o filtro.
- Non-goals: SERPRO live, mutações fiscais, flags ON, mexer em sidebar/URL das abas, seed DEMO, filtro por `office_fiscal_category_links` como fonte primária (regime canônico é `tax_regime`).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: nenhuma main spec; padrão de referência = change arquivável `declarations-obligation-tabs` (filtro por submodule no portfolio).
- Depende de: nenhuma
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente das changes ativas restantes (não compartilha ownership de capability)
