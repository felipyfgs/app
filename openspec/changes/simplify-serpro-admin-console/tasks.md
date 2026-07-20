## 1. N0 — Catálogo e páginas

- [x] 1.1 Reduzir `SERPRO_NAV_ITEMS` a Visão geral + Configuração (sem Canário; `isActive` cobre deep-links)
- [x] 1.2 Enxugar `pages/admin/serpro/index.vue`: remover tabs/embeds; Status + links secundários
- [x] 1.3 Enxugar `pages/admin/serpro/configuration.vue`: remover tabs/embeds/pending offices/histórico longo; Acesso essencial + links secundários

## 2. N1 — Testes e gates

- [x] 2.1 Atualizar testes de navegação/tabs tocados; vitest + lint da área; `openspec validate --changes --strict`
  - Depende de: 1.1, 1.2, 1.3
