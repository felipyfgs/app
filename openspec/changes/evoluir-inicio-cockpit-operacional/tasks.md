## 1. N0 — Contrato API do summary

- [x] 1.1 Expandir Feature test de `GET /api/v1/operations/summary` cobrindo chaves tipadas já existentes no builder (`platform_health`, `blocks`, `serpro_authorization`, `proxy_powers`, `modules`, `fiscal_pending`, `fiscal_coverage`, `usage`, `subscription`, `uncertain_results`, `guides_due_7d`) + isolamento de tenant + ausência de campos proibidos
- [x] 1.2 Implementar seção `communication` no `OperationsSummaryBuilder` (flags + inboxes por status + outbox RETRY/DEAD + conversas OPEN/PENDING) com fail-closed `available: false` sob exceção
- [x] 1.3 (Opcional barato) Adicionar contagens leves `mei_automation` e/ou `fiscal_runs` 24h no summary; se inviável, documentar omissão honesta e pular UI
- [x] 1.4 Feature test das seções novas (comunicação; contagens leves se 1.3) + isolamentoação de office

## 2. N1 — Tipos e utilitários web

- [x] 2.1 Expandir `OperationsSummary` (e tipos auxiliares) em `apps/web/app/types/api.ts` para espelhar o contrato do summary
  Depende de: 1.2
- [x] 2.2 Criar helpers de mapeamento KPI/banner (blocos, fiscal slice, SERPRO office, comunicação) em `apps/web/app/utils/` com Vitest fail-closed (loading ≠ zero)
  Depende de: 2.1

## 3. N1 — UI do cockpit Início

- [x] 3.1 Adicionar componentes `components/home/` para Bloqueios/saúde, Fiscal resumo, SERPRO do office e Atendimento (padrão `ShellKpiStrip` / `UPageCard`)
  Depende de: 2.1, 2.2
- [x] 3.2 Reestruturar `pages/index.vue` com as seções do cockpit + deep-links + preservação `lastGood` no refresh
  Depende de: 3.1

## 4. N2 — Gates integrados

- [x] 4.1 Rodar testes API da área (`php artisan test --filter=OperationsSummary` ou equivalente) e Pint no diff
  Depende de: 1.4, 3.2
- [x] 4.2 Rodar Vitest/typecheck web dos arquivos tocados e `openspec validate --specs --strict` + validate da change
  Depende de: 3.2
