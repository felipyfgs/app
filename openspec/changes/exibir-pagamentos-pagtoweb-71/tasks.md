## 1. N0 — Contrato seguro da operação

- [x] 1.1 Catalogar `PAGTOWEB/PAGAMENTOS71` (7.1) com rota, poder, bilhetagem e capacidade fail-closed.
- [x] 1.2 Criar migrações, modelos e projetor de itens sanitizados de pagamento, com identificador mascarado e digest não reversível.
- [x] 1.3 Implementar codec e adaptador da consulta paginada, com allowlist de filtros, período obrigatório, limite de página e logs seguros.
- [x] 1.4 Cobrir codec, mascaramento e resposta externa inválida com testes unitários.

## 2. N1 — Monitor autenticado e isolado

- [x] 2.1 Criar serviço/job e endpoints de consulta e histórico, usando `CurrentOffice`, permissões e procuração aplicável.
  Depende de: 1.1, 1.2, 1.3
- [x] 2.2 Cobrir consulta válida, paginação, filtro rejeitado, isolamento entre escritórios e proveniência simulada nos testes de feature.
  Depende de: 1.4, 2.1

## 3. N2 — Painel de pagamentos por período

- [x] 3.1 Adicionar tipos e cliente de API tipado para a listagem PAGTOWEB 7.1.
  Depende de: 2.1
- [x] 3.2 Adaptar o painel filho do cliente ao arquétipo de lista do template, com período, paginação server-side, documentos mascarados, estado e proveniência.
  Depende de: 3.1
- [x] 3.3 Cobrir a UI e a navegação com testes unitários, incluindo a indicação explícita de execução simulada.
  Depende de: 3.2

## 4. N3 — Evidências e gates integrados

- [x] 4.1 Atualizar a matriz de cobertura e a evidência operacional, distinguindo teste simulado de consulta real autorizada.
  Depende de: 2.2, 3.3
- [x] 4.2 Executar lint, typecheck, testes, build/generate, testes de fidelidade/artefatos, validação OpenSpec e `git diff --check`; registrar resultados sem segredos.
  Depende de: 4.1
