## 1. N0 — FiscalDocumentAction

- [x] 1.1 Trocar `:href`/`target=_blank` em `FiscalDocumentAction` por `@click` + `useAuthenticatedDownload` (filename a partir de label/kind)
- [x] 1.2 Teste unitário mínimo do path/filename helper (ou cobertura do composable já existente se só wiring)

## 2. N1 — Central de guias

- [x] 2.1 Em `monitoring/guides.vue`, remover `to: doc.href` e baixar via Sanctum (componente ou `onClick`)
  - Depende de: 1.1

## 3. N2 — Gates

- [x] 3.1 `pnpm` lint e/ou typecheck e/ou teste unitário da área + `openspec validate --change fix-fiscal-document-authenticated-download --strict`
  - Depende de: 1.1, 1.2, 2.1
