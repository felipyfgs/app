## Purpose

Capability `surface-test-coverage` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Inventário canônico de superfície é versionado e verificado
O sistema SHALL manter, nesta change, artefatos de inventário com o conjunto completo de rotas HTTP da API e páginas Nuxt (contagens e listagens), e SHALL falhar o gate de teste se as contagens canônicas divergirem do `route:list` / glob de `app/pages` além da tolerância definida (zero para totais; amostra de URIs obrigatória).

#### Scenario: Totais do inventário
- **WHEN** o gate de inventário é executado no ambiente de teste
- **THEN** o total de rotas API e o total de páginas coincidem com os artefatos `summary.json` (baseline: 444 rotas, 94 páginas na medição inicial, atualizáveis no apply se o código mudou)

#### Scenario: Página redirect marcada
- **WHEN** o inventário de páginas é validado
- **THEN** páginas redirect/legacy estão identificadas e não contam como superfície ativa sem cobertura L3 obrigatória

### Requirement: Cada cluster de domínio API tem smoke de contrato
O sistema SHALL possuir suites Feature (com fakes, sem SERPRO/mei live) que exercitam rotas representativas de cada cluster — fiscal; serpro+mei; office+auth+onboarding; clients+documents; monitoring+platform; work+outbound — validando autenticação/tenant/fail-closed esperado (ex.: 401 sem sessão, 403/422 com contexto inválido, ou 200 estrutural quando autenticado em leitura segura).

#### Scenario: Cluster fiscal
- **WHEN** a suite smoke do cluster fiscal roda autenticada e não autenticada
- **THEN** pelo menos uma rota GET e uma rota POST representativas respondem com status de contrato documentado no teste (não 500 por erro não tratado)

#### Scenario: Cluster serpro+mei
- **WHEN** a suite smoke serpro/mei roda
- **THEN** endpoints de configuração/plataforma ou mei automation sob teste não disparam egress real e respeitam kill switch / auth de platform quando aplicável

### Requirement: Fail-closed SERPRO/Integra/MEI permanece coberto em unitário
O sistema SHALL manter testes unitários para kill switch SERPRO, limite de quantidade não configurado (`QUANTITY_LIMIT_NOT_CONFIGURED`), elegibilidade Integra com autorização incompleta, e provider portal MEI no monitoring com falha de transporte classificada para fallback — sem rede externa.

#### Scenario: Kill switch e limite
- **WHEN** kill switch está ativo ou `global_limit_quantity` é nulo
- **THEN** os testes demonstram bloqueio fail-closed com códigos estáveis

#### Scenario: Portal inalcançável
- **WHEN** a criação do job no sidecar falha por conexão sob política portal-then-serpro
- **THEN** o outcome é elegível a fallback SERPRO (ou o fallback stub é invocado) sem `ConnectionException` crua no job

### Requirement: Painel cobre utils críticos e Nuxt deixa de ter zero mounts
O sistema SHALL cobrir behavioralmente utils de PGMEI/PGDASD/monitoring-actions e de navegação/selectors SERPRO admin, smoke do workspace de monitoramento, e SHALL incluir pelo menos dois arquivos `*.nuxt.test.ts` no projeto Vitest Nuxt.

#### Scenario: Utils monitoring/serpro
- **WHEN** entradas variam (dívida vazia/stale, ações elegíveis/inelegíveis, rotas serpro)
- **THEN** as funções retornam estados/labels/rotas assertados

#### Scenario: Âncoras Nuxt
- **WHEN** `pnpm run test` executa o projeto nuxt
- **THEN** ≥2 arquivos `*.nuxt.test.ts` passam

### Requirement: Verify da change prova L0–L3
Antes do archive, o time SHALL evidenciar: gate de inventário; smokes dos 6 clusters; unitários L2; behavioral+Nuxt L3; `openspec validate --strict` da change.

#### Scenario: Suite integrada filtrada
- **WHEN** os filtros de teste da change são executados
- **THEN** todos passam sem depender de SERPRO/mei reais
