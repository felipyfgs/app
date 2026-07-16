## ADDED Requirements

### Requirement: Listagens hierárquicas server-side
O sistema SHALL listar o trabalho por cliente, processo ou tarefa com paginação, ordenação e filtros server-side por período, competência, cliente, departamento, responsável, estado, prazo e risco.

#### Scenario: Filtro combinado
- **WHEN** usuário filtra uma competência, departamento e risco atrasado
- **THEN** a API aplica todos os filtros no escritório ativo, devolve metadados de paginação e informa os filtros efetivos

#### Scenario: Troca de nível
- **WHEN** usuário alterna de visão por processo para visão por tarefa
- **THEN** os filtros compatíveis são preservados e cada linha mantém deep-link para o recurso autorizado

### Requirement: Calendário agregado por prazo
O sistema SHALL oferecer visão mensal e semanal com contagens diárias de atrasadas, vencem no dia, a fazer, em progresso e concluídas, calculadas no timezone do escritório.

#### Scenario: Abertura do mês
- **WHEN** usuário solicita um mês e filtros válidos
- **THEN** o sistema retorna agregados somente dos processos/tarefas autorizados do escritório para cada data civil

#### Scenario: Detalhe do dia
- **WHEN** usuário seleciona um dia do calendário
- **THEN** o sistema carrega lista paginada daquele dia sem transferir toda a base mensal para o navegador

### Requirement: Indicadores operacionais com definições estáveis
O sistema SHALL calcular total de tarefas, atrasadas, em multa, vencem hoje, em progresso, concluídas e sem responsável conforme as mesmas regras usadas pela fila e SHALL retornar `generated_at` e timezone.

#### Scenario: Mesmo filtro na fila e no KPI
- **WHEN** dashboard e lista usam o mesmo escritório, período e filtros
- **THEN** a contagem navegável corresponde ao conjunto da lista segundo a definição publicada

#### Scenario: Tarefa com riscos combinados
- **WHEN** uma tarefa está atrasada e em multa
- **THEN** ela participa de ambos os KPIs específicos sem ser duplicada no total de tarefas

### Requirement: Visão de carga e risco
O sistema SHALL agregar tarefas abertas e concluídas por departamento e responsável e listar maiores riscos, processos sem dono e clientes com pendências, sem produzir recomendação automática de pessoal.

#### Scenario: Responsável sobrecarregado
- **WHEN** um responsável possui mais tarefas abertas que os demais no filtro
- **THEN** o agrupamento mostra a carga observada e deep-link correspondente sem reatribuir trabalho automaticamente

#### Scenario: Processo sem dono
- **WHEN** processo aberto não possui responsável efetivo
- **THEN** ele aparece na lista gerencial de atenção e pode ser aberto por usuário autorizado

### Requirement: Exportação CSV assíncrona
O sistema SHALL permitir que `ADMIN` e `OPERATOR` criem exportação CSV a partir dos filtros autorizados, com processamento em fila, expiração e download privado tenant-scoped.

#### Scenario: Export concluído
- **WHEN** job conclui exportação válida
- **THEN** o arquivo contém somente colunas operacionais allowlisted do snapshot de filtros e fica disponível ao escritório solicitante até expirar

#### Scenario: Export de outro escritório
- **WHEN** usuário tenta consultar ou baixar export pertencente a outro tenant
- **THEN** o sistema responde como não encontrado e não entrega metadados nem arquivo

#### Scenario: Conteúdo proibido
- **WHEN** o CSV é inspecionado
- **THEN** ele não contém comentários, bytes/IDs do cofre, paths, PFX, PEM, tokens, Termo XML ou conteúdo fiscal bruto

### Requirement: Consultas e agregações estritamente tenant-scoped
O sistema MUST derivar `office_id` da sessão em listagens, calendário, indicadores, agrupamentos, locks e exports e MUST NOT aceitar filtro de tenant fornecido pelo cliente como autoridade.

#### Scenario: Mesmo cliente em dois escritórios
- **WHEN** o mesmo CNPJ possui processos na mesma competência em dois escritórios
- **THEN** cada escritório vê apenas seus processos, tarefas, contagens e arquivos

#### Scenario: Office injetado no filtro
- **WHEN** requisição adiciona `office_id` externo aos filtros
- **THEN** o valor é ignorado ou rejeitado e nenhuma métrica externa influencia a resposta

### Requirement: Respostas honestas e navegáveis
O sistema SHALL distinguir ausência de trabalho, falha de consulta e dado não aplicável, e SHALL fornecer deep-links estáveis dos indicadores e riscos para as listagens filtradas correspondentes.

#### Scenario: Escritório sem tarefas
- **WHEN** não existem tarefas no período selecionado
- **THEN** a resposta representa zero/vazio e não inventa sucesso operacional ou falha

#### Scenario: Clique em KPI atrasadas
- **WHEN** usuário ativa o KPI de atrasadas
- **THEN** a navegação abre a lista com filtro equivalente refletido na URL

