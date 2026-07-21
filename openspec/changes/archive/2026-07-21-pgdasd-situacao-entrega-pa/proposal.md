## Why

A coluna Situação PGDAS-D da carteira mistura rótulos ambíguos (“Pendências” para prazo ainda aberto) com um mapeamento de `FiscalSituation` que coloca atraso (`OVERDUE_NOT_FOUND`) no mesmo balde KPI de “ainda no prazo”. O produto decidiu: Situação = **somente entrega do PA esperado**; malha/DAS/MAED ficam fora. É preciso alinhar enum, UI, pós-consulta e linhas já gravadas em `tax_obligation_projections`.

## What Changes

- Fixar o contrato: `PgdasdDeclarationState` continua sendo o estado operacional de **entrega do PA esperado** (mês anterior no fuso do escritório); não incorpora malha SERPRO.
- Alinhar labels da célula com DCTFWeb onde couber: `DUE_WITHIN_DEADLINE` → **No prazo**; manter **Em dia** / **Atrasado** / **Não verificado**.
- **BREAKING (semântica de KPI/filtro):** mapear `OVERDUE_NOT_FOUND` → `FiscalSituation::Attention` (antes `Pending`) no pós-consulta e na projeção.
- Migration de dados: reescrever `tax_obligation_projections.situation` a partir de `pgdasd_declaration_state` para o mapeamento canônico (apenas obrigações `PGDAS_D`).
- Expor `label()` no enum PHP e espelhar no front; testes de resolver/pós-consulta/UI.

## Capabilities

### New Capabilities

- `pgdasd-pa-delivery-situation`: contrato da Situação PGDAS-D como entrega do PA esperado, vocabulário, mapeamento para `FiscalSituation` e consistência persistida na projeção.

### Modified Capabilities

- (nenhuma — `openspec/specs/` sem capabilities arquivadas reutilizáveis para este contrato)

## Impact

- API: `PgdasdDeclarationState`, `PgdasdPostConsultService`, queries/carteira que leem `situation` / KPI PGDAS-D; testes unitários/feature PGDAS.
- Web: `pgdasd.ts` (labels), eventual alinhamento no hub Declarações; testes unitários.
- DB: migration data-fix em `tax_obligation_projections` (coluna `situation` + coerência com `pgdasd_declaration_state`); sem mudança de schema estrutural.
- Fora de escopo: malha SERPRO na Situação, MAED, `dasPago`, rename dos códigos do enum, live SERPRO, flags ON.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias / comportamento já implantado em código PGDAS-D
- Depende de: nenhuma
- Capability/contrato: `pgdasd-pa-delivery-situation`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa bloqueada
- Paralelismo: pode seguir em paralelo com changes que não toquem pós-consulta PGDAS / `tax_obligation_projections.situation` PGDAS_D
