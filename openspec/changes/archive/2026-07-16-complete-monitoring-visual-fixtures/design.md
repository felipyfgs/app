## Context

O change anterior criou o núcleo fiscal, as APIs e as rotas Nuxt, mas o ambiente local atual possui apenas um escritório, três clientes e catálogo básico: há zero vínculos fiscais, execuções, snapshots ou registros dos oito módulos. As flags `FEATURES_GLOBAL_ENABLED` e `FISCAL_MONITORING_ENABLED` também estão desligadas. Assim, as páginas chegam corretamente ao estado vazio e parecem stubs, embora existam 71 rotas fiscais e persistência tenant-scoped já coberta por testes.

A auditoria encontrou também divergências de contrato que dados reais tornariam visíveis:

| Superfície | Estado atual que precisa ser corrigido |
|---|---|
| Dashboard | quatro KPIs parciais; sem carteira, categorias, agenda e execuções por módulo |
| Simples/MEI | consulta snapshots genéricos e pode exibir outro módulo; ignora regimes, competências e guias próprios |
| DCTFWeb/MIT | filtra após paginação e usa códigos de mutação fallback diferentes do catálogo |
| Parcelamentos | lista somente o pedido; ignora modalidades, parcelas e guias; deep-link aponta para aba inexistente |
| SITFIS | lê uma resposta aninhada como se fosse plana e termina em JSON bruto |
| Caixa Postal | campos e enum de triagem não correspondem à API; a rota de detalhe não é renderizada pelo pai |
| Declarações | nomes de campos e filtros divergem da API; o resumo é carregado e nunca exibido |
| Guias | usa `amount`/`status`, enquanto a API retorna `amount_cents`/`payment_status`; faltam detalhe e download protegido |
| FGTS/eSocial | é a superfície mais completa, mas não usa detalhe/eventos e precisa manter cobertura parcial explícita |
| Detalhe do cliente | carrega tudo a cada aba, silencia falhas secundárias e recebe deep-links para seções sem conteúdo |

O produto continua sendo SaaS multi-escritório para escritórios contábeis. O plano de controle global do contrato SERPRO, credenciais e faturamento não recebe dados de demonstração. As fixtures desta change pertencem exclusivamente ao plano de dados do tenant `demo`, sempre com `office_id`, e não alteram cursores ADN/SEFAZ nem material criptográfico.

## Goals / Non-Goals

**Goals:**

- Oferecer uma carteira fiscal visualmente completa e coerente em todas as rotas `/monitoring`.
- Fazer o frontend consumir contratos REST tipados e compatíveis com os recursos realmente retornados.
- Popular o tenant demo com cenários fiscais determinísticos, coerentes entre KPIs, listas e detalhes.
- Reutilizar as APIs, policies, models e filas reais, mantendo fontes externas fake apenas como dependências locais controladas.
- Derivar a forma das telas dos arquétipos Home, Customers, Inbox e Settings do template fixado, usando as capturas fornecidas somente como direção de densidade e hierarquia.
- Garantir distinção explícita entre dado `DEMO`, dado `LIVE`, vazio, indisponível, não suportado e erro.
- Cobrir visual, responsividade, contratos, permissões e isolamento por testes reproduzíveis.

**Non-Goals:**

- Criar fallback sintético quando uma API produtiva retornar vazio ou erro.
- Ligar scheduler, chamadas externas ou mutações fiscais apenas para preencher a demonstração.
- Apresentar FGTS Digital como cobertura integral ou adicionar automação de portal.
- Armazenar PFX, senha, PEM, token, Termo assinado, XML fiscal real ou resposta remota bruta em fixture.
- Alterar o plano de controle global, o contrato SERPRO ou a separação entre `PLATFORM_ADMIN` e papéis do tenant.
- Substituir o template oficial por uma cópia visual da HubStorm.

## Decisions

### 1. Dados demonstrativos persistidos no plano de dados real

Será criado `FiscalMonitoringDemoSeeder`, chamado depois de `DemoCatalogSeeder` e autorizado somente quando `app()->environment(['local', 'testing'])` e o office alvo possuir o slug configurado `demo`. O seeder recusará execução fora dessas condições.

O dataset usará as mesmas tabelas, models, scopes `BelongsToOffice`, resources e endpoints do produto. Não haverá arrays fake nas páginas, `server/api` Nuxt, servidor mock em runtime ou regra “se vazio, mostrar exemplo”. A alternativa de mockar respostas no frontend foi rejeitada porque esconderia incompatibilidades de contrato, não exercitaria paginação/tenancy e poderia vazar para o bundle de produção.

O office demo é descartável e exclusivamente sintético. A carga será transacional e idempotente: removerá apenas registros fiscais do office demo e chaves determinísticas do conjunto de fixtures, preservando outros tenants. Registros usarão origem `DEMO_FIXTURE`, `simulated=true` em metadados disponíveis e prefixos estáveis em `correlation_id`/referências. O resolver de origem retornará `DEMO` somente em `local/testing` para o office configurado; em produção, esse estado não poderá ser produzido.

### 2. Dataset coerente, determinístico e suficiente para todos os estados

A carga usará uma data-âncora explícita e versionada, configurável por `DEMO_FISCAL_ANCHOR_AT`, e entre 16 e 20 clientes sintéticos. Uma segunda identidade/office sentinela de testes validará que consultas nunca misturam tenants, mas não aparecerá ao usuário do office demo.

O manifesto do seeder cobrirá:

| Grupo | Cenários mínimos |
|---|---|
| Núcleo | categorias, vínculos, agendas, competências, runs concluídas/processando/falhas, snapshots atuais/históricos, findings e pendências |
| Situações | `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED` |
| Simples/MEI | regimes Simples e MEI, PGDAS-D, DEFIS, PGMEI, DASN-SIMEI, competências e stubs de guia claramente simulados |
| DCTFWeb/MIT | apurações, encerramento, transmissão, recibo, evidência sanitizada, DARF e estados independentes |
| Parcelamentos | modalidades, pedidos, parcelas pagas/abertas/atrasadas e guia associada |
| SITFIS | snapshot vigente e vencido, TTL, protocolo/run, pendências normalizadas e relatório sintético seguro |
| Caixa Postal | DTE, mensagens novas/em análise/resolvidas, prazos, alertas, corpo/anexos sintéticos guardados via `SecureObjectStore` local |
| Declarações | obrigações aplicáveis/não aplicáveis, competências, vencimentos, entrega, atraso e evidência sintética |
| Guias | emissão, validade, valor em centavos, pagamento conhecido/desconhecido, versões e download sintético seguro |
| FGTS/eSocial | eventos, fechamento, totalização e divergências; guia e pagamento permanecem `UNSUPPORTED` quando não houver fonte oficial |
| Consumo | ledger atribuído ao office demo e agregados coerentes com o período exibido |

Nenhum documento sintético será aceito como evidência produtiva. Arquivos necessários ao fluxo visual serão inofensivos, conterão marca d'água/texto `DEMONSTRAÇÃO — SEM VALIDADE FISCAL` e passarão pelo `SecureObjectStore`, sem identificador de cofre exposto na API.

### 3. Perfil local de features somente leitura

O Compose/configuração local terá um perfil documentado que habilita o hub e os módulos de leitura para o office demo, mantém scheduler e chamadas externas desligados e mantém todas as mutações fiscais desligadas. Os clients `FakeIntegraContadorClient`, `FakeEsocialEventClient`, `FakeCaixaPostalClient`, `FakeGuideEmissionClient` e `FakeParcelamentoSource` não serão acionados pelo seeder.

Ações internas seguras, como filtros, associação de categoria, triagem e navegação, funcionarão normalmente. Botões de transmissão, emissão ou adesão abrirão o preflight real e terminarão em bloqueio explícito de modo demonstração/somente leitura; não será exibido sucesso fiscal fictício.

### 4. Read model de carteira por módulo

As listas de baixo nível permanecem compatíveis, mas a UI da carteira precisa de dados que hoje exigiriam várias chamadas e cálculo incorreto sobre uma única página. Serão adicionados dois contratos REST tenant-scoped e somente leitura:

- `GET /api/v1/fiscal/modules/{module}/overview`
- `GET /api/v1/fiscal/modules/{module}/clients`

`overview` retornará `module_key`, `data_origin`, cobertura/fonte, horário válido, agenda, categorias vinculadas, total de clientes e contadores `up_to_date`, `processing`, `pending`, `attention` e `error`, além de métricas opcionais do módulo. `clients` receberá `page`, `per_page`, `q`, `situation`, `competence`, `submodule`, `delivery_status` e ordenação; retornará identidade sanitizada do cliente, CNPJ mascarado, competência, situação, cobertura/origem, última consulta, próximo prazo/ação e um bloco discriminado pelo `module_key`.

Os contadores serão calculados pelo mesmo escopo de consulta da carteira, nunca a partir apenas da página recebida. `office_id` virá exclusivamente da membership ativa; qualquer `office_id` enviado pelo cliente será ignorado/rejeitado. Resources/DTOs PHP e interfaces TypeScript documentarão os campos, eliminando `Record<string, unknown>` dos fluxos principais.

A alternativa de enriquecer cada página com agregações independentes foi rejeitada por duplicar regras, produzir KPIs divergentes e aumentar o número de consultas. Os endpoints específicos de DCTFWeb, MIT, parcelas, mensagens, declarações, guias e FGTS continuarão sendo usados nos detalhes.

### 5. Composição Nuxt UI compartilhada e derivada do template

A navegação do módulo será um `UNavigationMenu highlight` dentro de `UDashboardToolbar`, derivado do arquétipo Settings. Cada página preservará `UDashboardPanel`, `UDashboardNavbar`, `UDashboardSidebarCollapse`, `#header` e `#body`.

A carteira comum será derivada de `pages/customers.vue`, com componentes compartilhados:

- `MonitoringModuleNav` para Dashboard, Simples/MEI, DCTFWeb/MIT, FGTS, Parcelamentos, SITFIS, Caixas Postais, Declarações e Guias;
- `FiscalKpiStrip`, derivado de `HomeStats.vue`/`NotesInsightsBar.vue`, com Total, Em dia, Processando, Pendências e Atenção;
- `FiscalClientPicker` com busca server-side por razão social ou CNPJ, substituindo entrada manual de ID;
- `FiscalClientCell`, `FiscalCoverageBadge` e `FiscalDataOriginBadge`;
- evolução de `FiscalModuleTable` para receber slots de KPIs, submódulos, filtros especializados, ações e detalhe, mantendo `DASHBOARD_TABLE_UI` e paginação server-side;
- `FiscalTableEmptyState` e skeletons para distinguir carregamento, vazio, erro e atualização com dados anteriores.

Não será copiada a barra lateral fixa de ações das capturas. A ação primária ficará em `UDashboardNavbar #right`, filtros/subnavegação na toolbar, utilidades acima da tabela e ações de linha em menu/botão no fim da linha, conforme o template.

### 6. Arquétipo e conteúdo por rota

| Rota | Origem obrigatória | Conteúdo final |
|---|---|---|
| `/monitoring` | Home (`pages/index.vue`, `home/*`) | KPIs gerais, cobertura por módulo, carteira em atenção, últimas execuções e atalhos reais |
| `/monitoring/simples-mei` | HomeStats + Customers | tabs PGDAS-D, PGMEI, DASN-SIMEI e Regime; competência, declaração e guia por cliente |
| `/monitoring/dctfweb` | HomeStats + Customers | tabs DCTFWeb/MIT; encerramento, transmissão, recibos, pagamento e evidências em eixos separados |
| `/monitoring/fgts` | HomeStats + Customers | banner permanente de cobertura parcial, fechamento/totalização e estados `UNSUPPORTED` honestos |
| `/monitoring/installments` | HomeStats + Customers | tabs do catálogo de modalidades, pedido, total, parcelas, próxima parcela e atraso |
| `/monitoring/sitfis` | Customers + Slideover | carteira, idade/TTL, achados e detalhe normalizado; nenhum JSON bruto |
| `/monitoring/mailbox` | Inbox (`pages/inbox.vue`, `inbox/*`) | mestre–detalhe desktop, detalhe em slideover mobile, triagem, DTE e alertas |
| `/monitoring/mailbox/[id]` | `InboxMail.vue` | rota canônica do detalhe, metadados, corpo/anexos autorizados e triagem `NEW/IN_REVIEW/RESOLVED` |
| `/monitoring/declarations` | HomeStats + Customers | KPIs do resumo, obrigação, aplicabilidade, competência, vencimento, entrega e evidência |
| `/monitoring/guides` | Customers + modal | sistema/tipo, competência, valor em centavos, vencimento, emissão, pagamento, versão e download protegido |
| `/monitoring/clients/[clientId]` | Settings | resumo e seções lazy para execuções, findings, pendências, parcelamentos, declarações, guias, FGTS e SITFIS |

Submódulos locais usarão `UTabs`; destinos com URL própria usarão `UNavigationMenu`. Filtros e seção permanecerão reproduzíveis na URL. O detalhe do cliente carregará somente a aba ativa e mostrará falhas parciais em vez de convertê-las silenciosamente em lista vazia.

### 7. Caixa Postal como mestre–detalhe real

`/monitoring/mailbox/[id]` continuará sendo a rota canônica. A estrutura de arquivos/parent page será ajustada para que o detalhe seja efetivamente renderizado. Em desktop, lista e detalhe ficarão em painéis adjacentes; abaixo de `lg`, o detalhe abrirá em `USlideover`/drawer e devolverá foco ao item acionador.

Os campos serão alinhados a `subject_preview`, `received_at_official`, indicador de leitura oficial e endpoints protegidos de corpo/anexo. Triagem interna usará apenas `NEW`, `IN_REVIEW` e `RESOLVED` e nunca alterará ciência/leitura oficial.

### 8. Exportação e ações de carteira

Quando o papel permitir, cada carteira oferecerá associação de clientes/categorias, atualização enfileirada e exportação assíncrona por filtro usando o mecanismo de exports existente. A exportação carregará apenas campos sanitizados e a origem do dado; arquivos demo serão marcados como demonstração. Ações inexistentes no backend não serão desenhadas como controles decorativos.

### 9. Testes como parte da entrega visual

Os testes backend cobrirão seeder idempotente, recusa fora de `local/testing`, coerência dos agregados, filtros server-side, isolamento entre offices e ausência de segredos. Testes de contrato verificarão DTO/resource contra as interfaces usadas pelo frontend.

`frontend/tests/e2e/support/api-fixtures.ts` receberá fixtures fiscais fixas para não depender do banco nos snapshots. A matriz Playwright cobrirá todas as rotas preenchidas em `1440×900` e `390×844`, largura mínima de 360 px, detalhe/overlay, navegação por teclado, papéis e estados loading/vazio/erro. Artefatos passarão pela varredura de conteúdo sensível já existente.

## Risks / Trade-offs

- **[Fixture divergir da realidade]** → construir dados pelos models/resources reais, validar contratos e manter o manifesto versionado junto aos testes.
- **[Dado demo ser confundido com produção]** → bloqueio duplo por ambiente e office demo, `data_origin=DEMO`, selo persistente e ausência total de fallback sintético em runtime produtivo.
- **[KPIs e tabela divergirem]** → overview e carteira compartilham o mesmo query service e filtros normalizados.
- **[Seeder apagar dados locais úteis]** → limitar purga ao office `demo` e às chaves determinísticas; recusar office não configurado.
- **[N+1 e consultas caras]** → read model com joins/eager loading, agregações SQL e índices existentes; testes de quantidade/tempo representativos.
- **[Componente comum ficar genérico demais]** → manter slots discriminados e detalhes nos endpoints/componentes específicos, sem transformar todos os módulos em `Record<string, unknown>`.
- **[Referência HubStorm conflitar com template]** → template fixado vence em estrutura, slots, posições de ações e responsividade; capturas só orientam densidade e informações úteis.
- **[FGTS induzir cobertura falsa]** → banner e badges permanentes, campos sem fonte oficial em `UNSUPPORTED` e nenhum link/ação de portal.
- **[Ações demo parecerem fiscais]** → preflight bloqueado, texto de simulação e nenhuma gravação que imite sucesso externo.
- **[Change anterior completo ainda não sincronizado]** → delta desta change modifica apenas specs principais existentes e cria capability própria; o apply deverá revisar conflitos antes de sync/archive.

## Migration Plan

1. Congelar contratos atuais em testes e corrigir duplicidade de rotas de Guias sem alterar URLs públicas.
2. Adicionar DTOs/resources e os read models de overview/carteira, preservando endpoints existentes.
3. Implementar e testar o seeder fiscal e o perfil local somente leitura; validar contagens e isolamento antes de tocar a UI.
4. Criar os componentes compartilhados a partir dos arquivos exatos do template e migrar uma rota por vez: Dashboard, Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Caixa Postal, Declarações, Guias, FGTS e detalhe do cliente.
5. Adicionar fixtures E2E e baselines desktop/mobile por rota, depois executar lint, typecheck, testes backend/frontend e varredura de artefatos.
6. Produção recebe somente contratos compatíveis e componentes; seeder/perfil demo permanecem inoperantes. Rollback desabilita o perfil demo, remove o dataset do office demo e reverte os consumidores para endpoints específicos sem migração destrutiva de dados reais.

## Open Questions

- Não há questão bloqueante para implementação. Um eventual ambiente público de showroom, fora de `local/testing`, exigirá change próprio com autenticação, expiração e isolamento adicionais; não será inferido desta proposta.
