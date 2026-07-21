## ADDED Requirements

### Requirement: Monitoring insights aggregate endpoint

O sistema SHALL expor `GET /api/v1/fiscal/monitoring/insights` autenticado no contexto do office da sessão. A resposta SHALL incluir seções tipadas: `as_of`, `kpis`, `pending`, `rbt12`, `mailbox`, `notifications`, `declarations_absence`, `sitfis`, `obligations_progress` e, quando houver falha parcial, `partial_errors`. Contadores produtivos SHALL excluir origem sintética (`DEMO` / `SIMULATED` / `TRIAL`). O endpoint SHALL NÃO disparar consultas SERPRO nem inventar fixtures.

#### Scenario: Successful insights for office

- **WHEN** um usuário autenticado com office válido solicita `GET /api/v1/fiscal/monitoring/insights`
- **THEN** a API retorna HTTP 200 com as seções tipadas preenchidas a partir de read models locais do office
- **AND** contadores produtivos não incluem origem sintética

#### Scenario: Partial failure is honest

- **WHEN** uma subconsulta (ex.: mailbox) falha e as demais sucedem
- **THEN** a API retorna as seções disponíveis
- **AND** inclui `partial_errors` identificando a seção falha
- **AND** NÃO inventa KPIs para a seção ausente

#### Scenario: Tenant isolation

- **WHEN** o office A solicita insights
- **THEN** a resposta NÃO inclui pending, findings, mailbox ou overviews do office B

### Requirement: Insights dashboard layout on /monitoring

O painel `/monitoring` SHALL apresentar dashboard de insights em layout denso de duas colunas (aprox. 8/4 em viewport larga): coluna esquerda com pendências fiscais, gráfico RBT12 e mensagens e-CAC; coluna direita com feed de notificações/atenção, ausência de declarações, donut de situação fiscal e barras de progresso por obrigação. A UX SHALL usar o shell/Nuxt UI do produto (não clonar chrome MonitorHub). `ManualConsultExplorer` SHALL permanecer disponível abaixo dos insights (fora do primeiro viewport prioritário).

#### Scenario: Dense insights grid loads

- **WHEN** o usuário abre `/monitoring`
- **THEN** a página carrega o payload de insights e renderiza os cards das duas colunas
- **AND** exibe timestamp de atualização e ação de refresh

#### Scenario: Total load failure is fail-closed

- **WHEN** o endpoint de insights falha por completo
- **THEN** a UI exibe alerta de erro
- **AND** NÃO inventa valores de KPI ou gráficos

### Requirement: Honest domain mapping for insight widgets

Os widgets SHALL mapear dados reais sem labels falsos:

- Gráfico de receita/carteira Simples SHALL usar RBT12 (`PARSED`) e NÃO se intitular “Sublimites” como se houvesse limiar persistido.
- Buckets e-CAC SHALL usar Importante / Em dia / Outros (derivados de severidade/triage/leitura); NÃO inventar bucket “Excluído” sem flag no domínio.
- Ausência de declarações SHALL agregar entregues vs em aberto por obrigação aplicável; NÃO exigir split Gerais/SPEDs sem catálogo SPED.
- Progresso por obrigação SHALL incluir PGDAS, DCTFWeb, FGTS e DEFIS com fração `completed/total` quando houver overview; DIRF SHALL aparecer como `UNSUPPORTED` (sem dados inventados).
- Donut de situação fiscal SHALL usar counters do overview `sitfis` (Em dia / Pendentes / Atenção; Outros opcional se > 0).

#### Scenario: RBT12 card copy

- **WHEN** o card de RBT12 é renderizado
- **THEN** o título/descrição referem RBT12 (não sublimite anual inexistente)
- **AND** apenas clientes com RBT12 parseado entram nas barras (demais não recebem valor sintético)

#### Scenario: DIRF progress is unsupported

- **WHEN** o progresso de obrigações inclui DIRF
- **THEN** a UI mostra estado `UNSUPPORTED` ou equivalente honesto
- **AND** NÃO exibe fração inventada
