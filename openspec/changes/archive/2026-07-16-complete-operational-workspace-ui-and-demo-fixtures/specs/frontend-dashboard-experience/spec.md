## ADDED Requirements

### Requirement: Workspace de tarefas em mestre–detalhe completo
O sistema SHALL apresentar `/work` como dois painéis reconhecíveis do arquétipo Inbox: lista lateral redimensionável e detalhe adjacente no desktop, com detalhe em slideover/drawer abaixo de `lg` e seleção reproduzível na URL.

#### Scenario: Abertura preenchida no desktop
- **WHEN** o usuário abre `/work` em viewport `lg` ou maior e a fila possui tarefas
- **THEN** a lista mostra contagem, prioridade, prazo, cliente, processo, departamento e responsável, a primeira seleção válida abre no painel adjacente e o shell permanece utilizável

#### Scenario: Seleção no mobile
- **WHEN** uma tarefa é selecionada em viewport menor que `lg`
- **THEN** o detalhe abre em overlay acessível, pode ser fechado por teclado/controle visível e devolve foco ao item selecionado

#### Scenario: Navegação por teclado
- **WHEN** o foco está na lista e o usuário pressiona seta para cima ou para baixo
- **THEN** a seleção percorre somente os itens visíveis, atualiza o detalhe/URL e mantém o item selecionado em vista

#### Scenario: Detalhe operacional
- **WHEN** uma tarefa autorizada é selecionada
- **THEN** o painel apresenta lifecycle, riscos, prazo, cliente/processo/competência, atribuição, descrição, comentários, evidências, timeline e somente ações permitidas ao papel

### Requirement: Filtros e estado da fila são server-side e reproduzíveis
A fila SHALL consultar o backend para tabs, busca, departamento, responsável, cliente, escopo e paginação, refletindo na URL os filtros compartilháveis e reiniciando a página quando o escopo muda.

#### Scenario: Filtro por departamento
- **WHEN** o usuário seleciona um departamento
- **THEN** a URL e a chamada da API recebem o filtro tenant-scoped, a paginação reinicia e a lista não filtra apenas os registros já carregados

#### Scenario: Operador alterna escopo
- **WHEN** um `OPERATOR` usa o escopo padrão
- **THEN** a fila mantém próprias tarefas e tarefas livres elegíveis do departamento, sem oferecer escopo de todo o office se a policy não permitir

#### Scenario: Office trocado durante request
- **WHEN** a membership ativa muda enquanto a fila ou detalhe está carregando
- **THEN** seleção e dados anteriores são limpos, a resposta antiga é descartada e nenhum registro do tenant anterior é renderizado

### Requirement: Calendário operacional Mês Semana Dia
O sistema SHALL oferecer em `/work/calendar` visões `Mês`, `Semana` e `Dia` baseadas em prazos de tarefa, com data/filtros reproduzíveis, navegação temporal e painel lateral com `UCalendar` como seletor de data.

#### Scenario: Visão mensal
- **WHEN** o usuário escolhe `Mês`
- **THEN** a grade exibe dias civis, contagens e severidade de risco do intervalo retornado pelo backend sem carregar todas as tarefas do office

#### Scenario: Visão semanal
- **WHEN** o usuário escolhe `Semana`
- **THEN** sete lanes por dia exibem tarefas ordenadas por risco/prazo e não existe eixo horário ou posição vertical inventada

#### Scenario: Visão diária
- **WHEN** o usuário escolhe `Dia` ou uma data no minicalendário
- **THEN** a rota reflete a data e apresenta fila detalhada paginada com as mesmas ações e permissões do workspace

#### Scenario: Rail inspirado na referência externa
- **WHEN** o calendário é exibido no desktop
- **THEN** o rail contém minicalendário e tabs de Tarefas, Atrasadas e Concluídas da data, mantendo tokens, componentes e shell do MonitorHub

#### Scenario: Calendário móvel
- **WHEN** o calendário abre em 390 px
- **THEN** visão, data e tarefas essenciais permanecem acessíveis e o rail migra para composição/overlay sem overflow horizontal do documento

### Requirement: Lista e detalhe de processos operacionais
O sistema SHALL apresentar `/work/processes` no arquétipo Customers com consulta server-side e `/work/processes/{id}` no arquétipo Settings com seções reproduzíveis e dados completos do processo.

#### Scenario: Lista preenchida
- **WHEN** a API retorna processos
- **THEN** a tabela mostra cliente, competência, lifecycle, risco/progresso, responsável/departamento e prazo, além de total/paginação e ações permitidas

#### Scenario: Detalhe por seções
- **WHEN** o usuário abre um processo autorizado
- **THEN** a toolbar oferece Resumo, Tarefas, Comentários/Evidências e Histórico, destaca a seção da URL e carrega somente o conteúdo aplicável

#### Scenario: Checklist do processo
- **WHEN** a seção Tarefas é aberta
- **THEN** cada item apresenta ordem, estado, prazo, criticidade, exigência/evidência, responsável e ações autorizadas sem perder o contexto do processo

#### Scenario: Processo de outro office
- **WHEN** a URL usa ID pertencente a outro tenant
- **THEN** a página apresenta não encontrado sem revelar cliente, título, contagem ou existência do processo

### Requirement: Modelos e geração com divulgação progressiva
O sistema SHALL apresentar modelos como lista administrativa e SHALL conduzir geração por `UStepper` usando preview e confirmação reais do backend.

#### Scenario: Criação ou edição de modelo
- **WHEN** um `ADMIN` abre criação/edição
- **THEN** `UModal`/`UForm` valida nome, departamento, regra e tarefas, associa erros 422 aos campos e preserva valores não sensíveis após falha

#### Scenario: Preview de geração
- **WHEN** o usuário seleciona modelo, clientes, competência e overrides válidos
- **THEN** o backend cria preview persistido e a UI apresenta itens prontos, alertas, bloqueios e duplicidades antes da confirmação

#### Scenario: Confirmação e acompanhamento
- **WHEN** o usuário confirma um preview vigente
- **THEN** a UI acompanha o batch real, não simula sucesso e oferece deep-links para processos criados ou erros sanitizados

#### Scenario: Viewer em modelos
- **WHEN** um `VIEWER` abre a página
- **THEN** modelos podem ser consultados conforme policy, mas criação, edição, preview mutante e confirmação não são oferecidos

### Requirement: Estados operacionais completos e honestos
Todas as rotas `/work` SHALL distinguir loading inicial, atualização, preenchido, vazio legítimo, erro inicial, erro de refresh, 403, 409 e somente leitura sem substituir ausência de dados reais por conteúdo sintético no frontend.

#### Scenario: Office real sem processos
- **WHEN** a API responde com lista vazia para um office não demonstrativo
- **THEN** a UI mostra onboarding/criação somente a papel autorizado e uma mensagem de leitura honesta para `VIEWER`

#### Scenario: Refresh falha após sucesso
- **WHEN** a atualização falha depois de dados válidos terem sido exibidos
- **THEN** os dados anteriores e horário da última atualização válida permanecem visíveis junto ao erro sanitizado

#### Scenario: Conflito otimista
- **WHEN** uma ação retorna 409 por `lock_version`
- **THEN** a UI preserva entrada não sensível, informa que o registro mudou e oferece recarregar antes de tentar novamente
