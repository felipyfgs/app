## Why

O hub `/monitoring` hoje é operacional (KPI strip + acordeões + atalhos), não um dashboard de insights densos. O usuário precisa de visão at-a-glance — pendências, RBT12, e-CAC, notificações, ausência de declarações, situação fiscal e progresso por obrigação — sem fan-out de dezenas de chamadas no browser nem inventar dados que o domínio ainda não tem.

## What Changes

- Novo endpoint agregado `GET /api/v1/fiscal/monitoring/insights` com DTO de insights (KPIs, pending, RBT12, mailbox buckets, notifications, declaração ausência, sitfis counters, progresso por obrigação).
- Redesign de `/monitoring` (Dashboard) em layout 2 colunas (≈8/4) com cards de insights, gráficos Unovis e UX própria do painel (Nuxt UI / shell).
- Mapeamento honesto: RBT12 (não sublimite), buckets e-CAC sem “Excluído”, ausência sem split Gerais/SPEDs, DIRF `UNSUPPORTED`.
- Fail-closed: erro total sem KPI inventado; falha parcial com alert + empty/error por card.
- Reposicionar `ManualConsultExplorer` abaixo dos insights (fora do primeiro viewport).

## Capabilities

### New Capabilities

- `monitoring-insights-dashboard`: contrato do dashboard de insights em `/monitoring` e do endpoint agregado `monitoring/insights` (shape, fail-closed, copy honesta, widgets).

### Modified Capabilities

- (nenhuma — `openspec/specs/` não tem capability canônica de monitoring hub)

## Impact

- API: controller/service/DTO + rota em `apps/api`; Feature tests.
- Web: `pages/monitoring/index.vue`, componentes `monitoring/insights/*`, tipos + `createFiscalApi`, charts Unovis.
- Sem chrome MonitorHub; sem sublimite/SPED/DIRF novos; sem mei no Compose; SERPRO fail-closed.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs / archive (fora do DAG)
- Depende de: nenhuma
- Capability/contrato: `monitoring-insights-dashboard`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação apply desta change
- Paralelismo: coordenada com `declarations-obligation-tabs` (ownership distinto: hub vs aba Declarações); sem conflito de capability
