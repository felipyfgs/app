## 1. N0 — Restaurar sidebar Admin

- [x] 1.1 Restaurar imports (`SERPRO_NAV_ITEMS`, `groupEntryTo`, `isNavTabGroup`) e o spread dos itens SERPRO em `platformAdminDestinations` (`apps/web/app/utils/navigation.ts`)
- [x] 1.2 Atualizar o teste unitário de navegação Admin para exigir Escritórios, Módulos fiscais e os três itens `SERPRO · …` (incl. Integração ativa em `/admin/serpro/contracts`)

## 2. N1 — Gates

- [x] 2.1 Rodar gates web da área: `pnpm run lint` / `typecheck` / teste de navegação tocado; `openspec validate --specs --strict` + change
  - Depende de: 1.1, 1.2
