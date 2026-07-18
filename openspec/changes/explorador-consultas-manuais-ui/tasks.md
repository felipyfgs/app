## 1. N0 — Contrato de inventário e elegibilidade

- [x] 1.1 Mapear `operation_key`s de leitura (`PRODUCTION` + `IMPLEMENTED` + não mutantes) a partir de `MonitoringSurfaceRegistry` e dos POSTs de consult já existentes; registrar gaps `adapter_missing` sem inventar envelope genérico.
- [x] 1.2 Implementar serviço de inventário + elegibilidade (módulo, capability, token/poder, handler) e DTOs públicos sanitizados (`action_id`, labels, params_schema, last_result_summary).
- [x] 1.3 Expor `GET` de inventário tenant-scoped (`CurrentOffice`) e testes Feature de listagem, bloqueio sem office e exclusão de mutantes/PROSPECTION.

## 2. N1 — Execução confirmada e despacho

- [x] 2.1 Implementar façade `POST` de consulta manual com `confirmed: true`, validação de `client_id` no office e recusa de mutantes/ações não `ready`.
  - Depende de: 1.2
- [x] 2.2 Ligar despacho aos handlers existentes (PGDASD/DEFIS/CCMEI/PGMEI/REGIME, DCTFWEB/MIT leitura, SITFIS, mailbox/DTE, PAGTOWEB/SICALC 52, parcelamentos leitura, PNR, e-Processo 271) e persistir/enfileirar como cada adapter já faz.
  - Depende de: 2.1
- [x] 2.3 Cobrir testes Feature: confirmação obrigatória, cross-tenant, capability/módulo off, adapter_missing, resposta sem segredos; ao menos um fluxo síncrono e um assíncrono (SITFIS) com fake/simulated.
  - Depende de: 2.2

## 3. N2 — UI explorador e CTAs nos módulos

- [x] 3.1 Adicionar tipos/cliente API e composable do inventário/execução no frontend (sem GET que dispare SERPRO).
  - Depende de: 1.3, 2.1
- [x] 3.2 Criar página/painel do explorador em `/monitoring` (filtro cliente/módulo, lista de ações, elegibilidade, modal de confirmação + params mínimos, deep-link ao módulo).
  - Depende de: 3.1
- [x] 3.3 Integrar CTA de consulta manual nas superfícies prioritárias (simples-mei, dctfweb/mit, sitfis, mailbox, guides, installments, registrations, tax-processes) reutilizando o mesmo contrato; recarregar projeção/histórico local após sucesso.
  - Depende de: 3.1, 2.2
- [x] 3.4 Testes Vitest do explorador e do CTA (ação não `ready` desabilitada; abrir UI não consulta SERPRO; confirmação chama POST mockado).
  - Depende de: 3.2, 3.3

## 4. N3 — Gates de prontidão

- [x] 4.1 Rodar gates backend direcionados (`php artisan test` filtro ManualConsult/inventário/execução) e frontend (`pnpm run test:gate` ou suíte unit do explorador) com evidência PASS.
  - Depende de: 2.3, 3.4
- [x] 4.2 Validar change OpenSpec (`npx openspec validate explorador-consultas-manuais-ui --strict` ou equivalente do schema) e checklist de non-goals (sem mutação, sem capability real forçada, sem office_id do client).
  - Depende de: 4.1
