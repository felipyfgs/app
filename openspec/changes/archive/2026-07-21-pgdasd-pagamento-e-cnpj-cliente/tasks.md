## 1. N0 — API portfolio (paralelo)

- [x] 1.1 Expor `cnpj` normalizado (14 chars) em `ModuleClientRowDto` / `ModulePortfolioQueryService` sem alterar semântica de `cnpj_masked`
- [x] 1.2 Montar `payment_open_competencies` no batch de `PgdasdMonitoringQueryService::portfolioDetails` (DAS unpaid por cliente, agregação por `period_key`, `amount_cents` opcional via `tax_guides`)
- [x] 1.3 Propagar `payment_open_competencies` em `ModulePortfolioQueryService` no bloco `detail.pgdasd`
- [x] 1.4 Testes unitários API: lista de competências + presença de `cnpj` no DTO/payload (`php artisan test --filter=Pgdasd` / filtro do DTO)

## 2. N1 — Web tipos e popover Pagamento

- [x] 2.1 Tipar `cnpj` na row do portfolio e `payment_open_competencies` em `PgdasdClientSummary` (`fiscal-modules.ts`)
  - Depende de: 1.1, 1.3
- [x] 2.2 Redesenhar `pgdasdPaymentDetailItems` / `PaymentValue.vue`: sem contagens nem reason codes; `UNPAID` lista competências (`MM/YYYY` + valor pt-BR se houver)
  - Depende de: 2.1
- [x] 2.3 Testes unitários web do detalhe Pagamento (`pnpm run test` filtro `pgdasd`)
  - Depende de: 2.2

## 3. N1 — Web célula Cliente CNPJ

- [x] 3.1 Atualizar `FiscalClientCell`: prop `cnpj`, `formatCnpj` na exibição, click copia `normalizeCnpj` + toast; clique não navega
  - Depende de: 1.1
- [x] 3.2 Passar `cnpj` nos builders/páginas que usam a célula (PGDASD, PGMEI, DCTF, SITFIS, Declarações, FGTS, Parcelamentos)
  - Depende de: 3.1
- [x] 3.3 Teste unitário ou componente cobrindo máscara BR + intenção de cópia (quando houver harness; senão assert utilitário + smoke da célula)
  - Depende de: 3.2

## 4. N1 — Popover unpaid: competência · valor

- [x] 4.1 Em `UNPAID` com lista, remover linha Situação|Pendências; cada competência mostra `MM/YYYY` + valor (`formatAmountCents` / "—")
  - Depende de: 2.2
- [x] 4.2 Atualizar teste unitário `pgdasd` + delta spec do cenário UI
  - Depende de: 4.1
- [x] 4.3 `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validação da change ativa com delta
## 5. N2 — Gates integrados

- [x] 5.1 Gate API da área: `vendor/bin/pint --test` + `php artisan test` nos filtros tocados
  - Depende de: 1.4, 2.3, 3.3, 4.2
- [x] 5.2 Gate web da área: `pnpm run lint` + `pnpm run typecheck` + `pnpm run test` (filtros tocados)
  - Depende de: 2.3, 3.3, 4.2
  - Escopo da change: `eslint` nos arquivos owned + `vitest` (`pgdasd` / `fiscal-client-cell`) OK.
  - `pnpm run typecheck` global falha só em siblings pré-existentes (`ClientForm.vue`, `ClientRegistrationRefreshModal.vue`, `AssociateMonitoringClientsModal.vue`, `Portfolio.vue` assignment `@click`) — fora do escopo desta change.
- [x] 5.3 `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validação da change ativa com delta
  - Depende de: 5.1, 5.2
