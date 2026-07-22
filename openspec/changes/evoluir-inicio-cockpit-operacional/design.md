## Context

O Início (`apps/web/app/pages/index.vue`) já consome `GET /api/v1/operations/summary` e `GET /api/v1/operations/inbox`, além de `GET /api/v1/work/kpis`. O builder PHP (`OperationsSummaryBuilder`) devolve um payload rico (bloqueios, `platform_health` sanitizado, autorização SERPRO do office, procurações, pendências fiscais, cobertura, uso, assinatura, SVRS, backup), mas o tipo TS `OperationsSummary` e a UI ignoram a maior parte. Atendimento não entra no aggregate. O dashboard fiscal canônico permanece em `/monitoring` (`monitoring-insights-dashboard`).

Stakeholders: operadores/admins do escritório ativo (office context via Sanctum + `CurrentOffice`). Platform admin continua em `/admin`.

## Goals / Non-Goals

**Goals:**

- Cockpit em `/` com blocos acionáveis e deep-links, alimentados por dados locais do office.
- Contrato tipado alinhado ao builder + extensões mínimas (Atendimento; MEI/runs leves).
- Fail-closed: ausência/erro ≠ zero inventado; preservar `lastGood` no refresh.

**Non-Goals:**

- Redesign do shell Nuxt UI / tema.
- Duplicar widgets densos de `/monitoring` (charts RBT12, donuts SITFIS, etc.).
- Horizon, Compose healthchecks, gateway `/healthz` ou readiness multi-tenant na home (fase `/admin` futura).
- Disparar consultas SERPRO/SEFAZ live a partir do Início.
- Ligar kill switches / flags fail-closed.
- Serviços `mei`/`mei-worker` no Compose.

## Decisions

1. **Fonte primária = estender `/operations/summary`**
   Em vez de um novo endpoint agregado, enriquecer o builder existente e tipar o frontend. Motivo: a home já depende dele; um segundo aggregate aumentaria latência e duplicaria tenancy. Alternativa rejeitada: compor N fetches no cliente (pior falha parcial e cache).

2. **Fiscal no Início = slice, não insights completos**
   Usar campos já presentes no summary (`fiscal_pending`, `guides_due_7d`, `uncertain_results`, `fiscal_coverage.up_to_date_full_only`) + CTA para `/monitoring`. Não chamar `GET /fiscal/monitoring/insights` na v1 do cockpit (evita payload pesado e overlap com a página fiscal). Alternativa: embedar insights — rejeitada por duplicação UX.

3. **Atendimento = contagens DB do office**
   Agregar `communication_inboxes` por status, `communication_outbox_entries` em RETRY/DEAD, conversas OPEN/PENDING, e flags de config (`COMMUNICATION_ENABLED`, gateway, office). Sem proxy a `/healthz` do gateway (rede interna + escopo plataforma). Alternativa: listar inboxes na UI e somar no cliente — rejeitada (N+1 e permissões).

4. **MEI/runs = contagens 24h baratas e opcionais no mesmo payload**
   Se as queries forem indexáveis/`office_id`-scoped e baratas, incluir; se falharem, omitir seção com honestidade (`available: false`) sem zerar. Não criar listagens novas.

5. **UI = blocos em `components/home/` + `ShellKpiStrip`**
   Manter padrão de fetch manual (`ref` + `sessionEpoch` + `lastGood`) já usado em `index.vue` / `WorkKpisBlock`. Sem Pinia, sem redesign.

6. **Autorização**
   Mesmo gate de `/operations/summary` atual (usuário autenticado com office context). Sem novos papéis. Campos sensíveis continuam sanitizados (sem PFX, custo global, fingerprint, outros tenants).

## Risks / Trade-offs

- **[Risco] Payload do summary cresce e fica lento** → Mitigação: só COUNT/`exists` indexados por `office_id`; sem joins pesados; Feature test de isolamento; se necessário, lazy section `available: false` sob falha parcial.
- **[Risco] Vazamento cross-office** → Mitigação: todas as queries com `where('office_id', $officeId)`; Feature test com dois offices.
- **[Risco] Segredos/custo SERPRO no JSON** → Mitigação: manter allowlist atual de `tenantScopedHealth` / usage (sem micros de custo interno, sem vault ids).
- **[Risco] Overlap visual com `/monitoring`** → Mitigação: labels “resumo” + deep-link; sem charts.
- **[Risco] Contrato de comunicação ainda em change não arquivada** → Mitigação: ler modelos/enums já no código; não depender de archive; KPIs só de colunas estáveis (`status`, outbox status).

## Migration Plan

1. Deploy API com campos novos no summary (backward-compatible: só adições).
2. Deploy web tipado + novos blocos.
3. Rollback: reverter web primeiro (ignora campos extras); API aditiva não quebra clientes antigos.

## Open Questions

- (nenhuma bloqueante) Contagens MEI/runs entram na mesma PR se baratas; senão ficam `available: false` documentado na spec.
