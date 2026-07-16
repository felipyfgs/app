## ADDED Requirements

### Requirement: Progresso operacional por departamento
O dashboard SHALL apresentar para cada departamento do office ativo abertas, concluídas no período, atrasadas, em multa, sem responsável e proporção de conclusão, derivados do mesmo corte temporal e regras da fila.

#### Scenario: Dashboard preenchido
- **WHEN** o endpoint operacional retorna departamentos com trabalho
- **THEN** cada departamento aparece em bloco compacto com contagens, `UProgress` acessível e horário da última atualização válida

#### Scenario: Departamento sem atividade
- **WHEN** um departamento ativo não possui tarefas no corte
- **THEN** o dashboard apresenta zero de forma neutra ou omite conforme contrato explícito, sem inventar percentual ou tendência

#### Scenario: Percentual calculado
- **WHEN** existem tarefas abertas e concluídas no período
- **THEN** numerador, denominador e percentual usam o mesmo conjunto tenant-scoped e o texto acessível comunica o valor além da barra visual

### Requirement: Deep-links do trabalho preservam o recorte
Indicadores e blocos departamentais SHALL abrir fila ou processos com filtros representáveis na URL e semanticamente equivalentes ao número acionado.

#### Scenario: Abrir atrasadas do Fiscal
- **WHEN** o usuário ativa a contagem de atrasadas do departamento Fiscal
- **THEN** `/work` abre com tab/risco e departamento correspondentes, consulta a API e não aplica filtro apenas no cliente

#### Scenario: Abrir processos sem responsável
- **WHEN** o usuário ativa um item de processo sem responsável
- **THEN** o detalhe autorizado ou a lista filtrada é aberta sem perder o office da sessão

### Requirement: Sinais de trabalho permanecem separados dos demais domínios
O dashboard MUST manter métricas de trabalho operacional separadas de saúde fiscal, sincronização, backup e infraestrutura, sem somar contagens heterogêneas ou usar cores como único identificador.

#### Scenario: Home com múltiplos blocos
- **WHEN** a Home recebe indicadores fiscais e de trabalho
- **THEN** títulos, descrições e deep-links distinguem os domínios e nenhuma tarefa é apresentada como finding fiscal ou falha de infraestrutura

#### Scenario: Falha parcial do bloco Work
- **WHEN** o endpoint de trabalho falha e os demais blocos carregam
- **THEN** somente o bloco Work apresenta erro/retry sanitizado e os demais dados válidos permanecem visíveis
