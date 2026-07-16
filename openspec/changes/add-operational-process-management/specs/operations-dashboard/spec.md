## ADDED Requirements

### Requirement: Bloco de trabalho operacional no dashboard
O sistema SHALL acrescentar ao dashboard existente um bloco tenant-scoped com tarefas totais, atrasadas, em multa, vencem hoje, em progresso, concluídas e sem responsável, mantendo esses números semanticamente separados da inbox fiscal, sincronizações e backup.

#### Scenario: Dashboard com trabalho e alertas fiscais
- **WHEN** o escritório possui tarefas atrasadas e também cursor fiscal bloqueado
- **THEN** o dashboard mostra ambos em áreas identificadas, sem somar ou rotular uma categoria como se fosse a outra

#### Scenario: Filtro de competência
- **WHEN** usuário aplica competência ao bloco operacional
- **THEN** somente os KPIs de trabalho e agrupamentos compatíveis são recalculados, preservando a semântica dos indicadores fiscais existentes

### Requirement: Riscos e carga com deep-links
O sistema SHALL mostrar maiores riscos, processos sem dono e agrupamentos por departamento/responsável com deep-links para `/work/processes` ou `/work` usando filtros equivalentes.

#### Scenario: KPI sem responsável
- **WHEN** existem tarefas abertas sem responsável no escritório
- **THEN** o card exibe a contagem e seu deep-link abre a lista tenant-scoped com risco `SEM_RESPONSAVEL`

#### Scenario: Acesso sem permissão de mutação
- **WHEN** `VIEWER` abre um deep-link gerencial
- **THEN** a lista é exibida em modo somente leitura e não oferece reatribuição ou lote

### Requirement: Resumo operacional protegido e sanitizado
O sistema MUST derivar os indicadores de processo do escritório da sessão e MUST NOT incluir comentário, evidência, conteúdo fiscal, identificador do cofre ou material sensível no resumo ou nos agrupamentos.

#### Scenario: Tentativa de filtro por outro office
- **WHEN** requisição do dashboard injeta `office_id` de outro tenant
- **THEN** o valor não altera contagens, séries, riscos ou deep-links do escritório ativo

#### Scenario: Varredura do payload
- **WHEN** a resposta consolidada é inspecionada em teste automatizado
- **THEN** ela não contém `vault_object_id`, path, bytes de evidência, PFX, PEM, senha, token, Consumer Secret ou Termo XML

### Requirement: Preservação dos sinais operacionais existentes
O sistema SHALL manter o estado de backup, a inbox operacional, saúde de canais e demais métricas existentes ao adicionar o módulo de processos.

#### Scenario: Escritório sem processos
- **WHEN** não existem processos no escritório
- **THEN** o novo bloco apresenta estado vazio/zero e os cards de backup, inbox e saúde continuam funcionais

