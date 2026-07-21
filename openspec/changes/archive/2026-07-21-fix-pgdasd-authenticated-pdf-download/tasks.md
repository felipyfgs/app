## 1. N0 — Composable

- [x] 1.1 Criar `useAuthenticatedDownload` (path Sanctum, blob, erro JSON, save)
- [x] 1.2 Teste unitário do strip de `apiBase` / path canônico
  - Depende de: 1.1

## 2. N1 — Superfície PGDAS-D

- [x] 2.1 `PgdasdHistoryView` — click autenticado
  - Depende de: 1.1
- [x] 2.2 `PgdasdDasHistoryModal` + `PgdasdDeclarationsHistoryModal` + `PgdasdCommunicationModals`
  - Depende de: 1.1

## 3. N2 — Gates

- [x] 3.1 `pnpm` lint/typecheck ou teste unitário da área + `openspec validate` da change
  - Depende de: 2.1, 2.2, 1.2
