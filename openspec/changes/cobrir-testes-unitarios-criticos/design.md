## Context

Inventário medido (2026-07-20), fonte `php artisan route:list` + `app/pages/**/*.vue`:

| Superfície | Total | Detalhe |
|------------|------:|---------|
| Rotas API | **444** | GET 227 · POST 177 · PATCH 29 · PUT 6 · DELETE 5 |
| Páginas Nuxt | **94** | ~27 redirect/legacy |
| Grupos API | **26** | fiscal 143, serpro 65, work 42, outbound 37, office 32, monitoring 23, … |
| Seções UI | **11** | clients 23, monitoring 16, admin/serpro 8, settings 8, … |

Testes atuais: ~51 PHPUnit + ~27 Vitest (~7 source-gates; **0** `*.nuxt.test.ts`). Ratio file-level ~8% no backend; composables ~6%.

Stakeholders: engenharia/produto; CI Backend + Frontend.

## Goals / Non-Goals

**Goals:**

- Inventário canônico versionado + gate de paridade (L0).
- Smoke por **cluster de domínio** cobrindo 100% dos grupos de rota (via agrupamento) e seções ativas de página (L1).
- Unit fail-closed + codecs (L2) e behavioral painel + Nuxt âncora (L3).
- Definição operacional de “completo e robusto”: L0–L3 verdes nesta change; P2 profundo (Vault/Sitfis/E2E) explícito como follow-on.

**Non-Goals:**

- 444 Features individuais; 94 mounts; E2E Playwright; live SERPRO/mei.

## Decisions

1. **Completo = inventário + L0–L3 por domínio, não 1:1 rota/página**  
   Robustez vem de: (a) inventário não mentir, (b) cada cluster ter smoke auth/tenant/fail-closed, (c) lógica de decisão ter unit/behavioral, (d) Nuxt project usado.

2. **Clusters API (L1 smoke)** — cada cluster → 1 suite Feature (fakes; assert 401/403/422/200 estrutural mínimo em rotas representativas):
   - `fiscal` (143)
   - `serpro` + `mei` (67)
   - `office` + `auth` + `onboarding` + `first-access` + `activations` + `tenants` (~52)
   - `clients` + `client-categories` + `cnpj` + `establishments` + `documents` + `notes` (~40)
   - `monitoring` + `platform` + `operations` + `sync-runs` + `list-filters` + `exports` + `storage` + `integrations` (~58)
   - `work` + `outbound` + `cte` (~84)

3. **Seções UI (L1/L3)**  
   - Inventário lista as 94; gate diferencia redirect vs ativa.  
   - Behavioral obrigatório: monitoring utils, serpro admin utils, client-detail/conta navigation.  
   - Mounts Nuxt: ≥2 âncoras (ex. monitoring client section shell + serpro config badge/estado ou ModuleDataTable).

4. **Inventário como artefato**  
   Commitar sob `openspec/changes/cobrir-testes-unitarios-criticos/artifacts/`:
   - `api-routes.json` (method, uri, group, action)
   - `web-pages.json` (section, file, route, notes, redirectOnly)
   - `summary.json`  
   Gate PHPUnit/Vitest compara contagens (e amostragem de URIs) com `route:list` / glob de pages.

5. **Pirâmide**  
   L2 Unit > L1 Feature smoke > L3 Nuxt mounts (poucos). Sem HTTP real externo.

6. **Follow-on (fora do verify obrigatório desta change)**  
   Vault crypto, Sitfis/Eventos profundos, FiscalMutationService completo, E2E Playwright seletivo — change `cobrir-testes-unitarios-profundos` (futura).

## Risks / Trade-offs

- [Inventário desatualiza] → gate de paridade no CI da change; regenerar no apply.
- [Smoke Feature frágil a auth] → usar Sanctum + office factory padrão dos testes existentes.
- [Escopo grande] → tasks por nível N0–N5; evidência filtrada; não bloquear em P2.
- [Falsos “cobertos”] → spec exige asserts de código/estado, não só “rota existe”.

## Migration Plan

1. Commit inventário + gates L0.
2. L2 fail-closed + L3 behavioral (risco recente).
3. L1 smokes por cluster.
4. Verify completo; archive; follow-on profundos.

## Open Questions

- Nenhuma bloqueante. Default de teto SERPRO / copy UI “Limite não configurado” permanece change de produto separada.
