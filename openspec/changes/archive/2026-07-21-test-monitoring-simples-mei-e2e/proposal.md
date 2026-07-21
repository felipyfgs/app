## Why

A rota `/monitoring/simples-mei` (carteira Simples Nacional / PGDAS-D) é o hub operacional do escritório, mas a suíte atual só cobre escopo de regime em serviço, asserts de fonte e um smoke Playwright “sem 500”. Regressões em overview/lista HTTP, filtros, consulta com polling, membership, comunicação e papéis escapam do CI. Precisamos de cobertura ponta a ponta (API Feature + web behavioral/E2E) desta superfície agora, antes de novas mudanças na carteira.

## What Changes

- Fechar o contrato de cobertura da carteira PGDAS-D em `/monitoring/simples-mei`: todo endpoint HTTP usado pela página e os fluxos de UI correspondentes passam a ter teste automatizado.
- API (`apps/api`): Feature HTTP para overview/clients (filtros, sort, paginação, tenant, módulo off, papéis), membership include/exclude/list, `POST /fiscal/runs` + poll de run PGDAS-D (sem egress SERPRO), comunicação (preview/tracking/preference/send fail-closed) e download autenticado.
- Web (`apps/web`): testes behavioral/Nuxt dos fluxos da `Portfolio.vue` (carga, filtros/URL, seleção, consulta linha/bulk + skeleton, associate/exclude, comunicação, viewer vs operador) e E2E Playwright do caminho feliz + isolamento de regime/papel — **fora do gate CI** (como hoje), mas versionado e executável localmente.
- Reconciliar asserts de fonte obsoletos (ex.: comunicação informativa vs UI real) quando bloquearem a suíte.
- Sem mudança de comportamento de produto, salvo correção mínima se o teste revelar bug bloqueante.

## Capabilities

### New Capabilities

- `simples-nacional-portfolio-e2e`: contrato de cobertura ponta a ponta da carteira Simples Nacional (PGDAS-D) em `/monitoring/simples-mei` — APIs da página, fluxos de UI e E2E do caminho crítico, com SERPRO/MEI fail-closed.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; requirements anteriores vivem só em changes ativas/arquiváveis e não alteram main specs nesta change)

## Impact

- API: novos/estendidos Feature em `apps/api/tests/Feature/**` (portfolio HTTP, runs PGDAS-D, membership, comunicação/download); helpers de seed de projeções PGDAS-D se necessário.
- Web: `apps/web/tests/unit/**` (+ mounts Nuxt se o projeto já tiver padrão); `apps/web/tests/e2e/specs/**` para a rota; possível seed E2E de estados de linha.
- CI gate: PHPUnit Feature + Vitest da área; Playwright permanece fora do gate (documentado).
- Sem novos serviços Compose, sem flags ON, sem secrets.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; referência de comportamento = changes aplicadas da carteira (`simples-mei-portfolio-regime-scope`, `desacoplar-mei-pagina-independente`, `monitoring-rail-and-portfolio-membership`, `compact-simples-mei-selection-actions`, `simples-mei-consult-row-skeleton`)
- Depende de: nenhuma
- Capability/contrato: `simples-nacional-portfolio-e2e` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: apply/verify desta change; futuras mudanças na carteira herdam a rede de segurança
- Paralelismo: coordenar com `renomear-rota-monitoring-simples` (path da página) e `pgdasd-history-period-layout` / `fix-fiscal-document-authenticated-download` (histórico/download) — relação `coordenada`, não bloqueante; não editar ownership de capabilities dessas changes

### Non-goals

- Live SERPRO, sidecar `mei` no Compose, ligar kill switches / canais SEFAZ/MEI
- Cobertura da página MEI independente (`/monitoring/mei`) — só PGDAS-D em `/monitoring/simples-mei`
- Hub de detalhe completo `/monitoring/clients/{id}/pgdasd` além do que a carteira abre (histórico/download já usados a partir da página)
- Playwright no gate CI
- Mutações fiscais novas, redesign de UI, rename de rota
- Targets Make backup/restore/ops indisponíveis
