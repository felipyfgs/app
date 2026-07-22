## 1. N0 — Contratos de apresentação

- [x] 1.1 Atualizar o DTO TypeScript de insights e criar mapeamento puro dos KPIs/estados honestos, com testes Vitest para loading, zero confirmado e fonte indisponível.

## 2. N1 — Dados reais e componentes operacionais

- [x] 2.1 Estender `MonitoringInsightsQueryService` com `kpis.clients_total` isolado por `Office` e falha parcial fail-closed, ampliando `MonitoringInsightsApiTest` para valor real, tenant e contrato nulo.
  Depende de: 1.1
- [x] 2.2 Refatorar os cards de prioridades, atividade, situação fiscal, obrigações, declarações, e-CAC e RBT12 com estados acessíveis e deep-links canônicos, incluindo testes web de domínio e estrutura.
  Depende de: 1.1
- [x] 2.3 Corrigir refs DOM/medição dos gráficos Unovis e fallbacks estáveis sem overflow, com cobertura de contrato nos testes de componentes.
  Depende de: 1.1

## 3. N2 — Composição integral da tela

- [x] 3.1 Reestruturar `/monitoring` no shell canônico com faixa de KPIs, prioridades, saúde das carteiras, contexto analítico e consulta manual secundária; preservar snapshot válido em falha de refresh e cobrir a composição/responsividade em Vitest.
  Depende de: 2.1, 2.2, 2.3

## 4. N3 — Gates integrados

- [x] 4.1 Executar os gates API afetados (`composer validate`, Pint e testes PHP), corrigindo qualquer regressão desta change.
  Depende de: 3.1
- [x] 4.2 Executar os gates web (`lint`, `typecheck`, `generate`, Vitest, fidelity e artifacts) e validar visualmente `/monitoring` em 1920×951 e viewport mobile.
  Depende de: 3.1
- [x] 4.3 Validar a change e as specs OpenSpec em modo strict, revisar o diff para segredos/escopo e registrar a prontidão sem arquivar ou commitar sem autorização.
  Depende de: 4.1, 4.2

Evidência dos gates em 21/07/2026: API completa verde (408 testes/9.259 asserções), lint web, typecheck, generate e Vitest completos verdes (251 testes), OpenSpec change/specs strict verdes e inspeção Playwright sem overflow em 1920×951 e 390×844. Os scripts `test:fidelity` e `test:artifacts` foram executados, mas permanecem indisponíveis no baseline local porque os arquivos versionados esperados `tests/fixtures/template-parity-matrix.md` e `tests/security/scan-artifacts.mjs` não existem; nenhum artefato ausente foi inventado nesta change.
