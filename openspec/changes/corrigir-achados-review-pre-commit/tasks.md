## 1. N0 — Corrigir implementação e evidências locais

- [x] 1.1 Esvaziar `APP_KEY`/`VAULT_MASTER_KEY` nos exemplos versionados e ampliar `test:artifacts` para rejeitar chaves operacionais preenchidas, comprovando o gate com os exemplos reais.
- [x] 1.2 Isolar por allowlist o ambiente de `ProcessFgtsDigitalPortalClient` e adicionar teste PHPUnit que comprova que segredo artificial do Horizon não chega ao worker.
  Depende de: change `automatizar-guias-fgts-digital-portal` no marco `apply`.
- [x] 1.3 Corrigir a precedência de `payment_status_from_cells` e cobrir `não pago`, `em aberto`, `pago` e `pagamento parcial` em `test_worker.py`.
  Depende de: change `automatizar-guias-fgts-digital-portal` no marco `apply`.
- [x] 1.4 Tipar o payload bulk dinâmico sem afrouxar `changes.action`, remover o re-export auto-importado duplicado e validar por Vitest/typecheck.
  Depende de: change `gerenciar-listas-trabalho-bulk-sort` no marco `apply`.
- [x] 1.5 Remover `NopechaCaptchaService`, provider, teste e configuração PHP redundantes, mantendo somente `fgts_digital.captcha` e o solver do worker Playwright; comprovar container Laravel sem binding órfão.
- [x] 1.6 Atualizar e regenerar inventários de páginas/rotas, grafo de testabilidade e matriz de fidelidade para classificar `/work/tasks` e o preview de anexo revelados pelos gates integrais.

## 2. N1 — Tornar os gates completos

- [x] 2.1 Adicionar job Go com `go test ./...` e `go vet ./...` e incluir `horizon`/`whatsapp-gateway` no build de desenvolvimento do CI.
  Depende de: 1.2, 1.3; change `adicionar-comunicacao-whatsapp-nativa` no marco `apply`.
- [x] 2.2 Executar o unittest Python do FGTS dentro da imagem Horizon RPA no CI e validar que o Compose continua sem serviços `mei`/`mei-worker`.
  Depende de: 1.2, 1.3.

## 3. N2 — Verificação integrada

- [x] 3.1 Rodar gates API completos, unittest Python/RPA e testes/vet Go, corrigindo qualquer regressão encontrada.
  Depende de: 1.1, 1.2, 1.3, 1.5, 2.1, 2.2.
- [x] 3.2 Rodar gates web completos (`lint`, `typecheck`, `generate`, `test`, `test:fidelity`, `test:artifacts`) sem warnings de auto-import duplicado.
  Depende de: 1.1, 1.4, 1.6.
- [x] 3.3 Validar Compose dev/prod, specs/changes OpenSpec strict, diff/secret scan e confirmar flags OFF e ausência de `mei`/`mei-worker`.
  Depende de: 2.1, 2.2.
