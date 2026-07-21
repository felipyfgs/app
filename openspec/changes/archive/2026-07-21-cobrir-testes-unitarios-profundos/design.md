## Context

Pós-`cobrir-testes-unitarios-criticos` (L0–L3 superfície): inventário ~445 rotas / 94 páginas, smokes por cluster, kill switch/limites/elegibilidade fina, utils painel, Nuxt âncora.

Ainda fracos (~4% file-level em SimplesMei): `SimplesMeiAdapter`, mappers, PGMEI post-consult/projector, `FiscalMutationPolicy`/`FiscalMutationService`, Vault crypto. Jornada observada: credencial plataforma ok + teto + consult cliente → `AUTHORIZATION_MISSING` (auth escritório DRAFT) — falta Feature do caminho feliz com fakes.

Stakeholders: engenharia fiscal/MEI; CI Backend.

## Goals / Non-Goals

**Goals:**

- Unit: `SimplesMeiAdapter` (operações PGMEI/PGDASD representativas com stubs).
- Unit: `PgmeiPostConsultService` / `PgmeiDebtProjector` (ou paths equivalentes).
- Unit: `FiscalMutationPolicy` fail-closed (2FA/kill/budget/procuração stubs).
- Feature: enqueue/execute consult PGMEI MONITOR com Integra/SERPRO fake após auth “TokenActive” (ou status que passe o gate de autorização).
- Unit: `EnvelopeCrypto` (round-trip + chave inválida fail-closed).
- Evidência filtrada verde + openspec validate.

**Non-Goals:**

- Playwright CI; Sitfis/Eventos profundos; live SERPRO; mei Compose; L0 inventário novo.

## Decisions

1. **Foco A+C, Vault incluso**  
   Domínio fiscal + jornada consult + crypto. Sitfis/Playwright → P3.

2. **Fakes, não live**  
   `Http::fake` / `FixtureIntegraContadorClient` / stubs de `FiscalSourceAdapter` conforme padrões existentes (`MeiPortalFiscalMutationTransportTest`, Integra unit).

3. **Auth “pronta” no Feature**  
   Seed `OfficeSerproAuthorization` em status utilizável + contrato TRIAL ativo + limites positivos se o gate exigir — espelhar o mínimo para não cair em `AUTHORIZATION_MISSING` por DRAFT.

4. **Adapter via stubs**  
   Não reexecutar SERPRO; injetar resultado de adapter/mapper com payloads oficiais mínimos (inline ou fixture resources/serpro).

5. **Dependência coordenada**  
   Apply após verify da change crítica; archive crítico recomendado para reduzir clutter OpenSpec.

## Risks / Trade-offs

- [Eligibility matrix profunda demais] → Feature só PGMEI MONITOR; unit policy isolada.
- [FiscalMutationService 700+ LOC] → nesta change só **Policy**; Service completo = P3 se necessário.
- [Vault precisa keyring de teste] → usar env/test doubles já usados em outros testes se existirem; senão skip documentado + spike task.

## Migration Plan

1. Archive (recomendado) `cobrir-testes-unitarios-criticos`.
2. Apply profundos → verify API filtrada.
3. P3 futura: Sitfis/Eventos, MutationService, Playwright seletivo nightly.

## Open Questions

- Nenhuma bloqueante; se Vault sem harness estável no apply, mover EnvelopeCrypto para task opcional marcada no tasks.
