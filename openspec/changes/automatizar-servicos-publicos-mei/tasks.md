## 1. N0 — Contratos e exploração

- [x] 1.1 Explorar PGMEI e DASN-SIMEI em ambiente controlado, documentar checkpoints/limitações e criar fixtures sanitizadas sem CNPJ ou conteúdo fiscal real.
  Depende de: `adicionar-orquestrador-portal-mei` no marco `verify` (bloqueante).
- [x] 1.2 Criar schemas Pydantic por operação, modelos de resultado/cobertura e `OperationRegistry` com testes de rejeição antes do browser.
  Depende de: `adicionar-orquestrador-portal-mei` no marco `verify` (bloqueante).
- [x] 1.3 Definir requests/responses Laravel tenant-scoped para emissão DAS, dívida ativa, histórico DASN e consulta de tentativa, com testes de autorização.
  Depende de: `adicionar-orquestrador-portal-mei` no marco `verify` (bloqueante).
- [x] 1.4 Definir tipos/API factories Nuxt e contratos de apresentação para progresso, cobertura, artefatos e proveniência, com testes unitários.
  Depende de: `adicionar-orquestrador-portal-mei` no marco `verify` (bloqueante).

## 2. N1 — Execução por domínio

- [x] 2.1 Implementar handlers/parsers PGMEI para `gerardaspdf`, `gerardascodbarra` e `dividaativa`, incluindo checkpoints, CNPJ alfanumérico e testes por fixture.
  Depende de: 1.1, 1.2
- [x] 2.2 Implementar handler/parser `dasnsimei.consultimadecrec` com cobertura `SUMMARY`/`FULL` explícita e testes por fixture.
  Depende de: 1.1, 1.2
- [x] 2.3 Implementar validação de PDF/código de barras e contrato pluggable de captcha fail-closed, com TTL e testes de conteúdo inválido.
  Depende de: 1.2
- [x] 2.4 Implementar tradução Laravel do resultado portal, persistência de tentativa/proveniência e ingestão de artefatos no vault.
  Depende de: 1.2, 1.3

## 3. N2 — Orquestração Laravel

- [x] 3.1 Implementar endpoints/workflows de emissão DAS, dívida ativa e histórico DASN, usando preflight/idempotência e polling assíncrono.
  Depende de: 2.1, 2.2, 2.4
- [x] 3.2 Cobrir roteamento Portal -> SERPRO, ledger sem consumo no sucesso portal e `UNCERTAIN` sem reenvio após submissão.
  Depende de: 2.1, 2.2, 2.4
- [x] 3.3 Integrar handlers ao worker/Compose e executar smoke fixture de cada operação com isolamento de contexto.
  Depende de: 2.1, 2.2, 2.3

## 4. N3 — Interface Nuxt

- [x] 4.1 Implementar emissão de DAS, histórico DASN-SIMEI, progresso, downloads e badges de proveniência no painel Nuxt com testes de estados e tenancy.
  Depende de: 1.4, 3.1

## 5. N4 — Gates integrados

- [x] 5.1 Executar OpenSpec estrito, testes/ruff/mypy Python, Laravel/Pint, `pnpm run test:gate`, `pnpm run generate` e smoke Docker, registrando evidência sanitizada.
  Depende de: 3.2, 3.3, 4.1
  Concluído em 2026-07-19: gates Python, Laravel, Nuxt, OpenSpec, Compose e smoke Docker passaram; evidência sanitizada em `docs/verification/automatizar-servicos-publicos-mei.md`.
