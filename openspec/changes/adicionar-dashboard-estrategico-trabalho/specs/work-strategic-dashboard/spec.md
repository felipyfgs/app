## ADDED Requirements

### Requirement: Visão estratégica de Trabalho na rota raiz

A rota autenticada `/work` SHALL apresentar uma visão estratégica dos processos e tarefas do escritório corrente, separada das superfícies de execução. A visão MUST exibir indicadores de tarefas abertas, atrasadas, em multa, vencendo hoje, em progresso e sem responsável, além de progresso por departamento, maiores riscos e processos abertos sem responsável quando esses dados existirem.

#### Scenario: Escritório com trabalho operacional

- **WHEN** um membro autorizado abre `/work` e `GET /api/v1/work/kpis` retorna um snapshot
- **THEN** o dashboard apresenta os indicadores e agrupamentos do snapshot sem inventar dados ausentes
- **AND** cada indicador ou exceção acionável oferece deep link para a fila, tarefa ou processo correspondente

#### Scenario: Escritório sem tarefas ou processos

- **WHEN** o snapshot não contém riscos, departamentos relevantes ou processos sem responsável
- **THEN** a visão mantém os indicadores numéricos válidos
- **AND** apresenta empty states orientativos no lugar de listas vazias

### Requirement: Dados locais, tenant-scoped e resilientes

O dashboard SHALL consumir somente os read models locais autenticados de Work e MUST NOT disparar egress SERPRO, SEFAZ ou provider MEI. A interface MUST distinguir loading inicial, erro sem dados e snapshot anterior desatualizado, permitir retry e invalidar dados ao trocar a identidade ou o escritório da sessão.

#### Scenario: Falha no primeiro carregamento

- **WHEN** a API de KPIs falha antes de existir snapshot válido
- **THEN** a página exibe erro acionável com opção de tentar novamente
- **AND** não converte a ausência de dados em zeros

#### Scenario: Falha após um carregamento válido

- **WHEN** uma atualização falha depois que um snapshot válido já foi exibido
- **THEN** o último snapshot permanece visível com aviso de desatualização
- **AND** o operador pode repetir a consulta

#### Scenario: Troca de escritório na sessão

- **WHEN** o contexto autenticado muda e o `sessionEpoch` é incrementado
- **THEN** dados e departamentos do contexto anterior são descartados antes do novo carregamento

### Requirement: Rotas canônicas e compatibilidade da fila

A fila sem seleção SHALL usar `/work/tasks`, enquanto o detalhe MUST permanecer em `/work/tasks/{id}` e os filtros MUST continuar na query string. A navegação Work SHALL distinguir Visão geral e Tarefas. URLs legadas de `/work` que contenham chaves reconhecidas da fila MUST ser redirecionadas com substituição de histórico para a rota canônica, preservando filtros válidos.

#### Scenario: Navegação direta para a fila

- **WHEN** o usuário aciona Tarefas no menu ou em um KPI
- **THEN** a aplicação navega para `/work/tasks` com o filtro correspondente
- **AND** a visão Fila ou Lista mantém o comportamento existente

#### Scenario: Filtro legado na rota raiz

- **WHEN** o usuário abre `/work?tab=atrasadas&department_id=4`
- **THEN** a aplicação substitui a URL por `/work/tasks?tab=atrasadas&department_id=4`
- **AND** nenhum filtro reconhecido é perdido

#### Scenario: Seleção legada na query

- **WHEN** o usuário abre `/work?task=27&view=lista`
- **THEN** a aplicação substitui a URL por `/work/tasks/27?view=lista`
- **AND** remove `task` da query

### Requirement: Composição responsiva e alinhada ao shell

O dashboard SHALL reutilizar o `UDashboardPanel` e os componentes canônicos do shell Nuxt UI, com cores semânticas e informação textual que não dependa apenas de cor. Em viewport estreita, KPIs, progresso e listas MUST reordenar em uma única coluna sem overflow horizontal obrigatório.

#### Scenario: Leitura em desktop

- **WHEN** a viewport é larga
- **THEN** o dashboard prioriza a execução por departamento e mantém exceções visíveis em coluna adjacente

#### Scenario: Leitura em mobile

- **WHEN** a viewport é estreita
- **THEN** cards e listas são empilhados
- **AND** rótulos, valores, estados e ações permanecem acessíveis por teclado e leitor de tela

### Requirement: Hierarquia executiva de desempenho

O dashboard SHALL organizar a posição atual em uma composição executiva densa: desempenho geral e situação das tarefas no painel principal, nível operacional em painel adjacente, desempenho por departamento e atalhos/resumo operacional. A interface MUST identificar a conclusão como proporção do snapshot consolidado, MUST manter um resumo textual para os indicadores gráficos e MUST NOT simular período histórico ou desempenho individual ausente no read model.

#### Scenario: Snapshot com departamentos e riscos

- **WHEN** o snapshot contém tarefas concluídas, abertas e agrupamentos por departamento
- **THEN** o painel geral apresenta a proporção de conclusão e os estados operacionais com valores acionáveis
- **AND** o nível operacional deriva da porcentagem consolidada e dos riscos do snapshot
- **AND** o quadro de departamentos apresenta carga, riscos e conclusão sem atribuir métricas a pessoas não identificadas

#### Scenario: Adaptação entre desktop e mobile

- **WHEN** a viewport muda entre desktop e mobile
- **THEN** o desempenho por departamento usa comparação tabular no desktop e cards equivalentes no mobile
- **AND** nenhum dos dois modos exige scroll horizontal para acessar valores ou ações
