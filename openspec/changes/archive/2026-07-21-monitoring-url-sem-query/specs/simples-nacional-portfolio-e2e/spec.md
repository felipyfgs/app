## MODIFIED Requirements

### Requirement: Testes web da Portfolio e E2E do caminho crítico
Os testes web SHALL cobrir a carteira em `/monitoring/simples` (e redirect legado `/monitoring/simples-mei`): carga inicial (overview+rows), filtros/sort/paginação em estado local com URL Nuxt path-only (sem query), seleção e consulta linha/bulk com tracking de pending/skeleton, associate/exclude, e visibilidade de ações mutáveis apenas para quem pode (`canManageClients` / `canTriggerSync`). Playwright E2E versionado MUST percorrer o caminho crítico com seed local (operador vê clientes SN, MEI não aparece, viewer sem controles de sync/consulta) e bloquear hosts externos. Playwright MUST NOT fazer parte do gate CI Frontend.

#### Scenario: Behavioral da Portfolio cobre fluxos mutáveis e read-only
- **WHEN** a suíte Vitest/Nuxt da carteira roda
- **THEN** há asserts de carga, filtros em estado local com URL limpa, consulta com pending, membership e diferença de permissões viewer vs operador

#### Scenario: E2E operador e viewer na rota canônica
- **WHEN** o spec Playwright da carteira Simples Nacional é executado localmente com seed E2E
- **THEN** o operador acessa `/monitoring/simples` (ou o redirect a partir de `/monitoring/simples-mei`) e interage com a carteira SN sem erro 500; o viewer não vê controles de enqueue/consulta manual; hosts externos permanecem bloqueados

#### Scenario: Playwright fora do gate CI
- **WHEN** o workflow CI Frontend é avaliado
- **THEN** o job de gate MUST NOT exigir Playwright; a cobertura E2E permanece executável via script local do app web
