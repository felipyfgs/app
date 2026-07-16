## 1. Baseline, escopo e gates de segurança

- [x] 1.1 Revisar o status e os deltas da change concluída `build-complete-fiscal-monitoring-hub`, registrar sobreposições e definir a ordem segura de sync/archive antes desta change.
- [x] 1.2 Congelar em testes de contrato os envelopes e nomes de campos atualmente retornados pelos endpoints fiscais consumidos pelas rotas `/monitoring`.
- [x] 1.3 Registrar na matriz de fidelidade cada rota de Monitoramento, o arquivo exato do template fixado e a justificativa das adaptações funcionais.
- [x] 1.4 Remover a declaração duplicada das rotas de Guias sem alterar URLs públicas, middleware, policies ou comportamento autorizado.
- [x] 1.5 Confirmar por teste que nenhuma API nova aceita `office_id` do request como fonte do tenant e que `PLATFORM_ADMIN` não herda leitura fiscal.
- [x] 1.6 Definir os identificadores tipados de módulo, submódulo, situação, cobertura e origem de dados compartilhados pelo read model e pelo frontend.

## 2. Read model tenant-scoped da carteira fiscal

- [x] 2.1 Criar DTOs e Resources PHP tipados para `FiscalModuleOverview`, contadores, agenda, categorias e métricas opcionais do módulo.
- [x] 2.2 Criar DTOs e Resources PHP discriminados para as linhas de clientes de cada `module_key`, sem payload genérico ou campos sensíveis.
- [x] 2.3 Implementar o query service comum que resolve office pela membership, aplica módulo, busca, situação, competência, submódulo e ordenação.
- [x] 2.4 Implementar agregações SQL do overview sobre o mesmo escopo normalizado da carteira, independentemente da página solicitada.
- [x] 2.5 Expor `GET /api/v1/fiscal/modules/{module}/overview` com validação de módulo, feature flags, autorização e proveniência sanitizada.
- [x] 2.6 Expor `GET /api/v1/fiscal/modules/{module}/clients` com paginação server-side, identidade sanitizada, CNPJ mascarado e bloco discriminado por módulo.
- [x] 2.7 Adicionar suporte aos filtros especializados declarados por módulo sem aplicar filtragem somente sobre a página já retornada.
- [x] 2.8 Reaproveitar os endpoints específicos existentes para detalhes, evidências, mensagens, guias, parcelas, eventos e ações, evitando duplicar regras fiscais no agregador.
- [x] 2.9 Cobrir overview e carteira com testes de contrato, paginação, busca por CNPJ alfanumérico, filtros, ordenação, escopo e contagens coerentes.
- [x] 2.10 Cobrir os novos endpoints com teste de isolamento entre offices, manipulação de `office_id`, papéis e quantidade controlada de consultas para evitar N+1.

## 3. Infraestrutura das fixtures demonstrativas

- [x] 3.1 Criar manifesto versionado da fixture com data-âncora `DEMO_FISCAL_ANCHOR_AT`, chaves lógicas estáveis e entre 16 e 20 clientes integralmente sintéticos.
- [x] 3.2 Implementar `FiscalMonitoringDemoSeeder` com guard anterior à mutação para ambiente `local/testing` e slug de office demo configurado.
- [x] 3.3 Implementar reset transacional e idempotente limitado aos registros identificados da fixture no office demo, preservando qualquer outro tenant.
- [x] 3.4 Criar categorias, vínculos de clientes, agendas, competências, execuções, snapshots, findings e pendências coerentes para a carteira demo.
- [x] 3.5 Incluir cenários `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED`.
- [x] 3.6 Criar office/identidade sentinela de testes com CNPJ repetido para provar isolamento sem exibi-lo na carteira demo principal.
- [x] 3.7 Implementar o resolver de proveniência `DEMO`/`SIMULATED`/`LIVE` com guard de ambiente e metadados sanitizados nas APIs.
- [x] 3.8 Criar utilitário de conteúdo demonstrativo inofensivo com a marca “DEMONSTRAÇÃO — SEM VALIDADE FISCAL” para evidências e downloads.
- [x] 3.9 Persistir corpos, anexos, relatórios e arquivos demonstrativos necessários exclusivamente via `SecureObjectStore`, sem expor identificador de cofre.
- [x] 3.10 Cobrir guard, transação, idempotência, troca de versão, relógio determinístico, isolamento e ausência de material sensível em testes backend.

## 4. Cenários demonstrativos por módulo

- [x] 4.1 Popular Simples/MEI com regimes, PGDAS-D, PGMEI, DASN-SIMEI, DEFIS, competências, aplicabilidade e guias simuladas coerentes.
- [x] 4.2 Popular DCTFWeb/MIT com apuração, encerramento, transmissão, recibos, evidência sanitizada, DARF e pagamento em estados independentes.
- [x] 4.3 Popular Parcelamentos com catálogo de modalidades, pedidos, saldo, parcelas pagas/abertas/atrasadas, próxima parcela e guias relacionadas.
- [x] 4.4 Popular SITFIS com snapshots vigentes e expirados, TTL, protocolos, execuções, achados normalizados e relatório sintético protegido.
- [x] 4.5 Popular Caixa Postal com DTEs, mensagens `NEW`, `IN_REVIEW` e `RESOLVED`, prazos, leitura oficial, corpo e anexos protegidos.
- [x] 4.6 Popular Declarações com obrigações aplicáveis/não aplicáveis, competências, vencimentos, entregas, atrasos e evidências sintéticas.
- [x] 4.7 Popular Guias com sistema/tipo, competência, `amount_cents`, emissão, validade, pagamento, versões e download protegido.
- [x] 4.8 Popular FGTS/eSocial com eventos, fechamento, totalização e divergências, mantendo guia e pagamento como `UNSUPPORTED` quando sem fonte M2M.
- [x] 4.9 Popular consumo/ledger do office demo e agregados coerentes com os períodos exibidos, sem criar credencial ou contrato SERPRO sintético.
- [x] 4.10 Criar o cenário documental de cinco falhas consecutivas de decode com cursor `BLOCKED`, NSU preservado e nenhum salto silencioso.
- [x] 4.11 Adicionar testes de coerência que naveguem de cada linha demo ao cliente, execução, mensagem, declaração, guia, parcela ou competência correspondente.

## 5. Perfil local demonstrativo somente leitura

- [x] 5.1 Encadear o seeder fiscal após o catálogo demo em `DatabaseSeeder` somente sob o guard explícito de ambiente e tenant.
- [x] 5.2 Documentar e configurar o perfil local que habilita hub e módulos de leitura, mantendo scheduler e integrações externas desligados.
- [x] 5.3 Manter transmissões, emissões externas, adesões e demais mutações fiscais bloqueadas no perfil demo mesmo com clients fake registrados.
- [x] 5.4 Fazer o preflight real identificar modo demonstração/somente leitura e retornar bloqueio explícito sem registrar sucesso fiscal fictício.
- [x] 5.5 Permitir apenas ações internas autorizadas sobre fixtures, como filtros, associação de categoria, triagem e navegação.
- [x] 5.6 Adicionar comando documentado para recriar o dataset demo de forma idempotente e imprimir apenas contagens sanitizadas.
- [x] 5.7 Provar em teste/configuração que variáveis demo não habilitam seeder, origem sintética, mocks ou fallback em produção.

## 6. Fundação compartilhada do frontend Nuxt UI

- [x] 6.1 Criar interfaces TypeScript discriminadas para overview, linhas por módulo e detalhes, substituindo `Record<string, unknown>` nos fluxos principais.
- [x] 6.2 Criar composable tenant-aware para overview e carteira com paginação, filtros na URL, descarte de resposta após troca de office e preservação da última resposta válida.
- [x] 6.3 Implementar `MonitoringModuleNav` com `UNavigationMenu highlight` em `UDashboardToolbar`, estado ativo e comportamento móvel sem overflow do documento.
- [x] 6.4 Implementar `FiscalKpiStrip` derivado de `HomeStats` com Total, Em dia, Processando, Pendências e Atenção acionáveis.
- [x] 6.5 Implementar `FiscalClientPicker` com busca server-side por razão social, nome fantasia ou CNPJ, sem exigir ID numérico.
- [x] 6.6 Implementar `FiscalClientCell`, `FiscalCoverageBadge` e `FiscalDataOriginBadge` com texto semântico, CNPJ mascarado e origem demo visível.
- [x] 6.7 Evoluir `FiscalModuleTable` a partir do arquétipo Customers, preservando `DASHBOARD_TABLE_UI`, slots tipados, ações de linha e paginação server-side.
- [x] 6.8 Implementar toolbar compartilhada de busca, situação, competência, submódulo, filtros especializados e exportação com estado reproduzível na URL.
- [x] 6.9 Implementar skeleton, atualização com dados anteriores, vazio, erro com retry, `UNSUPPORTED`, `BLOCKED` e `FiscalTableEmptyState` distintos.
- [x] 6.10 Implementar banner persistente “Dados demonstrativos” quando a API indicar origem sintética, sem fallback visual para respostas produtivas vazias ou com erro.
- [x] 6.11 Adicionar testes de componentes para navegação, KPIs acionáveis, filtros, paginação, badges, estados e troca de office.

## 7. Implementação das rotas de Monitoramento

- [x] 7.1 Refatorar `/monitoring` pelo arquétipo Home com KPIs gerais, cobertura por módulo, carteira em atenção, últimas execuções e atalhos funcionais.
- [x] 7.2 Refatorar `/monitoring/simples-mei` com tabs PGDAS-D, PGMEI, DASN-SIMEI e Regime, contratos dedicados, competência, obrigação e guia por cliente.
- [x] 7.3 Refatorar `/monitoring/dctfweb` com tabs DCTFWeb/MIT e estados separados de encerramento, transmissão, recibo, evidência, DARF e pagamento.
- [x] 7.4 Refatorar `/monitoring/installments` com modalidades, pedido, saldo, parcelas, próxima parcela, atrasos, guia e detalhe navegável.
- [x] 7.5 Refatorar `/monitoring/sitfis` com carteira, idade/TTL e achados, além de slideover acessível de pendências normalizadas sem JSON bruto.
- [x] 7.6 Reestruturar `/monitoring/mailbox` pelo arquétipo Inbox com lista e detalhe adjacentes no desktop e overlay abaixo de `lg`.
- [x] 7.7 Corrigir a estrutura de rota de `/monitoring/mailbox/[id]` para renderizar o detalhe canônico, alinhar campos e suportar corpo/anexos protegidos.
- [x] 7.8 Refatorar `/monitoring/declarations` para renderizar o resumo real, obrigação, aplicabilidade, competência, vencimento, entrega e evidência.
- [x] 7.9 Refatorar `/monitoring/guides` com `amount_cents`, estados independentes, versão, detalhe, download efêmero e identificação de demonstração.
- [x] 7.10 Refatorar `/monitoring/fgts` com fechamento, totalização, eventos, divergências e aviso permanente de cobertura parcial/`UNSUPPORTED`.
- [x] 7.11 Refatorar `/monitoring/clients/[clientId]` pelo arquétipo Settings com seções lazy de resumo, execuções, findings, pendências, parcelamentos, declarações, guias, FGTS e SITFIS.
- [x] 7.12 Corrigir todos os deep-links de carteiras para tabs existentes, preservar filtros compatíveis e apresentar falhas parciais com retry em vez de listas vazias.
- [x] 7.13 Remover arrays de exemplo, fallbacks genéricos, filtros pós-paginação e controles decorativos remanescentes das páginas de Monitoramento.

## 8. Ações, permissões e exportações

- [x] 8.1 Conectar “Adicionar cliente” ao fluxo existente e “Associar clientes/categorias” aos endpoints reais, com refresh das carteiras afetadas.
- [x] 8.2 Conectar atualização de leitura ao job real permitido, exibindo estado enfileirado e resultado sem disparar integração externa no modo demo.
- [x] 8.3 Implementar exportação assíncrona por módulo e filtro com escopo do office, campos sanitizados, proveniência e marcação de demonstração.
- [x] 8.4 Conectar triagem interna da Caixa Postal exclusivamente aos valores `NEW`, `IN_REVIEW` e `RESOLVED`, sem alterar ciência oficial.
- [x] 8.5 Aplicar policies e feature flags na renderização e execução de ações para `ADMIN`, `OPERATOR` e `VIEWER`.
- [x] 8.6 Garantir que ações fiscais de alto risco usem códigos oficiais do catálogo, 2FA/preflight e bloqueio demo, sem valores fallback inventados.
- [x] 8.7 Testar ações permitidas, proibidas, bloqueadas, assíncronas e inexistentes para evitar qualquer sucesso apenas visual.

## 9. Testes funcionais, responsivos e visuais

- [x] 9.1 Expandir `frontend/tests/e2e/support/api-fixtures.ts` com contratos fiscais determinísticos e sanitizados para todas as rotas e detalhes.
- [x] 9.2 Criar testes de integração frontend que falhem diante de campo incompatível, envelope incorreto ou registro de outro módulo.
- [x] 9.3 Cobrir todas as rotas preenchidas em Playwright, incluindo filtros, paginação, tabs, deep-links, detalhes e ações autorizadas.
- [x] 9.4 Cobrir loading inicial, atualização, vazio, erro, `UNSUPPORTED`, `BLOCKED` e origem demo por rota relevante.
- [x] 9.5 Cobrir Caixa Postal mestre–detalhe, fechamento por teclado, retorno de foco e preservação da lista em desktop/mobile.
- [x] 9.6 Cobrir troca de office durante request, descarte da resposta anterior e ausência de dado do tenant anterior no DOM.
- [x] 9.7 Gerar e aprovar baselines visuais por zonas em `1440×900` e `390×844` para todas as carteiras, detalhes e overlays críticos.
- [x] 9.8 Executar a matriz em largura mínima de 360 px e eliminar overflow horizontal do documento sem esconder ações essenciais.
- [x] 9.9 Executar varredura de screenshots, traces, relatórios, exports e downloads contra PFX, PEM, segredos, tokens, cookies, XML real e IDs de cofre.

## 10. Aceite e preparação para entrega

- [x] 10.1 Recriar o banco local duas vezes com o seeder demo e conferir contagens, relações, KPIs, idempotência e ausência de alterações no tenant sentinela.
- [x] 10.2 Executar a suíte backend fiscal direcionada e completa, corrigindo regressões de tenancy, resources, policies, filas e SecureObjectStore.
- [x] 10.3 Executar lint, typecheck, testes unitários/de componente e build de produção do frontend sem incluir dataset ou rota mock no bundle.
- [x] 10.4 Executar toda a matriz Playwright desktop/mobile e revisar evidências de cada rota, estado e papel.
- [x] 10.5 Revisar a matriz de fidelidade e o checklist do template, garantindo `UDashboardPanel`, navbar, toolbar, tabela, estados e responsividade canônicos.
- [x] 10.6 Verificar manualmente que produção vazia continua vazia, produção com erro mostra erro e nenhum fallback demo pode ser ativado fora de `local/testing`.
- [x] 10.7 Atualizar documentação operacional do ambiente demo, origem dos dados, limitações do FGTS e procedimentos de reset/diagnóstico.
- [x] 10.8 Executar `openspec validate complete-monitoring-visual-fixtures --json` e resolver todos os erros antes de solicitar sync/archive.
