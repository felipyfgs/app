## 1. N0 — Contrato de submódulos e tipos

- [x] 1.1 Expandir `FiscalModuleKey::Declarations::knownSubmodules()` para `PGDAS`, `DCTFWEB`, `FGTS`, `DEFIS`, `DIRF` (manter `DECLARACOES` como agregado legado se necessário)
- [x] 1.2 Adicionar `DECLARATIONS_TABS` e helpers de normalize/label em `apps/web/app/types/fiscal-modules.ts` / util de submodule
- [x] 1.3 Filtrar portfolio `declarations` por `submodule` → `obligation_code` / origem em `ModulePortfolioQueryService` (overview + clients + detailDeclaracoes + situation SQL)

## 2. N1 — Shell de abas na página Declarações

- [x] 2.1 Refatorar `declarations.vue` com `submodule` local + `useFiscalModulePortfolio('declarations', { submodule })` + `ShellScrollableTabs` no slot `#submodules`
  - Depende de: 1.2, 1.3
- [x] 2.2 Título dinâmico (`PGDAS - Declarações` etc.), reset de página/filtros/modais ao trocar aba, empty/unsupported para DIRF
  - Depende de: 2.1

## 3. N1 — Lista e histórico PGDAS

- [x] 3.1 Colunas PGDAS fiéis à referência: Situação da declaração, Últ. Declaração, Cliente, Última Busca, Histórico de Busca
  - Depende de: 2.1
- [x] 3.2 Extrair/criar `PgdasdDasHistoryModal` (aviso MAED, cliente/CNPJ, filtro ano, tabela 9 colunas, Baixar DAS)
  - Depende de: 1.2
- [x] 3.3 Extrair/criar `PgdasdDeclarationsHistoryModal` aninhado (Operação… Extrato + downloads de artefatos)
  - Depende de: 3.2
- [x] 3.4 Ligar botão Histórico de Busca da lista PGDAS aos modais; reusar no detalhe do cliente quando couber
  - Depende de: 3.1, 3.3

## 4. N1 — Demais abas (mínimo útil)

- [x] 4.1 Aba DCTFWeb: lista filtrada + `DctfwebHistoryModal`
  - Depende de: 2.1
- [x] 4.2 Aba DEFIS: lista + modais DEFIS existentes
  - Depende de: 2.1
- [x] 4.3 Aba FGTS: lista/contrato parcial sem inventar guia/pagamento; aba DIRF unsupported honesto
  - Depende de: 2.2

## 5. N2 — Verificação

- [x] 5.1 Testes unit/fidelity: tabs presentes, default PGDAS, colunas PGDAS, abertura do histórico
  - Depende de: 3.4, 4.1, 4.2, 4.3
- [x] 5.2 Gates web da área: `pnpm run lint`, `pnpm run typecheck`, testes monitoring/fidelity
  - Depende de: 5.1
- [x] 5.3 Gates API tocados: `vendor/bin/pint --test` + testes de portfolio/submodule declarations
  - Depende de: 1.1, 1.3
- [x] 5.4 `npx @fission-ai/openspec@1.6.0 validate --changes --strict` (e `--specs` se aplicável) para esta change
  - Depende de: 5.2, 5.3
