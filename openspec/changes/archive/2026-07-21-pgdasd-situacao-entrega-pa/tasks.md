## 1. N0 — Domínio e persistência

- [x] 1.1 Adicionar `label()` e `toFiscalSituation()` em `PgdasdDeclarationState` com mapeamento canônico
- [x] 1.2 Usar o mapeamento no `PgdasdPostConsultService` (OVERDUE → ATTENTION)
- [x] 1.3 Criar migration data-fix: reescrever `tax_obligation_projections.situation` para `PGDAS_D` a partir de `pgdasd_declaration_state`

## 2. N1 — UI e contrato público

- [x] 2.1 Atualizar labels em `apps/web/app/utils/pgdasd.ts` (`DUE_WITHIN_DEADLINE` → No prazo)
  Depende de: 1.1
- [x] 2.2 Garantir hub Declarações / indicadores PGDAS usam as mesmas labels via `pgdasdDeclarationMeta`
  Depende de: 2.1

## 3. N1 — Testes da mudança

- [x] 3.1 Testes unitários PHP do enum/mapeamento e ajuste do pós-consulta se houver cobertura existente
  Depende de: 1.1, 1.2
- [x] 3.2 Teste unitário web das labels (`pgdasd.test.ts`)
  Depende de: 2.1

## 4. N2 — Gates integrados

- [x] 4.1 Rodar testes/filtros API relevantes + pint na área tocada
  Depende de: 1.3, 3.1
- [x] 4.2 Rodar `pnpm run test` (ou filtro unit) + typecheck na área web tocada
  Depende de: 3.2
- [x] 4.3 `npx @fission-ai/openspec@1.6.0 validate --specs --strict` e validate da change
  Depende de: 4.1, 4.2
