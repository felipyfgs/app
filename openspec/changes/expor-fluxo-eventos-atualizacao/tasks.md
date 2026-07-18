## 1. N0 — Contrato e evidência oficial

- [x] 1.1 Confrontar as quatro páginas oficiais 13.1–13.4 com o snapshot, registrar envelope de lote PF/PJ, campos de obtenção e a divergência `eventValue`/`evento` PJ; bloquear Trial/produção até reconciliação oficial versionada.
  Evidência: a página oficial PJ consultada em 18/07/2026 exige lote em `contribuinte.numero` com tipo 4 e lista `eventValue` na tabela, mas seu exemplo usa `dados: {"evento":"E0301"}`; a divergência foi registrada no design e bloqueia egress até reconciliação.
- [ ] 1.2 Criar fixtures sanitizadas e testes de contrato que recusem campos, resposta ou proveniência ambíguos e não persistam matriz de eventos/NI.
  Depende de: 1.1

## 2. N1 — Adapter e projeção pública seguros

- [ ] 2.1 Criar envelope/DTO dedicado ao lote PF/PJ e corrigir `EventosAtualizacaoFlowService` para enviar o campo PJ somente conforme contrato reconciliado e `{ protocolo, evento }` em ambas as obtenções, com testes unitários do executor double.
  Depende de: 1.1, 1.2
- [ ] 2.2 Implementar presenter/DTO de run público que omita identificadores internos, protocolo, correlação, chaves de operação, matriz e PII.
  Depende de: 1.2
- [ ] 2.3 Cobrir consumo one-shot, janela oficial sem egress, rate limit, bloqueio, resposta vazia de negócio e isolamento por escritório nos testes Laravel.
  Depende de: 2.1, 2.2

## 3. N2 — API autorizada do cliente

- [ ] 3.1 Criar controller e rotas tenant-scoped para histórico local, solicitação confirmada e obtenção explícita por run opaca; recusar `office_id`, cliente ou run estrangeiros.
  Depende de: 2.2, 2.3
- [ ] 3.2 Cobrir Feature tests de RBAC, `CurrentOffice`, GET sem egress, confirmação, payload mínimo e estados da máquina assíncrona.
  Depende de: 3.1

## 4. N3 — Painel operacional de eventos

- [ ] 4.1 Consultar fontes públicas oficiais da experiência de eventos/monitoramento e registrar a referência visual antes de alterar a UI fiscal.
- [ ] 4.2 Criar contratos Nuxt e composable sem `office_id`, protocolo, operação ou conteúdo bruto.
  Depende de: 3.1
- [ ] 4.3 Implementar painel no detalhe do cliente seguindo `panel-ui` e `ui-archetype`, com histórico, confirmação, espera, bloqueio, limite, erro, sucesso e atualização manual sem polling.
  Depende de: 4.1, 4.2
- [ ] 4.4 Cobrir no Vitest abertura sem egress, confirmação, estados, permissão, dados sanitizados e ações de solicitação/obtenção.
  Depende de: 4.3

## 5. N4 — Ledger e validação integrada

- [ ] 5.1 Atualizar as quatro linhas `eventosatualizacao.*` no ledger com maturidade local, contratos e bloqueio de Trial/canário, sem promover produção.
  Depende de: 3.2, 4.4
- [ ] 5.2 Executar Pint, testes Laravel focados, lint, typecheck, Vitest, generate, fidelity, scan de artefatos, OpenSpec estrito e varredura de segredos; registrar limitações reais.
  Depende de: 5.1
