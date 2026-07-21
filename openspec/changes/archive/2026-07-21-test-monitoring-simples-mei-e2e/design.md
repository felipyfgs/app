## Context

A página `/monitoring/simples-mei` monta `Portfolio.vue` com `submodule=PGDASD` (Simples Nacional). Fluxos acoplados:

| Camada | Superfície |
|--------|------------|
| Web | `pages/monitoring/simples-mei/index.vue` → `components/monitoring/simples-mei/Portfolio.vue` + `useFiscalModulePortfolio` / `useSimplesMeiConsultPending` / `usePgdasdMonitoring` / `SelectionActions` / membership + communication modals |
| API | `GET …/modules/simples_mei/{overview,clients}`, `POST/GET …/fiscal/runs`, membership include/exclude/list, `…/simples-mei/pgdasd/clients/{id}/…` (preview, communications, preference, send), artifact download |

Cobertura atual: `ModulePortfolioSimplesMeiSubmoduleTest` (serviço, não HTTP completo), membership/communication parciais, Vitest source-contract, Playwright só “sem 500”. Gaps: Feature HTTP da carteira, lifecycle de run PGDAS-D, behavioral da Portfolio, E2E de fluxos.

## Goals / Non-Goals

**Goals:**

- Cobertura ponta a ponta da carteira PGDAS-D: cada endpoint usado pela página tem Feature HTTP com asserts de status/payload/tenant/papel/fail-closed.
- Web behavioral (Vitest/Nuxt) cobre carga, filtros↔URL, seleção, consulta+skeleton/poll, associate/exclude, comunicação e diferença viewer/operador.
- Playwright E2E versionado: login operador → carteira com linhas SN; filtros; consulta (queue, sem egress); exclude/include; viewer sem mutações; MEI de outro regime ausente.
- Pirâmide: Feature + Vitest no gate CI; Playwright local/`test:e2e`, fora do gate.

**Non-Goals:**

- Página MEI, hub detalhe completo além do aberto pela carteira, live SERPRO, flags ON, redesign, rename de rota.

## Decisions

1. **“100% ponta a ponta” = matriz página↔API, não 1 assert por pixel**  
   Inventário fechado dos endpoints da landing + fluxos UI que os disparam. Cada célula da matriz tem teste. Detalhe histórico profundo fora da landing fica fora (Non-goals).

2. **API: Feature HTTP com Sanctum + CurrentOffice**  
   Padrão: `Office`/`User`/`Client` factories, `Sanctum::actingAs`, `CurrentOffice::clear()`, módulo `simples_mei` habilitado no seed do teste. Projeções/comunicação criadas direto nos models quando não houver factory. `Http::fake()` + `Http::assertNothingSent()`; `Queue::fake()` para send/runs. Estender suites existentes quando couber; criar `SimplesMeiPortfolioHttpTest` / `PgdasdPortfolioConsultHttpTest` se o arquivo atual for só service-level.

3. **Run PGDAS-D: enqueue + poll, sem bilhetagem**  
   `POST /api/v1/fiscal/runs` com payload da UI (system/service/operation PGDAS-D) → 201/202 + id; `GET /runs/{id}` estados; job com fakes (espelhar `ManualConsultReadPolicyTest` / `MitConsultApiTest`). Não exigir resposta SERPRO real.

4. **Web: behavioral > source-contract**  
   Preferir testes que exercitam composables/handlers com `$fetch`/api mockado; mounts Nuxt âncora da Portfolio se o harness permitir. Manter source-gates só onde já existem e estão corretos; corrigir `monitoring-communication-informational.test.ts` se contradisser a UI atual (preferência/send existem).

5. **E2E: seed `FiscalMonitoringE2ESeeder` + extensão mínima**  
   Reusar `operador@example.com` / `viewer@example.com`. Garantir ≥1 cliente SN ativo na carteira e ≥1 MEI que NÃO aparece. Bloquear hosts externos (padrão do spec atual). Não adicionar Playwright ao workflow CI.

6. **Sem alteração de produto por padrão**  
   Se Feature revelar bug (tenant leak, 500, flag aberta), correção mínima no mesmo PR; mudança de UX exige change de produto separada.

## Risks / Trade-offs

- [Escopo largo] → matriz fechada na spec; tasks ≤ ~18; histórico detalhado fora.
- [Bilhetagem SERPRO acidental] → kill switch fail-closed + `Http::fake` + assert nothing sent em todo Feature de consult/send.
- [Vazamento entre offices] → cenários explícitos de outro `office_id` e rejeição de `office_id` no query/body.
- [Flaky Playwright] → timeouts generosos já no config; asserts por `data-testid` (`simples-mei-*` / `page-navbar`); serial worker.
- [Conflito com rename de rota] → testes usam path canônico atual `/monitoring/simples-mei`; se `renomear-rota-monitoring-simples` mergear primeiro, atualizar path numa única passagem.

## Mapa de dependências

```text
C0 test-monitoring-simples-mei-e2e (esta)
  coordenada (não bloqueante):
    - renomear-rota-monitoring-simples (path)
    - pgdasd-history-period-layout (histórico)
    - fix-fiscal-document-authenticated-download (download)
```

Ownership desta change: só arquivos de teste (+ helper de seed de teste se necessário) e correção mínima se bug bloqueante. Não editar specs/tasks de outras changes.

## Migration Plan

1. Spec + tasks apply-ready.
2. Feature HTTP portfolio + membership + comunicação/download.
3. Feature consult/run PGDAS-D.
4. Vitest behavioral Portfolio + reconciliar source-gate comunicação.
5. Playwright E2E caminhos críticos.
6. Gates CI da área (pint/test API; lint/typecheck/test web).

## Open Questions

- Nenhuma bloqueante. Playwright fora do CI já decidido pelo AGENTS.md.
