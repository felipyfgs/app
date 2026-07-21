## Context

`configuration.vue` já foi enxugada pelo simplify-serpro-admin-console, mas PRODUCTION ainda renderiza `serpro-production-onboarding` (stepper + form) enquanto TRIAL usa `serpro-config-credentials`.

## Goals / Non-Goals

**Goals:**

- Um card Credenciais para ambos os ambientes.
- PRODUCTION: consent + submit onboarding no mesmo card; status compacto.
- Gates e Limites no fluxo visual alinhado.

**Non-Goals:**

- Mudar endpoints/API de onboarding.
- Remover gates externos.
- Alterar navegação do shell SERPRO.

## Decisions

1. **Um form reativo `upload`** para PFX/Key/Secret nos dois ambientes; consent só em PRODUCTION (`consent_granted` no submit de onboarding).
2. **Submit**: TRIAL → `submitUpload` / activateTrial; PRODUCTION → `submitProductionOnboarding` mapeando os mesmos campos.
3. **Status PRODUCTION**: badge + hints em 1 faixa dentro do card Credenciais (sem `ol` 4 colunas).
4. **Gates**: permanecem após Credenciais, accordion atual.

## Risks / Trade-offs

- **[Regressão de test-id]** testes que buscam `serpro-prod-step-*` quebram → atualizar para shell unificado.
- **[Flag onboarding disabled]** manter alerta quando `productionOnboarding.enabled === false`.
