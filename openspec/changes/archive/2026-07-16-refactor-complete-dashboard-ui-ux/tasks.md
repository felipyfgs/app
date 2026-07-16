## 1. Linha de base, conflitos e gates

- [x] 1.1 Executar `openspec list/status` e registrar quais changes ativas alteram páginas, componentes, specs ou testes deste escopo antes do primeiro patch.
- [x] 1.2 Reconciliar a política conflitante de filtros em URL versus estado tabular local com as changes `standardize-dashboard-tables`, `complete-monitoring-visual-fixtures` e `add-operational-process-management`, registrando a decisão vigente por rota.
- [x] 1.3 Gerar inventário automatizado de `frontend/app/pages/**/*.vue`, confirmar os 51 arquivos iniciais e acrescentar qualquer rota criada por change concorrente.
- [x] 1.4 Criar matriz rastreável destino → rota → arquétipo → arquivo/bloco exato do template `0f30c09` → divergência autorizada → evidência.
- [x] 1.5 Executar e registrar baseline de lint, typecheck, Vitest, Playwright funcional/visual, build SPA e scanner de artefatos sensíveis.
- [x] 1.6 Preservar o worktree existente e separar por família todos os arquivos já modificados por outras implementações antes de aplicar a refatoração.
- [x] 1.7 Congelar fixtures sintéticas determinísticas para papéis `ADMIN`, `OPERATOR`, `VIEWER`, dois escritórios, estados preenchido/vazio/erro e temas claro/escuro.
- [x] 1.8 Criar checklist automatizado que rejeite PFX, senha, PEM, chave privada, token, Consumer Secret, Termo XML, XML fiscal real, cookie, `vault_object_id` e resposta externa bruta em screenshots/traces/reports.

## 2. Fundação visual e shell

- [x] 2.1 Comparar `frontend/app/app.vue`, `app.config.ts`, `assets/css/main.css` e `nuxt.config.ts` com o template e documentar somente divergências necessárias de domínio, SPA, PWA e tema.
- [x] 2.2 Consolidar tokens semânticos de cor, tipografia, radius, superfícies, contraste e densidade em `app.config.ts`/`main.css`, sem paleta paralela ou cores raw nas páginas.
- [x] 2.3 Refatorar `frontend/app/layouts/default.vue` por cópia rastreável de `template/app/layouts/default.vue`, preservando `UDashboardGroup`, sidebar, busca, dois menus e alertas globais.
- [x] 2.4 Revisar `OfficeIdentity.vue` para identidade/troca explícita apenas entre memberships válidas, reset tenant-scoped e estado recolhido acessível, sem seletor livre.
- [x] 2.5 Revisar `UserMenu.vue` para perfil, papel, tema, 2FA e logout com agrupamento/labels acessíveis e sem itens de demonstração.
- [x] 2.6 Revisar `NotificationsSlideover.vue` para estados carregando/vazio/erro/dados preservados, deep-links e ações permitidas por papel.
- [x] 2.7 Unificar sidebar, command palette, atalhos e ações rápidas a partir de `utils/navigation.ts` e das mesmas permissões tipadas.
- [x] 2.8 Implementar o pequeno componente de contexto operacional para cliente/competência/período/ambiente/origem, sem encapsular navbar, toolbar ou página inteira.
- [x] 2.9 Auditar e manter somente `DASHBOARD_TABLE_UI`, `DENSE_DASHBOARD_TABLE_UI` e `COMPACT_DASHBOARD_TABLE_UI`, verificando seus slots no tema gerado/MCP Nuxt UI.
- [x] 2.10 Padronizar convenções de loading, vazio, falha inicial, falha de refresh, 403, 409 e 422 sem criar wrapper universal de página.
- [x] 2.11 Padronizar modal, slideover, drawer, popover e tooltip conforme natureza da tarefa e testar contenção/retorno de foco.
- [x] 2.12 Implementar reset de seleção, paginação, detalhes e caches tenant-scoped ao trocar explicitamente de escritório.

## 3. Autenticação e entrada

- [x] 3.1 Refatorar `frontend/app/layouts/auth.vue` com branding responsivo, contraste, foco e densidade coerentes com Nuxt UI, sem mensagem que sugira portal de cliente final.
- [x] 3.2 Refatorar `frontend/app/pages/login.vue` com `UAuthForm`, schema, autocomplete, loading, erro acessível, redirect por papel e testes de teclado/mobile.
- [x] 3.3 Refatorar `frontend/app/pages/two-factor-challenge.vue` com `UAuthForm`, alternância OTP/recuperação, foco inicial/cíclico e preservação segura de estado.
- [x] 3.4 Refatorar `frontend/app/pages/two-factor/setup.vue` para `UForm` + schemas + `UStepper` nas etapas senha, QR/código e recuperação, com bloqueio de avanço inválido.
- [x] 3.5 Adicionar testes de componente e Playwright para login, desafio, setup, logout e retorno a rota permitida em desktop/mobile.

## 4. Home e experiência transversal do dashboard

- [x] 4.1 Refatorar `frontend/app/pages/index.vue` a partir de `template/app/pages/index.vue`, preservando navbar, ação rápida, toolbar e ordem dos blocos.
- [x] 4.2 Reorganizar `components/home/*` em áreas nomeadas de Trabalho, Monitoramento Fiscal e Operações/Infraestrutura, sem somar semânticas diferentes.
- [x] 4.3 Implementar cartões compactos e barras de progresso por departamento/responsável com contagens reais e deep-links equivalentes.
- [x] 4.4 Exibir período/contexto somente onde a API recalcula dados reais e manter última atualização válida em falha de refresh.
- [x] 4.5 Cobrir Home preenchida, vazia, falha parcial, falha preservando dados, `VIEWER`, tema escuro, desktop e mobile.

## 5. Família Clientes — todas as páginas

- [x] 5.1 Refatorar `frontend/app/pages/clients.vue` como shell Settings de Lista/Dashboard, eliminando ação primária ou chrome duplicado.
- [x] 5.2 Refatorar `frontend/app/pages/clients/index.vue` a partir de `customers.vue`, com busca/filtros server-side, A1/captura escaneáveis, ações reais e rodapé consistente.
- [x] 5.3 Refatorar `frontend/app/pages/clients/dashboard.vue` a partir de Home para carteira, onboarding, certificados, captura e deep-links reais.
- [x] 5.4 Refatorar `frontend/app/pages/clients/[id].vue` como Settings, mantendo identidade da raiz, retorno, seções, aside responsivo e permissões.
- [x] 5.5 Refatorar `frontend/app/pages/clients/[id]/index.vue` para resumo de onboarding, riscos e próximos passos sem métricas inventadas.
- [x] 5.6 Refatorar `frontend/app/pages/clients/[id]/cadastro.vue` com cards Settings, leitura/edição clara, `UForm`, 422, 409 e aviso de alteração pendente.
- [x] 5.7 Refatorar `frontend/app/pages/clients/[id]/estabelecimentos.vue` a partir de Members/list, distinguindo matriz/filiais, estado e criação permitida.
- [x] 5.8 Refatorar `frontend/app/pages/clients/[id]/certificado.vue` com saúde sanitizada, upload/substituição segura e ausência explícita de download/senha/recuperação.
- [x] 5.9 Refatorar `frontend/app/pages/clients/[id]/sincronizacao.vue` para canais, cursor/posição, falhas, histórico e ações elegíveis sem edição direta.
- [x] 5.10 Refatorar `frontend/app/pages/clients/[id]/saidas.vue` para perfis/séries, prazos, lacunas, captura/recuperação e gates por papel.
- [x] 5.11 Realinhar `components/clients/*` aos arquétipos Settings, Members, Customers e AddModal, removendo composições externas que não preservem a árvore do template.
- [x] 5.12 Testar a família Clientes em todas as seções, cliente ausente/outro tenant, permissões, 422/409, mobile, dark mode e troca de escritório.

## 6. Família Documentos e Importações — todas as páginas

- [x] 6.1 Refatorar `frontend/app/pages/docs/index.vue` e seu `NotesWorkspace` para visão por cliente escaneável, mantendo contexto e contrato server-side.
- [x] 6.2 Refatorar `frontend/app/pages/docs/catalog.vue` e seu `NotesWorkspace` para catálogo denso, filtros, seleção/export real e detalhe sem perda da lista.
- [x] 6.3 Refatorar `frontend/app/pages/docs/[accessKey].vue` para detalhe canônico, partes/status/chave, download auditado e resposta indistinguível para outro tenant.
- [x] 6.4 Refatorar `frontend/app/pages/docs/imports/index.vue` como Customers, com histórico paginado, estados e ação primária para fluxo guiado.
- [x] 6.5 Refatorar `frontend/app/pages/docs/imports/[id].vue` como Detail/Settings, com progresso, resumo, filtros, erro, retry elegível e CSV sanitizado.
- [x] 6.6 Implementar seleção/configuração/validação/confirmação de XML/ZIP com `UFileUpload`, `UForm` e `UStepper`, respeitando limites e backend autoritativo.
- [x] 6.7 Validar `frontend/app/pages/docs/import-batches.vue` como alias seguro para `/docs/imports`, sem shell duplicado, loop ou query insegura.
- [x] 6.8 Validar `frontend/app/pages/notes/index.vue` como redirect canônico e sanitizado para `/docs`.
- [x] 6.9 Validar `frontend/app/pages/notes/[accessKey].vue` como redirect canônico e sanitizado para `/docs/:accessKey`, inclusive chave inválida.
- [x] 6.10 Realinhar `components/notes/*`, filtros, insights e detalhe aos blocos exatos de Inbox/Customers e aos presets tabulares vigentes.
- [x] 6.11 Testar cursor incremental, mudança de filtro, seleção/export por papel, detalhe desktop/mobile, importação válida/inválida/assíncrona e isolamento tenant.

## 7. Família Monitoramento Fiscal — todas as páginas

- [x] 7.1 Refatorar `frontend/app/pages/monitoring/index.vue` a partir de Home com competência/contexto, cobertura por módulo, carteira em atenção e execuções reais.
- [x] 7.2 Refatorar `frontend/app/pages/monitoring/simples-mei.vue` com submódulos, situação, origem, competência e próximos prazos.
- [x] 7.3 Refatorar `frontend/app/pages/monitoring/dctfweb.vue` com eixos independentes, competência, recibo/evidência/pagamento e confirmação reforçada somente quando habilitada.
- [x] 7.4 Refatorar `frontend/app/pages/monitoring/installments.vue` com modalidade, pedido, parcelas, próxima parcela, atraso e guia.
- [x] 7.5 Refatorar `frontend/app/pages/monitoring/sitfis.vue` com idade/TTL, findings normalizados e detalhe em slideover sem JSON bruto.
- [x] 7.6 Refatorar `frontend/app/pages/monitoring/mailbox.vue` a partir de Inbox como mestre–detalhe real, com filtros e triagem interna distinta de leitura oficial.
- [x] 7.7 Refatorar `frontend/app/pages/monitoring/mailbox/index.vue` como estado vazio neutro, acessível e consistente com Inbox.
- [x] 7.8 Refatorar `frontend/app/pages/monitoring/mailbox/[id].vue` como detalhe canônico, com corpo/anexos protegidos, triagem e retorno de foco.
- [x] 7.9 Refatorar `frontend/app/pages/monitoring/declarations.vue` com aplicabilidade, competência, vencimento, entrega e evidência.
- [x] 7.10 Refatorar `frontend/app/pages/monitoring/guides.vue` com sistema/tipo, valor, vencimento, emissão, pagamento, versão e download protegido.
- [x] 7.11 Refatorar `frontend/app/pages/monitoring/fgts.vue` mantendo banner de cobertura parcial, eSocial conhecido e guia/pagamento `UNSUPPORTED` quando aplicável.
- [x] 7.12 Refatorar `frontend/app/pages/monitoring/clients/[clientId].vue` como Settings com contexto do contribuinte, seções lazy e falhas parciais explícitas.
- [x] 7.13 Realinhar `MonitoringModuleNav`, `FiscalKpiStrip`, `FiscalModuleToolbar`, `FiscalModuleTable`, pickers, badges e empty states ao template/MCP Nuxt UI.
- [x] 7.14 Testar todos os módulos com origem `LIVE`, `DEMO`, vazio, indisponível, erro, não aplicável, não suportado, bloqueado, permissões e mobile.

## 8. Família Trabalho Operacional — todas as páginas

- [x] 8.1 Refatorar `frontend/app/pages/work/index.vue` a partir de Inbox, com lista resizável, filtros/tabs, detalhe, timeline, comentários, evidências e ações por papel.
- [x] 8.2 Refatorar `frontend/app/pages/work/calendar.vue` com visões Mês/Semana/Dia, navegação de data, filtros e painel lateral/minicalendário.
- [x] 8.3 Implementar visão mensal com contagens e severidade por prazo usando os mesmos buckets da fila.
- [x] 8.4 Implementar visão semanal por lanes diárias ordenadas por risco, sem horários fictícios.
- [x] 8.5 Implementar visão diária com fila detalhada e abertura do mesmo detalhe mestre–detalhe/slideover de tarefa.
- [x] 8.6 Refatorar `frontend/app/pages/work/processes/index.vue` a partir de Customers com busca, filtros, risco, paginação e ação primária.
- [x] 8.7 Refatorar `frontend/app/pages/work/processes/[id].vue` como Settings com resumo, checklist, comentários, evidências e histórico.
- [x] 8.8 Refatorar `frontend/app/pages/work/templates/index.vue` a partir de Customers/AddModal com editor ordenável, preview e geração em etapas.
- [x] 8.9 Implementar fluxo de geração por modelo com `UStepper`: seleção, configuração, preview/conflitos, confirmação e progresso do batch.
- [x] 8.10 Reconciliar KPIs, calendário, fila, processos e deep-links por testes sobre prazo efetivo, risco e tenant.
- [x] 8.11 Testar ADMIN/OPERATOR/VIEWER, 409, 422, evidência, tarefa impedida+atrasada, calendário desktop/mobile e dois escritórios.

## 9. Família Operações — todas as páginas

- [x] 9.1 Refatorar `frontend/app/pages/closing/index.vue` como Customers/HomeStats com competência, completude conhecida, risco, paginação e totalizações honestas.
- [x] 9.2 Refatorar `frontend/app/pages/exports/index.vue` como Customers com histórico server-side e criação em etapas de escopo explícito.
- [x] 9.3 Implementar o fluxo de exportação com seleção/configuração/preview/confirmação/progresso sem expor dados de outro tenant ou segredos.
- [x] 9.4 Refatorar `frontend/app/pages/syncs/index.vue` como Customers + Slideover com canais, cursores/posições, detalhe e falha de refresh preservando dados.
- [x] 9.5 Refatorar `frontend/app/pages/health/index.vue` como Customers com severidade, tipo, origem, deep-link, filtros frequentes e paginação/cursor real.
- [x] 9.6 Testar Fechamento, Exportações, Sincronizações e Saúde com paginação/cursor, filtros, estados preservados, ações por papel e mobile.

## 10. Família Configurações e Administração — todas as páginas

- [x] 10.1 Refatorar `frontend/app/pages/settings.vue` a partir de `template/app/pages/settings.vue`, preservando toolbar de seções, permissões e largura canônica.
- [x] 10.2 Refatorar `frontend/app/pages/settings/index.vue` com cards Settings para saúde/gates do Integra Contador e próxima ação sanitizada, sem contrato/credencial global.
- [x] 10.3 Refatorar `frontend/app/pages/settings/cte.vue` com configuração e saúde por canal, formulários tipados e sem edição direta de cursor.
- [x] 10.4 Refatorar `frontend/app/pages/settings/proxies.vue` com validade, poderes, cliente, evidência referenciada e ações permitidas em Settings/table.
- [x] 10.5 Refatorar `frontend/app/pages/settings/usage.vue` com período, usado, franquia, saldo, serviços e ledger tenant-scoped, sem custo global.
- [x] 10.6 Refatorar `frontend/app/pages/settings/subscription.vue` com plano, limites e estado, sem gateway ou cobrança bancária inexistente.
- [x] 10.7 Refatorar `frontend/app/pages/admin/index.vue` como Settings para identidade fiscal, A1 do escritório, onboarding autXML, backup e gates administrativos.
- [x] 10.8 Refatorar `frontend/app/pages/admin/departments.vue` a partir de Members/list com departamentos, memberships, carga/progresso e ativação segura.
- [x] 10.9 Padronizar todos os formulários da família com `UForm`, `UFormField`, schemas, 422, 409, confirmação e aviso de alterações pendentes.
- [x] 10.10 Testar acesso restrito, 2FA recente, VIEWER/OPERATOR, dados sanitizados, falha parcial, mobile e troca de escritório.

## 11. Componentes compartilhados, acessibilidade e desempenho

- [x] 11.1 Auditar cada componente em `frontend/app/components/` e vincular consumidores à família/arquétipo antes de refatorá-lo ou removê-lo.
- [x] 11.2 Consultar o MCP Nuxt UI para props/slots/eventos incertos de Dashboard, Table, Calendar, Stepper, AuthForm, FileUpload, Modal, Slideover e Drawer antes de editar cada padrão.
- [x] 11.3 Consultar os temas gerados em `.nuxt/ui/*.ts` antes de qualquer override de slots e remover overrides que apenas dupliquem defaults.
- [x] 11.4 Garantir uma única ação solid primária por visão e variantes semânticas coerentes para ações secundárias/destrutivas.
- [x] 11.5 Garantir label, `aria-label` ou tooltip para controles icônicos, foco visível, ordem de tabulação e operação sem mouse.
- [x] 11.6 Garantir que tooltips não contenham interação e que detalhes mobile usem drawer/slideover com fechamento e retorno de foco.
- [x] 11.7 Medir payload, número de requests e renderização das listas de maior volume, preservando paginação/cursor server-side e evitando N+1 visual.
- [x] 11.8 Verificar que nenhuma refatoração adiciona runtime Node, SSR, API Nuxt mock ou dependência visual além do stack aprovado.

## 12. Cobertura automatizada por rota e estado

- [x] 12.1 Atualizar testes Vitest de helpers de contexto, filtros, status, datas, permissões, 409, 422 e reset tenant-scoped.
- [x] 12.2 Criar testes de componente para os arquétipos Home, Customers, Inbox, Settings, Auth, Calendar e fluxo Stepper.
- [x] 12.3 Criar matriz Playwright que visite cada página canônica autenticada em `1440×900` e `390×844` com fixture preenchida.
- [x] 12.4 Criar matriz Playwright de vazio, falha inicial e falha de refresh com dados preservados nas famílias representativas.
- [x] 12.5 Criar matriz Playwright de `ADMIN`, `OPERATOR`, `VIEWER`, 2FA e troca entre dois escritórios sem vazamento de estado.
- [x] 12.6 Criar verificação de overflow em `360 px` para cada rota com tabela, formulário, calendário, mestre–detalhe ou overlay.
- [x] 12.7 Atualizar baselines claros e escuros somente após revisão por zonas de shell, header, toolbar, conteúdo, footer e overlay.
- [x] 12.8 Testar redirects `/notes`, `/notes/:accessKey` e `/docs/import-batches`, inclusive parâmetros/identificadores inválidos.
- [x] 12.9 Executar scanner de screenshots, traces, vídeos e relatórios e rejeitar qualquer material sensível proibido.

## 13. Validação final e entrega

- [x] 13.1 Revisar a matriz e confirmar que cada um dos 51 arquivos iniciais e cada rota nova recebeu tarefa concluída e evidência aplicável.
- [x] 13.2 Executar lint, typecheck, Vitest, testes de componente, Playwright funcional/visual e build SPA em ambiente limpo.
- [x] 13.3 Executar testes backend afetados somente se read models/metadados de paginação ou totalização tiverem sido adicionados.
- [x] 13.4 Verificar novamente isolamento por `office_id`, permissões, TOTP, ausência de segredos, FGTS parcial e piloto fiscal somente leitura.
- [x] 13.5 Atualizar a matriz de paridade, documentação de decisões e comandos reproduzíveis com todas as divergências autorizadas.
- [x] 13.6 Executar `openspec validate refactor-complete-dashboard-ui-ux --json`, corrigir todas as violações e registrar o resultado.
- [x] 13.7 Realizar smoke test restrito em desktop/mobile com os três papéis e dois tenants antes de considerar a change implementada.
