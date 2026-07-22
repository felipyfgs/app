## 1. N0 — Extração e classificação do relatório

- [x] 1.1 Adicionar `smalot/pdfparser:^2.12`, contrato/implementação `SitfisPdfTextExtracting` e limites fail-closed de bytes/texto, sem binário externo nem alteração no Compose
- [x] 1.2 Evoluir `SitfisReportParser` para priorizar JSON estruturado e, no fallback PDF, reconhecer seções `Pendência - ...`, ausência geral RFB+PGFN e layout inconclusivo, preservando `is_negative_certificate=false`
- [x] 1.3 Ampliar `SitfisReportParserTest` com PDFs sanitizados para `PENDING`, `UP_TO_DATE`, frase somente PGFN, extração falha e prioridade do retorno estruturado

## 2. N1 — Projeções correntes e reprocessamento local

- [x] 2.1 Restringir contadores da carteira a findings do snapshot SITFIS corrente e pendências `OPEN` originadas por runs SITFIS; adicionar regressão Feature no portfolio
  Depende de: 1.2
- [x] 2.2 Implementar reconciliação SITFIS de findings/pending items ao promover snapshot corrente, sem afetar módulos vizinhos, com testes de desaparecimento/reabertura
  Depende de: 1.2
- [x] 2.3 Implementar comando Artisan office/client-scoped com `--dry-run`, sucessor versionado, mesmo `observed_at`/run/evidência e idempotência; testar que não resolve/chama executor SERPRO
  Depende de: 1.1, 1.2, 2.2

## 3. N2 — Correção dos dados auditados

- [x] 3.1 Executar `--dry-run` no office piloto e confirmar matriz esperada dos 11 PDFs (2 `UP_TO_DATE`, 9 `PENDING`) e hashes/identidades correspondentes
  Depende de: 2.3
- [x] 3.2 Reprocessar localmente os 11 snapshots e confrontar banco, API e `/monitoring/sitfis`, sem consulta SERPRO (contador externo permanece 0)
  Depende de: 3.1

## 4. N3 — Gates integrados

- [x] 4.1 Rodar `composer validate --strict --no-check-publish`, Pint nos arquivos tocados, suíte API relevante/completa e validar a change OpenSpec estritamente
  Depende de: 1.3, 2.1, 2.2, 2.3, 3.2

## 5. N4 — Download autenticado na carteira

- [x] 5.1 Substituir a navegação direta do menu `Ações` por callback de `useAuthenticatedDownload`, mantendo o botão do detalhe no mesmo fluxo
- [x] 5.2 Adicionar regressão unitária para impedir `to: href` em documento SITFIS e validar a página real sem navegação para `/api/v1/...`
- [x] 5.3 Rodar lint, typecheck, testes web relevantes, generate e validação OpenSpec
