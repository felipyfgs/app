## Why

O Início (`/`) é o ponto de entrada do escritório, mas só surface uma fatia mínima do resumo operacional: a API já agrega bloqueios, saúde SERPRO do tenant, pendências fiscais, uso/franquia e SVRS, enquanto Atendimento/WhatsApp nem entra no summary. O operador não tem um cockpit único com estados reais para gerenciar o escritório sem saltar entre `/monitoring`, `/health`, `/work` e `/communication`.

## What Changes

- Evoluir `/` (Início) para cockpit operacional do **escritório ativo**, com blocos tipados: Bloqueios/saúde, Operações, Trabalho, Fiscal (resumo), SERPRO do office, Atendimento e Atenção.
- Alinhar o contrato tipado de `GET /api/v1/operations/summary` ao payload real de `OperationsSummaryBuilder` (campos hoje omitidos no frontend).
- Estender o summary com rollup mínimo de Atendimento (inboxes por status, outbox RETRY/DEAD, conversas abertas/pendentes, flags de comunicação/gateway) e, se barato, contagens leves de MEI attempts e runs fiscais 24h.
- Deep-links canônicos para `/monitoring`, `/work`, `/communication`, `/health`, `/syncs`, `/conta/consumo` — sem duplicar a profundidade fiscal de `/monitoring` nem o console de plataforma `/admin`.
- Testes Feature/Unit (API) e Vitest (web) cobrindo contrato, isolamento de tenant e mapeamento fail-closed dos blocos.

## Capabilities

### New Capabilities

- `operations-home-dashboard`: contrato do cockpit Início — seções da UI, payload tipado de `/operations/summary` (incl. extensões de Atendimento/MEI/runs leves), regras fail-closed e limites vs `/monitoring` e `/admin`.

### Modified Capabilities

- (nenhuma — `monitoring-insights-dashboard` permanece o dashboard fiscal canônico; esta change não altera seus requisitos.)

## Impact

- **API:** `OperationsSummaryBuilder`, possivelmente collectors em `OperationsInboxBuilder`; Feature tests de `/api/v1/operations/summary`.
- **Web:** `apps/web/app/pages/index.vue`, `components/home/*`, `types/api.ts` (`OperationsSummary`), composable `createOperationsApi`.
- **Fora de escopo (Non-goals):** redesign do shell; fundir `/` com `/monitoring`; Horizon/Compose/readiness multi-tenant na home; SERPRO live / consultas externas no Início; flags fail-closed ON; serviços `mei`/`mei-worker` no Compose; ops backup/restore indisponíveis; dashboard cross-office (exceto `/admin` futuro).

### Dependências entre changes

- **Nível:** `C0`
- **Bases estáveis:** main specs (`monitoring-insights-dashboard`); implementação existente de Trabalho (`/work/kpis`) e Atendimento (código em changes de comunicação ainda não arquivadas — contrato de domínio já no código).
- **Depende de:** nenhuma
- **Capability/contrato:** `operations-home-dashboard`
- **Marco exigido:** n/a
- **Relação:** n/a
- **Desbloqueia:** eventual change futura de ops de plataforma (readiness/Horizon/gateway em `/admin`)
- **Paralelismo:** pode rodar em paralelo a changes de carteiras fiscais desde que não alterem o contrato de `/operations/summary` de forma conflitante; coordenar mentalmente com deltas de `communication-inbox` se estenderem modelos de outbox/inbox.
