## 1. N0 — Builder Comunicação

- [x] 1.1 Unificar Envio + rastreio em `buildMonitoringComunicacaoColumn` (header Comunicação, id `comunicacao`, célula Send · Switch · ícone) em `monitoring-table-columns.ts`; atualizar constantes/meta/labels compartilhados
- [x] 1.2 Trocar consumers (`pgdasd-table`, `pgmei-table`, `dctfweb-table`, `declarations-table`, `sitfis-table`, FGTS e correlatos) para a coluna única; remover referências à coluna isolada Hist. comunicação / par Envio+Tracking

## 2. N1 — Testes e copy

- [x] 2.1 Atualizar testes source/UX (`list-table-layout`, `monitoring-portfolio-columns`, `monitoring-communication-informational` e afins) para spine Comunicação
  Depende de: 1.1, 1.2
- [x] 2.2 Ajustar comentários/labels mobile que ainda digam “Hist. comunicação” como coluna da grade (tooltips de rastreio podem manter o texto completo)
  Depende de: 1.2

## 3. N2 — Gates

- [x] 3.1 Rodar testes unitários web afetados + `openspec validate --specs --strict` e validate da change
  Depende de: 2.1, 2.2
