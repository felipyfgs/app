## Context

O hub `/monitoring` carrega pending, findings, runs e N overviews de módulo em paralelo no browser. A UI é KPI strip + acordeões + atalhos — útil para operação, fraca para insights densos no padrão MonitorHub (2 colunas, cards, gráficos, status).

Já existem read models: `ModulePortfolioQueryService`, pending/findings, mailbox messages/alerts, `DeclarationHubQueryService::summaryByObligation`, RBT12 em detalhe PGDAS-D. Não há sublimite, catálogo SPED nem flag “excluído” em mailbox.

## Goals / Non-Goals

**Goals:**

- Endpoint agregado `GET /api/v1/fiscal/monitoring/insights` com DTO estável e fail-closed parcial.
- Dashboard `/monitoring` em grid ~8/4 com widgets de insights e UX própria (Nuxt UI / shell / Unovis).
- Copy honesta: RBT12 (não sublimite); buckets e-CAC sem Excluído; ausência sem Gerais/SPEDs; DIRF `UNSUPPORTED`.
- Manual Consult abaixo dos insights.

**Non-Goals:**

- Chrome/nav estilo MonitorHub; dark-only forçado.
- Persistência de sublimite, SPED ou DIRF.
- Mutações fiscais / consultas SERPRO live neste redesign.
- Serviços mei/mei-worker no Compose; flags SERPRO ON.

## Decisions

### 1. Endpoint agregado no backend

- **Decisão:** `MonitoringInsightsQueryService` + controller fino; uma resposta JSON com seções tipadas.
- **Por quê:** evita fan-out no browser, centraliza exclusão de origem sintética e `partial_errors`.
- **Alternativa:** compor só no front — rejeitada (latência, inconsistência, lógica duplicada).

### 2. Reuso de query services existentes

- Pending/findings: mesmos services do hub atual.
- Sitfis / obligations progress: `ModulePortfolioQueryService` (overview + submodules).
- RBT12: amostra de clients `simples_mei` / `PGDASD` com `rbt12` `PARSED`.
- Mailbox: list/metrics; buckets = Importante / Em dia / Outros.
- Declarações ausência: `DeclarationHubQueryService` summary.

### 3. UX própria, padrão densidade MonitorHub

- Grid `lg:grid-cols-12`: esquerda `col-span-8`, direita `col-span-4`.
- Cards `UPageCard variant="subtle"`; charts Unovis (`ClientOnly` + `useElementSize`).
- Sem redesenhar sidebar/`UDashboardNavbar` além de título/atualizado/refresh.

### 4. Office context e fail-closed

- Tenant via `CurrentOffice` (Sanctum office); nunca `office_id` do client.
- Origem DEMO/SIMULATED excluída de contadores produtivos (mesmo critério do hub).
- Erro total → 5xx ou payload sem inventar KPIs; parcial → seções null + `partial_errors[]`.

## Risks / Trade-offs

- **[Expectativa sublimite/SPED/Excluído]** Copy da imagem → Mitigação: títulos/tooltips honestos; testes de fidelity assertam ausência de labels falsos.
- **[Payload pesado RBT12/mailbox]** → Mitigação: limites (top N clients RBT12; amostra de mensagens); paginação futura fora desta change.
- **[Vazamento entre offices]** → Mitigação: todas as queries escopadas ao office da sessão.
- **[Bilhetagem SERPRO]** → Mitigação: endpoint somente leitura de snapshots/projeções locais; sem dispatch de runs.
- **[Conflito com declarations-obligation-tabs]** → Mitigação: ownership distinto (hub vs página Declarações); não editar artefatos da outra change.

## Mapa de dependências

- DAG: `C0` — nenhuma upstream.
- Ownership: `apps/api` Fiscal Monitoring Insights + `apps/web` `/monitoring` insights UI.
- Rollout: API + web no mesmo deploy; rollback reverte page/components e rota (clients antigos continuam sem o endpoint).
- Paralelo OK com `declarations-obligation-tabs` (sem overlap de capability).

## Migration Plan

1. Ship API + Feature tests.
2. Ship front consumindo insights; manter Manual Consult abaixo.
3. Rollback: front volta ao hub anterior; rota insights pode permanecer (sem callers).

## Open Questions

- (nenhuma bloqueante)
