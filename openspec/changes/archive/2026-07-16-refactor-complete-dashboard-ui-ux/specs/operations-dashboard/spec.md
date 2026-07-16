## ADDED Requirements

### Requirement: Progresso operacional por departamento
O dashboard SHALL apresentar por departamento tenant-scoped contagens de tarefas abertas, concluídas, atrasadas, em multa e sem responsável e proporção de conclusão, com deep-links equivalentes e sem misturar métricas fiscais, backup ou saúde de canais.

#### Scenario: Comparação de departamentos
- **WHEN** o escritório possui tarefas em mais de um departamento
- **THEN** o usuário compara carga e progresso por cartões compactos e barras, sem depender de gráficos de pizza

#### Scenario: Departamento com risco
- **WHEN** um departamento possui tarefas atrasadas ou em multa
- **THEN** o bloco evidencia a contagem e oferece deep-link para a fila/lista com filtro equivalente

#### Scenario: Nenhuma tarefa operacional
- **WHEN** o escritório não possui tarefas no período
- **THEN** o bloco mostra estado vazio/zero sem alterar ou ocultar os sinais fiscais e de infraestrutura existentes

### Requirement: Agenda operacional derivada dos mesmos prazos da fila
Dashboard, calendário, fila e detalhe SHALL usar a mesma definição tenant-scoped de prazo efetivo, risco e bucket, de modo que contagens e deep-links reconciliem sem criar compromissos ou horários ausentes no modelo.

#### Scenario: Tarefa vencendo hoje
- **WHEN** uma tarefa aparece no KPI `Vencem hoje`
- **THEN** ela aparece no dia correspondente do calendário e no deep-link da fila sob os mesmos filtros

#### Scenario: Tarefa impedida e atrasada
- **WHEN** uma tarefa possui riscos combinados
- **THEN** calendário e fila mostram prazo e impedimento como estados distintos, sem substituir um pelo outro

#### Scenario: Isolamento por escritório
- **WHEN** o usuário troca explicitamente de escritório
- **THEN** agenda, departamentos e contagens são recalculados somente para o novo tenant antes de exibição

### Requirement: Hierarquia calma de riscos e próximas ações
O dashboard SHALL priorizar riscos acionáveis e próximas ações sem transformar todo estado pendente em alerta crítico e MUST manter severidade, prazo, falha técnica e cobertura como dimensões distintas.

#### Scenario: Prazo saudável com canal fiscal bloqueado
- **WHEN** uma tarefa ainda está no prazo e um canal fiscal relacionado está bloqueado
- **THEN** as áreas correspondentes mostram os dois estados separadamente e não rotulam a tarefa como atrasada

#### Scenario: Sobrecarga sem falha técnica
- **WHEN** um departamento possui volume elevado mas nenhuma falha de canal
- **THEN** o dashboard mostra carga/progresso e não inventa um incidente fiscal ou de infraestrutura
