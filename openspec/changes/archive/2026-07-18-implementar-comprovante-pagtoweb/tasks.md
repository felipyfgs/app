## 1. N0 — Contrato oficial e armazenamento seguro

- [x] 1.1 Confrontar `PAGTOWEB/COMPARRECADACAO72/1.0` com a página oficial específica e registrar coordenada, poderes, bilhetagem, request/response e fixture sanitizada; interromper se divergir do snapshot.
  - Evidência: página oficial `solucoes/integra-pagamento/pagtoweb/servicos/emite_comprovante_pagamento/` e snapshot `official-service-catalog.v2026-07-16.json` concordam em `Emitir`, poder `00004`, `numeroDocumento` (até 17 bytes), PDF Base64, não mutante e emissão bilhetável.
- [x] 1.2 Criar DTO, codec fail-closed e testes para request, PDF Base64 válido, ausência/erro, MIME/assinatura inválidos, limite de bytes e dados sensíveis.
  - Depende de: 1.1
- [x] 1.3 Criar migration forward-only, modelo e projetor idempotente com bytes somente no `SecureObjectStore`, AAD tenant-scoped e descritor sanitizado.
  - Depende de: 1.2
- [x] 1.4 Generalizar a captura documental pré-ACK para `pagtoweb.comparrecadacao`, impedindo PDF Base64 em attempt, evidência, log e replay; falhar fechado antes do ACK se o cofre/projeção falhar.
  - Usar dispatcher por `operation_key` e redator dinâmico para remover `numeroDocumento` de respostas remotas, inclusive 4xx.
  - Depende de: 1.2, 1.3

## 2. N1 — Execução e API tenant-scoped

- [x] 2.1 Mapear `pagtoweb.comparrecadacao` no resolver/adapter de guias e executar exclusivamente pela cadeia central de capability, orçamento, rate limit, procuração e bilhetagem.
  - Depende de: 1.1, 1.2, 1.4
- [x] 2.2 Expor GET de histórico local, POST confirmado com `numeroDocumento` efêmero e download same-origin autorizado, recusando `office_id`, referência estrangeira e parâmetros técnicos SERPRO; não persistir nem registrar o número completo.
  - Executar o POST em caso de uso manual síncrono, sem serializar o número em `progress`, job, retry ou continuação.
  - Depende de: 1.3, 2.1
- [x] 2.3 Cobrir tenancy, RBAC, ausência de egress em GET, idempotência, timeout sem retry cego, cofre e download em testes Laravel.
  - Depende de: 2.2

## 3. N2 — Painel de comprovantes no monitoramento

- [x] 3.1 Adicionar contratos frontend e composable para histórico, solicitação e download sem transportar `office_id`; aceitar `numeroDocumento` somente como entrada efêmera validada e mascará-lo antes da confirmação.
  - Depende de: 2.2
- [x] 3.2 Integrar painel de comprovantes em `/monitoring/guides` ou no detalhe de guias do cliente conforme o arquétipo do painel, com loading, vazio, erro, bloqueio, histórico e download autorizado.
  - Depende de: 3.1
- [x] 3.3 Implementar confirmação em duas etapas antes do POST e cobrir com testes Nuxt a ausência de chamada em montagem/GET/modal, permissões e mensagens sanitizadas.
  - Depende de: 3.2

## 4. N3 — Evidências integradas

- [x] 4.1 Atualizar a linha do ledger com contrato, maturidade local, testes e blocker externo, sem promover Trial/mock a `PASS_REAL_*`.
  - Depende de: 2.3, 3.3
- [x] 4.2 Executar Pint, testes Laravel e Nuxt focados, typecheck, fidelity, scan de artefatos, OpenSpec estrito e varredura de segredos; registrar evidência e a pendência de Trial/canário autorizado.
  - Evidência 18/07/2026: Pint 1701 arquivos; 24 testes Laravel focados/101 asserções; Vitest 3 testes; typecheck/lint; fidelity PASS; scan de artefatos sem segredo; OpenSpec estrito. Não houve egress; Trial/canário seguem dependentes de autorização explícita.
  - Depende de: 4.1
