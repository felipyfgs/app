## 1. N0 — Contrato e workspace

- [x] 1.1 Ajustar `detailPaneVisible` / `detailOpen` default na Fila desktop + empty state sem seleção
- [x] 1.2 Auto-selecionar primeira tarefa ao carregar `/work` (Fila desktop, sem id)
- [x] 1.3 Atualizar gates `painel-responsivo-mobile-gate` / `work-orchestration` (auto-select Fila)

## 2. N1 — Validação

- [x] 2.1 `pnpm run test` (gates tocados) + `openspec validate --changes --strict`
  - Depende de: 1.1, 1.2, 1.3
