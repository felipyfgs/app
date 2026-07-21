## ADDED Requirements

### Requirement: Matriz de cobertura da carteira PGDAS-D
A suíte automatizada SHALL cobrir ponta a ponta a superfície `/monitoring/simples-mei` (submódulo PGDAS-D / Simples Nacional), garantindo que cada endpoint HTTP usado pela página e cada fluxo de UI correspondente tenham teste. A matriz mínima MUST incluir: overview, clients (filtros/sort/paginação), membership (list/include/exclude), criação e leitura de `fiscal/runs` para consulta PGDAS-D, comunicação (preview, tracking, preference, send fail-closed) e download autenticado de artefato. Testes MUST manter SERPRO/comunicação externa fail-closed (sem egress real).

#### Scenario: Inventário página↔API fechado nos testes
- **WHEN** a change é verificada
- **THEN** existe cobertura automatizada para cada endpoint da matriz mínima listada no requisito e para o fluxo de UI que o dispara

#### Scenario: Sem egress externo nos testes de consult/send
- **WHEN** um Feature ou E2E exercita consulta PGDAS-D ou envio de comunicação
- **THEN** a suíte usa fakes/bloqueio de rede e MUST NOT realizar chamada HTTP real a SERPRO ou provedor de comunicação

### Requirement: Feature HTTP da carteira e membership
Os testes Feature da API SHALL autenticar via Sanctum com contexto de escritório (`CurrentOffice`) e cobrir `GET /api/v1/fiscal/modules/simples_mei/overview` e `GET /api/v1/fiscal/modules/simples_mei/clients` com `submodule=PGDASD`: sucesso para operador/viewer com módulo habilitado; 403 com módulo desabilitado ou sem papel; isolamento de tenant (cliente de outro office ausente); rejeição de `office_id` fornecido pelo client quando aplicável; filtros (`situation`, `q`/`client_id`, `competence`, `send_status`), sort e paginação refletidos na resposta. Membership MUST ter Feature para list, include e exclude no par `simples_mei`/`PGDASD`, incluindo negação para viewer em mutações e escopo de regime (apenas Simples Nacional).

#### Scenario: Overview e clients HTTP no escopo PGDASD
- **WHEN** um operador autenticado no office correto chama overview e clients com `submodule=PGDASD`
- **THEN** a resposta inclui apenas clientes ativos de matriz da família Simples Nacional desse office e os contadores batem com essa população

#### Scenario: Módulo desabilitado ou papel ausente
- **WHEN** o módulo `simples_mei` está desabilitado para o office ou o usuário não tem papel no office
- **THEN** overview/clients retornam 403 (ou equivalente fail-closed documentado) e não vazam linhas

#### Scenario: Membership include/exclude HTTP
- **WHEN** um operador exclui e reinsere um cliente elegível via endpoints de membership PGDASD
- **THEN** o cliente some e volta na lista da carteira; um viewer MUST NOT conseguir mutar membership

### Requirement: Feature HTTP de consulta PGDAS-D e comunicação
A suíte SHALL cobrir `POST /api/v1/fiscal/runs` com payload de consulta PGDAS-D usado pela UI e `GET /api/v1/fiscal/runs/{run}` até estado terminal (ou enfileiramento verificável), sem egress SERPRO. Comunicação PGDAS-D MUST ter Feature para preview, listagem de communicações, PATCH de preferência (operador) e POST send com provider fail-closed (registro local/queue sem egress). Download autenticado de artefato MUST ter Feature de sucesso no tenant e negação cross-tenant.

#### Scenario: Consulta enfileira run sem egress
- **WHEN** um operador com `fiscal.sync.trigger` cria um run de consulta PGDAS-D para um cliente do office
- **THEN** a API aceita a criação, o job/run fica rastreável via GET, e nenhuma requisição HTTP externa é enviada

#### Scenario: Send de comunicação fail-closed
- **WHEN** o provider de comunicação está desabilitado (default) e o operador chama send
- **THEN** a API cria/atualiza o registro local de despacho conforme contrato atual e MUST NOT enviar ao provider externo

#### Scenario: Download cross-tenant negado
- **WHEN** um usuário tenta baixar artefato PGDAS-D de outro office
- **THEN** a API nega o acesso e não devolve o conteúdo do arquivo

### Requirement: Testes web da Portfolio e E2E do caminho crítico
Os testes web SHALL cobrir a carteira em `/monitoring/simples-mei`: carga inicial (overview+rows), sincronização de filtros com a URL, seleção e consulta linha/bulk com tracking de pending/skeleton, associate/exclude, e visibilidade de ações mutáveis apenas para quem pode (`canManageClients` / `canTriggerSync`). Playwright E2E versionado MUST percorrer o caminho crítico com seed local (operador vê clientes SN, MEI não aparece, viewer sem controles de sync/consulta) e bloquear hosts externos. Playwright MUST NOT fazer parte do gate CI Frontend.

#### Scenario: Behavioral da Portfolio cobre fluxos mutáveis e read-only
- **WHEN** a suíte Vitest/Nuxt da carteira roda
- **THEN** há asserts de carga, filtros/URL, consulta com pending, membership e diferença de permissões viewer vs operador

#### Scenario: E2E operador e viewer na rota canônica
- **WHEN** o spec Playwright da carteira Simples Nacional é executado localmente com seed E2E
- **THEN** o operador acessa `/monitoring/simples-mei` e interage com a carteira SN sem erro 500; o viewer não vê controles de enqueue/consulta manual; hosts externos permanecem bloqueados

#### Scenario: Playwright fora do gate CI
- **WHEN** o workflow CI Frontend é avaliado
- **THEN** o job de gate MUST NOT exigir Playwright; a cobertura E2E permanece executável via script local do app web
