## 1. N0 — Parser

- [x] 1.1 Evoluir `PgdasdRbt12Parser` para blocos multi-linha (cabeçalho + valores); bump `VERSION` para `pgdasd-rbt12-v2`; manter fail-closed.
- [x] 1.2 (Opcional útil) Capturar RPA etiquetada em `metadata` / campo público opcional sem afetar o total RBT12.
- [x] 1.3 Testes PHP unitários com fixtures linha-única e tabular (parse / not_found / ambiguous).

## 2. N1 — UI tooltip

- [x] 2.1 Humanizar razões e enriquecer `pgdasdRbt12Tooltip` / `Rbt12Value` (composição; distinguir RPA se presente).
  Depende de: 1.1

## 3. N2 — Gates

- [x] 3.1 Rodar teste PHP do parser + teste web do tooltip (se houver) + `openspec validate` da change.
  Depende de: 1.3, 2.1
