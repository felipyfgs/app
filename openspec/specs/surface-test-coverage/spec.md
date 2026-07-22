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

### Requirement: Grafo canônico de casos de uso é completo e regenerável
O sistema SHALL manter um grafo versionado que classifique todas as rotas Laravel e páginas Nuxt atuais em casos de uso e registre atores, handlers, integrações e evidências de teste. O grafo MUST ser regenerável de forma determinística a partir do catálogo e dos inventários de superfície.

#### Scenario: Toda superfície pertence a uma jornada
- **WHEN** o gate de testabilidade compara o grafo com `route:list` e `app/pages/**/*.vue`
- **THEN** toda rota e toda página aparecem exatamente uma vez no inventário e estão ligadas a ao menos um caso de uso, sem nós ou referências órfãs

#### Scenario: Snapshots API e web são equivalentes
- **WHEN** as fixtures do grafo nas duas aplicações são verificadas
- **THEN** ambas possuem o mesmo digest, contagens e catálogo de jornadas

### Requirement: Matriz diferencia níveis reais de evidência
O sistema SHALL publicar uma matriz por caso de uso com evidências `L0` inventário, `L1` contrato HTTP/auth/tenant, `L2` domínio/behavioral e `L3` navegador. Uma evidência MUST apontar para teste existente e MUST NOT ser promovida de nível apenas por conter referência textual ao código ou à rota.

#### Scenario: Lacuna permanece explícita
- **WHEN** um caso de uso não crítico não possui evidência em algum nível
- **THEN** o relatório o marca como lacuna sem inventar cobertura e sem remover a superfície do grafo

#### Scenario: Evidência inválida falha o gate
- **WHEN** uma jornada referencia arquivo ausente, nível inválido ou âncora inexistente
- **THEN** o gate falha com a jornada e a evidência responsáveis pela divergência

### Requirement: Jornadas críticas possuem testabilidade ponta a ponta
As jornadas críticas de identidade/tenant, catálogo de clientes, trabalho operacional e monitoramento fiscal SHALL possuir evidência automatizada nos níveis L1, L2 e L3. Os testes MUST validar o contexto de ator, isolamento de escritório e permissões mutáveis aplicáveis.

#### Scenario: Tenant não vaza dados
- **WHEN** operador ou viewer alterna ou acessa um escritório no teste da jornada
- **THEN** API e UI exibem somente dados do `CurrentOffice` selecionado e negam dados cross-office

#### Scenario: Papel read-only não recebe mutação
- **WHEN** o viewer percorre uma jornada crítica no navegador
- **THEN** controles de criação, sincronização ou alteração restritos não estão disponíveis

### Requirement: E2E local é determinístico e fail-closed
O harness Playwright SHALL usar Docker Compose e seed exclusivo de `APP_ENV=testing`, SHALL bloquear hosts externos e MUST manter SERPRO, Integra, SEFAZ e providers de comunicação fail-closed. Playwright MUST permanecer fora do gate CI Frontend.

#### Scenario: Navegador não realiza egress
- **WHEN** qualquer spec Playwright da matriz crítica executa
- **THEN** requisições para host diferente de `localhost` ou `127.0.0.1` são abortadas e nenhum canal fiscal externo é acionado

#### Scenario: Gate CI continua determinístico
- **WHEN** o workflow Frontend é executado
- **THEN** Vitest valida catálogo, grafo e evidências sem iniciar navegador ou stack E2E

### Requirement: Inventários verificam conjuntos exatos
Os gates de superfície SHALL comparar o conjunto completo de chaves `método + URI` da API e de arquivos/rotas das páginas, além das contagens agregadas. Trocar uma superfície por outra sem alterar o total MUST ser detectado.

#### Scenario: Substituição com mesma contagem falha
- **WHEN** uma rota ou página é removida e outra é adicionada mantendo a contagem total
- **THEN** o gate identifica as chaves ausente e excedente e exige regeneração consciente do inventário e do grafo
