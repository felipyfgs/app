## 1. N0 — Sidebar e shell

- [x] 1.1 Substituir o spread de `SERPRO_NAV_ITEMS` em `platformAdminDestinations` por um único filho `SERPRO` → `/admin/serpro` (ativo em `/admin/serpro/*`)
- [x] 1.2 Adicionar `SectionNavigation` com `SERPRO_NAV_ITEMS` no toolbar de `pages/admin/serpro.vue`
- [x] 1.3 Atualizar `navigation.test.ts` para o contrato de um único SERPRO no Admin

## 2. N1 — Gates

- [x] 2.1 Gates web da área (teste de navegação + lint dos arquivos tocados) e `openspec validate --changes --strict`
  - Depende de: 1.1, 1.2, 1.3
