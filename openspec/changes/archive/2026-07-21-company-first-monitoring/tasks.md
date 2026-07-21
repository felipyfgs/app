## 1. N0 — Overview por processos

- [x] 1.1 Substituir o overview de “Snapshots atuais” em `[clientId].vue` por lista/cards de processos monitorados (labels do catálogo fiscal + link para seção).
- [x] 1.2 Preencher status/última consulta só com dados locais já disponíveis (ou endpoint agregado read-only se necessário); fail-closed sem inventar.
- [x] 1.3 Entrada “Por empresa” no hub `/monitoring` (atalho) mantendo atalhos por módulo.

## 2. N1 — Coordenação e evidência

- [x] 2.1 Alinhar com `slim-monitoring-client-pgdasd` (chrome enxuto) sem conflitar no mesmo PR se possível.
  Depende de: 1.1
- [x] 2.2 Testes unit/fidelity do overview + `openspec validate` da change.
  Depende de: 1.1, 1.2, 1.3
