# 1.1 Changes ativas e escopo sobreposto

**Registrado em:** 2026-07-15  
**Comando:** `openspec list --json`  
**Template fixado:** `.reference/nuxt-dashboard-template` @ `0f30c09d697160ef5dd0aaaec27fae8d7195d930`

## Changes ativas no momento do baseline

| Change | Tasks | Status | Impacto em páginas/componentes/specs/testes deste escopo |
|--------|------:|--------|----------------------------------------------------------|
| `refactor-complete-dashboard-ui-ux` | 0/118 | in-progress | **Esta change** — matriz integral UI/UX |
| `complete-cte-capture-with-distdfe-autxml-and-import` | 130/140 | in-progress | Specs `frontend-dashboard-experience`, páginas CT-e/settings/clients captura |
| `integrate-cte-into-document-catalog` | 0/16 | in-progress | Catálogo de documentos / notes; specs frontend |
| `consolidate-fiscal-data-model` | 90/90 | complete | Backend/modelagem; testes e docs ops (worktree sujo residual) |
| `add-operational-process-management` | 110/120 | in-progress | **Alto** — `pages/work/**`, `admin/departments`, Home KPIs, fixtures/e2e work |
| `add-resilient-svrs-nfe55-outbound-xml-retrieval` | 124/137 | in-progress | Specs frontend/ops; painel captura saídas |
| `align-serpro-protocol-and-sitfis-monitoring` | 33/33 | complete | Monitoramento SitFis / frontend-dashboard |
| `complete-monitoring-visual-fixtures` | 92/92 | complete | **Alto** — `/monitoring/**`, fixtures fiscais, Playwright visual |
| `build-complete-fiscal-monitoring-hub` | 153/153 | complete | Rotas e componentes de monitoramento |
| `standardize-dashboard-tables` | 17/17 | complete | **Alto** — presets tabulares, política de URL canônica em listas Customers |
| `add-office-autxml-and-bulk-xml-import` | 138/145 | in-progress | Importações XML, admin autXML, docs/imports |

## Política desta change diante de concorrência

1. **Não sobrescrever** arquivos backend nem docs/ops de modelagem fiscal (`consolidate-fiscal-data-model`, SVRS, CTE).
2. **Preservar e estender** o trabalho em `frontend/app/pages/work/**`, fixtures e e2e de `add-operational-process-management` — realinhar ao template sem apagar domínio.
3. **Preservar** contratos e fixtures de monitoramento (`complete-monitoring-visual-fixtures`).
4. Qualquer rota nova criada por change concorrente **entra na matriz** (tarefa 1.4) antes de aceite.
5. Patches de UI desta change ficam em `frontend/` (páginas, componentes shell, utils de UI, testes e2e/visual) e artefatos em `openspec/changes/refactor-complete-dashboard-ui-ux/`.
