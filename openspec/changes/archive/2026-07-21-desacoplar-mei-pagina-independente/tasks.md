## 1. N0 — Nav, rotas e destino pós-create

- [x] 1.1 Estender `MonitoringModuleKey` com `mei`; adicionar item nav “MEI” → `/monitoring/mei`; retitular item Simples para “Simples Nacional” (sem “| MEI”) em `monitoring-nav.ts` e labels de superfície tocados
- [x] 1.2 Atualizar `monitoringDestinationAfterClientCreate` para MEI → `/monitoring/mei` e SN → `/monitoring/simples-mei`; remover uso de sessionStorage de cápsula (`monitoring-post-create.ts` + callers)
- [x] 1.3 Ajustar middleware/redirect legado: path PGMEI sob `/monitoring/simples-mei/...` → `/monitoring/mei`

## 2. N1 — Páginas Simples e MEI

- [x] 2.1 Criar `pages/monitoring/mei/index.vue` com carteira PGMEI (portfolio `simples_mei` + submodule fixo `PGMEI`, colunas/ações/modais PGMEI, associate MEI)
  - Depende de: 1.1
- [x] 2.2 Slim `pages/monitoring/simples-mei/index.vue`: só PGDAS-D, sem tabs `SIMPLES_MEI_TABS`, título Simples Nacional, remover ramo PGMEI
  - Depende de: 2.1
- [x] 2.3 Atualizar testes unitários (navigation, pós-create/membership, wiring PGMEI/consult) para as duas rotas e labels
  - Depende de: 1.2, 2.1, 2.2

## 3. N2 — Gates

- [x] 3.1 Rodar gates web da área: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (filtros navigation / simples-mei / mei / membership) e `pnpm run generate` se typecheck exigir
  - Depende de: 2.3
- [x] 3.2 Validar change: `npx @fission-ai/openspec@1.6.0 validate desacoplar-mei-pagina-independente --strict --no-interactive`
  - Depende de: 2.3
