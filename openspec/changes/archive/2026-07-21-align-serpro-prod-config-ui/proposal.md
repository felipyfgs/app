## Why

A Configuração SERPRO em PRODUÇÃO usa um shell paralelo (stepper + formulário próprio) em relação à Demonstração (Credenciais + Limites). Isso confunde o Proprietário e quebra a simplificação do console.

## What Changes

- Unificar `/admin/serpro/configuration`: mesmo card **Credenciais** em TRIAL e PRODUCTION.
- Em PRODUCTION: aceite (consent) no mesmo card + CTA que reutiliza `productionOnboarding.submit`; status compacto (badge/hint), sem grid de 4 steps.
- Manter **Liberações externas** e **Limites** no mesmo padrão visual.
- Remover o card “Ativação de Produção” denso com stepper/form paralelo.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `serpro-admin-console`: Configuração usa shell único TRIAL/PRODUCTION (Credenciais unificadas; Produção só adiciona consent + gates).

## Impact

- Web: `apps/web/app/pages/admin/serpro/configuration.vue` + testes de navegação/fidelity tocados.
- API/vault/onboarding: sem mudança de contrato; apenas composição UI.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: `simplify-serpro-admin-console` (capability `serpro-admin-console`, marco `apply`, relação `coordenada`)
- Capability/contrato: `serpro-admin-console` (MODIFIED)
- Marco exigido: `apply`
- Relação: `coordenada`
- Desbloqueia: apply desta change
- Paralelismo: não paralelizar com edits no mesmo `configuration.vue`

### Non-goals

- Ligar drivers SERPRO real; mudar Trial vs Production de backend
- Redesign de Visão geral / Contratos / Cobertura
- mei no Compose; flags ON
