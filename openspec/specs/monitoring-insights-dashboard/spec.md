## Purpose

Capability `monitoring-insights-dashboard` — requisitos sincronizados das changes OpenSpec.
## Requirements
### Requirement: Monitoring insights aggregate endpoint

O sistema SHALL expor `GET /api/v1/fiscal/monitoring/insights` autenticado no contexto do office da sessão. A resposta SHALL incluir seções tipadas: `as_of`, `kpis`, `pending`, `rbt12`, `mailbox`, `notifications`, `declarations_absence`, `sitfis`, `obligations_progress` e, quando houver falha parcial, `partial_errors`. `kpis` SHALL incluir a contagem real de clientes do `Office`, pendências abertas, findings ativos e módulos com erro, usando `null` quando a fonte correspondente falhar. Contadores produtivos SHALL excluir origem sintética (`DEMO` / `SIMULATED` / `TRIAL`). O endpoint SHALL NÃO disparar consultas SERPRO nem inventar fixtures.

#### Scenario: Successful insights for office

- **WHEN** um usuário autenticado com office válido solicita `GET /api/v1/fiscal/monitoring/insights`
- **THEN** a API retorna HTTP 200 com as seções tipadas preenchidas a partir de read models locais do office
- **AND** `kpis.clients_total` corresponde aos clientes persistidos daquele `Office`
- **AND** contadores produtivos não incluem origem sintética

#### Scenario: Partial failure is honest

- **WHEN** uma subconsulta (ex.: mailbox ou contagem de clientes) falha e as demais sucedem
- **THEN** a API retorna as seções disponíveis
- **AND** inclui `partial_errors` identificando a seção falha
- **AND** usa `null` no KPI cuja fonte falhou
- **AND** NÃO inventa KPIs para a seção ausente

#### Scenario: Tenant isolation

- **WHEN** o office A solicita insights
- **THEN** a resposta NÃO inclui clientes, pending, findings, mailbox ou overviews do office B

### Requirement: Insights dashboard layout on /monitoring

O painel `/monitoring` SHALL apresentar um resumo operacional responsivo com faixa de KPIs reais, prioridades abertas, atividade recente, saúde das carteiras e contexto analítico de RBT12, e-CAC e declarações. A UX SHALL usar o shell/Nuxt UI do produto, manter scroll vertical no `#body` de `UDashboardPanel` sem overflow horizontal e oferecer deep-links canônicos para os módulos. `ManualConsultExplorer` SHALL permanecer disponível em seção secundária abaixo dos insights, fora do primeiro viewport prioritário.

#### Scenario: Operational dashboard loads

- **WHEN** o usuário abre `/monitoring`
- **THEN** a página carrega um único payload agregado e renderiza os KPIs e seções operacionais responsivas
- **AND** exibe timestamp de atualização e ação de refresh
- **AND** cada card acionável leva à rota canônica do módulo correspondente

#### Scenario: Dense insights grid loads

- **WHEN** o usuário abre `/monitoring`
- **THEN** a página carrega o payload de insights e renderiza os cards das duas colunas
- **AND** exibe timestamp de atualização e ação de refresh

#### Scenario: Responsive panel body

- **WHEN** o dashboard é exibido em viewport mobile ou desktop
- **THEN** cards e gráficos se ajustam à largura disponível sem overflow horizontal
- **AND** o corpo canônico do painel preserva o scroll vertical

#### Scenario: Total load failure is fail-closed

- **WHEN** a primeira chamada do endpoint de insights falha por completo
- **THEN** a UI exibe alerta de erro e ação de nova tentativa
- **AND** NÃO inventa valores de KPI ou gráficos

#### Scenario: Refresh failure preserves last valid snapshot

- **WHEN** existe um snapshot válido e uma atualização posterior falha
- **THEN** a UI mantém os últimos valores confirmados visíveis
- **AND** sinaliza que a atualização falhou sem substituir valores por zero

### Requirement: Honest domain mapping for insight widgets

Os widgets SHALL mapear dados reais e estados de cobertura sem labels falsos:

- KPI de clientes SHALL representar clientes persistidos no `Office`, sem afirmar adesão universal a todos os módulos.
- Gráfico de receita/carteira Simples SHALL usar RBT12 (`PARSED`) e NÃO se intitular “Sublimites” como se houvesse limiar persistido.
- Buckets e-CAC SHALL usar Importante / Em dia / Outros (derivados de severidade/triage/leitura); NÃO inventar bucket “Excluído” sem flag no domínio.
- Ausência de declarações SHALL agregar entregues vs em aberto por obrigação aplicável; NÃO exigir split Gerais/SPEDs sem catálogo SPED.
- Progresso por obrigação SHALL incluir PGDAS, DCTFWeb, FGTS e DEFIS com fração `completed/total` quando houver overview; DIRF SHALL aparecer como `UNSUPPORTED` (sem dados inventados).
- Resumo de situação fiscal SHALL usar counters do overview `sitfis` e separar estados confirmados de erro, processamento, desconhecido, bloqueado, não aplicável e não suportado.
- Valor zero SHALL ser exibido somente após resposta válida; loading, ausência de cobertura e falha SHALL usar estados visuais distintos.

#### Scenario: RBT12 card copy

- **WHEN** o card de RBT12 é renderizado
- **THEN** o título/descrição referem RBT12 (não sublimite anual inexistente)
- **AND** apenas clientes com RBT12 parseado entram nas barras (demais não recebem valor sintético)

#### Scenario: DIRF progress is unsupported

- **WHEN** o progresso de obrigações inclui DIRF
- **THEN** a UI mostra estado `UNSUPPORTED` ou equivalente honesto
- **AND** NÃO exibe fração inventada

#### Scenario: Failed KPI source

- **WHEN** `partial_errors` inclui a fonte de um KPI
- **THEN** a UI exibe indisponibilidade para esse KPI
- **AND** NÃO exibe zero como se fosse um resultado confirmado
