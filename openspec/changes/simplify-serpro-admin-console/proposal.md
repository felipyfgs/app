## Why

O console Admin SERPRO concentra ~4k linhas com duas camadas de abas (Operação/Integração/Canário + Status/Consumo/Liberação e Acesso/Contratos/Cobertura). O fluxo diário do Proprietário precisa só de visão operacional e configuração de credenciais/limites; o restante é ops/auditoria que polui a gestão.

## What Changes

- Shell do console: apenas **Visão geral** e **Configuração** em `SERPRO_NAV_ITEMS` (Canário fora do menu).
- Visão geral (`/admin/serpro`): só status (ambiente, prontidão, kill switch, contrato ativo); sem tabs locais; links secundários para consumo/liberação/canário.
- Configuração (`/admin/serpro/configuration`): só acesso essencial (credenciais, onboarding PRODUÇÃO, limites); sem tabs locais; sem pending offices nem histórico longo; links secundários para contratos/catálogo.
- Deep-links (`/usage`, `/rollout`, `/contracts`, `/catalog`, `/dte-canary`) permanecem.

## Capabilities

### New Capabilities

- `serpro-admin-console`: contrato das duas superfícies primárias do console e do que fica fora do fluxo diário (deep-links).

### Modified Capabilities

- `platform-admin-navigation`: catálogo do shell SERPRO com 2 itens (Visão geral + Configuração); Canário fora da navegação contextual.

## Impact

- Web: `serpro-navigation.ts`, `pages/admin/serpro/index.vue`, `pages/admin/serpro/configuration.vue`, testes de navegação/tabs.
- API / vault / kill switch / flags: sem mudança de contrato.
- Arquivos `usage`/`rollout`/`contracts`/`catalog`/`dte-canary` preservados como deep-link.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: `unify-serpro-admin-nav` (capability `platform-admin-navigation`, marco `apply`, relação `bloqueante`)
- Capability/contrato: `platform-admin-navigation` (MODIFIED) + `serpro-admin-console` (NEW)
- Desbloqueia: nenhuma
- Paralelismo: não paralelizar com changes que editem o console SERPRO

### Non-goals

- Apagar rotas deep-link ou APIs
- Ligar flags SERPRO/MEI/SEFAZ; mudar bilhetagem/vault
- Serviços `mei`/`mei-worker` no Compose
- Split físico obrigatório de `configuration.vue` em múltiplos arquivos
- Targets Make de backup/restore/ops indisponíveis
