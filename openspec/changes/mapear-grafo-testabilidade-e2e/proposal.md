## Why

O inventário atual prova a existência de 445 rotas API e 94 páginas Nuxt, mas não registra como os casos de uso atravessam página, cliente HTTP, endpoint, controller e testes. Com apenas duas specs Playwright concentradas em monitoramento, hoje não é possível identificar automaticamente jornadas sem cobertura ponta a ponta nem impedir que o grafo de testabilidade fique obsoleto.

## What Changes

- Evoluir o inventário de superfície para um grafo canônico e versionado de casos de uso, com atores, jornadas, páginas, endpoints, handlers, integrações externas e evidências de teste.
- Gerar o grafo a partir das fontes reais do Laravel/Nuxt e validar arestas órfãs, rotas/páginas ausentes e divergências de cobertura em gates automatizados.
- Publicar uma matriz legível de testabilidade por jornada e nível (`L0` inventário, `L1` contrato HTTP, `L2` domínio/behavioral, `L3` navegador), explicitando lacunas em vez de inferir cobertura pela quantidade de arquivos.
- Ampliar o harness Playwright existente para jornadas críticas representativas de autenticação/tenant, clientes, trabalho e monitoramento, sempre com seed determinístico e bloqueio de egress externo.
- Manter Playwright local e fora do gate CI; os gates determinísticos do grafo, PHPUnit e Vitest permanecem no CI.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `surface-test-coverage`: passa a exigir rastreabilidade executável de casos de uso ponta a ponta, matriz de lacunas e jornadas críticas de navegador além do inventário plano de rotas/páginas.

## Impact

- API: fixtures/gates em `apps/api/tests/**`, seed E2E e testes Feature de contrato quando uma aresta crítica ainda não tiver evidência.
- Web: gerador/gate do grafo, testes Vitest e specs em `apps/web/tests/e2e/**`; sem redesign do painel.
- OpenSpec: delta de `surface-test-coverage` e artefato de levantamento da change.
- Infra/segurança: reuso de Docker Compose, `APP_ENV=testing`, kill switches fail-closed e bloqueio de hosts externos; nenhum serviço novo.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `surface-test-coverage`, `deep-fiscal-unit-coverage` e `simples-nacional-portfolio-e2e` em main specs
- Depende de: nenhuma
- Capability/contrato: `surface-test-coverage`
- Marco exigido: n/a
- Relação: coordenada com `sitfis-historico-busca`, `corrigir-classificacao-pdf-sitfis` e `alinhar-historico-pgdasd-portal-simples`, sem dependência bloqueante
- Desbloqueia: identificação objetiva de lacunas e expansão incremental de E2E por jornada
- Paralelismo: pode avançar em fixtures/scripts/testes próprios; deve preservar testes e superfícies alterados pelas changes coordenadas

### Non-goals

- Uma execução Playwright para cada uma das 445 rotas ou 94 páginas
- Live SERPRO/Integra, parecer jurídico, novas mutações fiscais, flags/canais SEFAZ ligados ou sidecar `mei` no Compose
- Colocar Playwright no gate CI ou criar targets Make de backup/restore/ops indisponíveis
- Redesenhar o shell Nuxt ou alterar comportamento de produto sem bug comprovado por teste
