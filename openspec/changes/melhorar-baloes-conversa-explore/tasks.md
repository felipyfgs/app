## 1. N0 — Bolhas e contrato unitário

- [x] 1.1 Refinar em `CommunicationTimelinePanel` a largura adaptativa, o alinhamento, a cauda, as superfícies semânticas e a hierarquia de origem/conteúdo/metadados para inbound, outbound e nota interna, preservando mídia, citação, reações e ações existentes.
  Depende de: change `colapsar-contexto-atendimento` no marco `apply`; change `evoluir-atendimento-whatsapp-multimidia` no marco `apply`.
- [x] 1.2 Ampliar `communication-workspace-ui-gate.test.ts` para provar limites responsivos, distinção textual/semântica, metadados compactos e ações visíveis por `focus-within`/touch (`pnpm run test -- communication-workspace-ui-gate`).
  Depende de: change `colapsar-contexto-atendimento` no marco `apply`; change `evoluir-atendimento-whatsapp-multimidia` no marco `apply`.

## 2. N1 — Verificação integrada

- [x] 2.1 Executar os gates completos Web (`pnpm run lint`, `pnpm run typecheck`, `pnpm run generate`, `pnpm run test`, `pnpm run test:fidelity`, `pnpm run test:artifacts`) e corrigir regressões.
  Depende de: 1.1, 1.2.
  Evidência: lint, typecheck, generate, fidelity e artifacts aprovados; gate dedicado 9/9 aprovado; suíte completa 346/347 com uma falha preexistente e fora do ownership em `work-strategic-dashboard.test.ts` (`work-dashboard-performance` ausente).
- [x] 2.2 Validar a change OpenSpec em modo estrito e inspecionar mensagens curtas, longas, internas e com anexo em desktop/mobile e light/dark, registrando qualquer limitação residual.
  Depende de: 1.1, 1.2.
  Evidência: change válida em `--strict`; 25 mensagens reais inspecionadas em desktop/mobile light/dark sem overflow horizontal; nota interna inspecionada com resposta interceptada, sem persistência nem egress.
