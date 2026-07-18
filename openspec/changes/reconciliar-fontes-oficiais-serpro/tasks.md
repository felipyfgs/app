## 1. N0 — Snapshots oficiais reconciliados

- [x] 1.1 Criar os manifestos datados de fontes e poderes com hashes reais, tipos de evidência explícitos e configuração apontando para as novas versões; comprovar que catálogo oficial, snapshot e matriz continuam 119:1 e sem diff de coordenada, estado ou rota.

## 2. N1 — Validação fail-closed e verificação vigente

- [x] 2.1 Endurecer o registro/loader para rejeitar fonte canônica inválida ou placeholder, validar coerência entre os três recursos e cobrir os cenários com testes offline focados.
  Depende de: 1.1

- [x] 2.2 Implementar comando read-only e sanitizado de verificação HTTP das fontes allowlisted, com timeout/limite, `REVIEW_REQUIRED` e testes via HTTP fake, sem acessar endpoint fiscal de negócio.
  Depende de: 1.1

## 3. N2 — Ledger factual

- [x] 3.1 Corrigir matriz e evidências piloto para registrar 25 mutações produtivas, 33 totais, invalidar classificações históricas insuficientes e documentar o bloqueio de proveniência sem promover operação a `READY_PRODUCTION`.
  Depende de: 2.1, 2.2

## 4. N3 — Gates integrados e evidência de prontidão

- [x] 4.1 Executar validação HTTP vigente, testes SERPRO focados, Pint nos arquivos tocados, validação OpenSpec estrita e varredura de segredos/placeholders; registrar evidência sanitizada e `VERDICT: PASS` independente para a change.
  Depende de: 3.1
