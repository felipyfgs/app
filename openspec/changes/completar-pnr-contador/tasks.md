## 1. N0 — Contratos locais de leitura PNR

- [x] 1.1 Completar o catálogo reconciliado e os payloads de prova para as três operações PNR de leitura, sem incluir `solicitar_renuncia`; cobrir as coordenadas em teste offline.
- [x] 1.2 Criar codecs fail-closed e testes unitários para histórico, situação e descritor de comprovante, incluindo rejeição de fonte/layout inválidos.

## 2. N1 — Projeção e API tenant-scoped

- [x] 2.1 Criar migrações, modelos e serviço de projeção idempotente para evidências PNR sanitizadas, com referência ao cofre para comprovante quando existir.
  Depende de: 1.1, 1.2
- [x] 2.2 Expor rotas e ações manuais protegidas por `CurrentOffice`, sem `office_id` do cliente, e cobrir isolamento entre escritórios e ausência válida de eventos.
  Depende de: 1.1, 1.2

## 3. N2 — Superfície de monitoramento

- [x] 3.1 Adicionar contratos de API e testes de UI para consultas PNR manuais, estados de vazio/erro/carregamento e rótulo de proveniência TRIAL/real.
  Depende de: 2.2
- [x] 3.2 Implementar a superfície de renúncias no detalhe do cliente seguindo o arquétipo do painel, sem botão ou fluxo de solicitar renúncia.
  Depende de: 3.1

## 4. N3 — Integração e evidências

- [x] 4.1 Atualizar a matriz de cobertura e executar os testes focados de backend e frontend, mantendo a classificação de produção bloqueada até evidência de canário.
  Evidências: matriz `docs/ops/integra-contador-matriz-cobertura.md`; 18 testes PHP/65 assertions e 12 testes de frontend aprovados em 18/07/2026. A produção permanece bloqueada até o canário autorizado.
  Depende de: 2.1, 2.2, 3.1, 3.2
- [x] 4.2 Executar os gates integrados aplicáveis (Pint, testes PHP, typecheck e testes frontend) e registrar o resultado verificável.
  Evidências: `vendor/bin/pint --test` (1.667 arquivos), suíte PHP focada PNR (18 aprovados), `pnpm run typecheck` e Vitest focado (12 aprovados) em 18/07/2026.
  Depende de: 4.1
