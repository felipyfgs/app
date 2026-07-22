## ADDED Requirements

### Requirement: Dualidade Fila e Lista na superfície Tarefas

A superfície Tarefas (`/work` e `/work/tasks/:id`) SHALL oferecer duas visões mutuamente exclusivas — **Fila** (mestre–detalhe) e **Lista** (tabela) — controladas pelo parâmetro de query `view`. Ausência de `view` ou `view=fila` MUST ativar a Fila; `view=lista` MUST ativar a Lista. Ambas MUST consumir a mesma fila autenticada (`GET /api/v1/work/queue`) e os mesmos filtros (`tab`, `q`, `department_id`, `assignee_membership_id`, `client_id`, `scope`, `page`, `per_page`), sem duplicar estado operacional no cliente.

#### Scenario: Toggle persiste na URL

- **WHEN** o usuário escolhe Lista no controle de visão
- **THEN** a URL passa a incluir `view=lista` sem perder os demais filtros
- **AND** recarregar a página restaura a Lista

#### Scenario: Volta para Fila

- **WHEN** o usuário escolhe Fila
- **THEN** `view` é omitido ou igual a `fila`
- **AND** o layout mestre–detalhe permanece disponível como antes desta change

### Requirement: Visão Lista de Tarefas via ShellDataTable

A visão Lista SHALL renderizar as tarefas com `ShellDataTable` (UTable do Nuxt UI + cards mobile do shell) em painel único, com colunas de tarefa, status, prazo, cliente/processo e responsável. MUST NOT usar layout de cards/accordion custom fora do padrão mobile do shell. A Lista MUST paginar via footer do `ShellDataTable`.

#### Scenario: Varredura no desktop

- **WHEN** há tarefas na página atual da fila em `view=lista`
- **THEN** o usuário vê uma tabela densa sem painel de detalhe permanente ao lado
- **AND** cada linha identifica status, prazo efetivo e origem (cliente/processo) quando disponíveis

#### Scenario: Paginação na Lista

- **WHEN** o total de tarefas excede a página corrente
- **THEN** o rodapé do `ShellDataTable` permite mudar página e tamanho de página
- **AND** a query sincroniza `page` e `per_page`

### Requirement: Detalhe canônico a partir da Lista

Abrir uma tarefa na Lista SHALL navegar para o path canônico `/work/tasks/{id}` preservando `view=lista` e os filtros, e SHALL apresentar o mesmo painel de detalhe usado na Fila em slideover. Fechar o detalhe MUST voltar a `/work` mantendo `view=lista` e filtros.

#### Scenario: Abrir e fechar na Lista

- **WHEN** o usuário aciona uma linha na Lista
- **THEN** o detalhe canônico abre em slideover com os dados da tarefa
- **AND** ao fechar, permanece em Lista sem seleção de path

#### Scenario: Deep link Lista + tarefa

- **WHEN** a URL é `/work/tasks/{id}?view=lista` com filtros válidos
- **THEN** a Lista carrega e o detalhe da tarefa abre automaticamente no slideover

### Requirement: Fila intacta como modo padrão

A visão Fila SHALL continuar com lista estreita + detalhe lateral no desktop e slideover no mobile, auto-seleção da primeira tarefa no desktop quando não houver seleção. Entrar em Lista MUST NOT auto-selecionar tarefa ao abrir `/work?view=lista`.

#### Scenario: Default sem view

- **WHEN** o usuário abre `/work` sem `view`
- **THEN** a Fila é exibida
- **AND** o comportamento mestre–detalhe existente permanece

### Requirement: Listagem de Processos via ShellDataTable

A visão Processos SHALL listar processos operacionais com `ShellDataTable`, exibindo ao menos processo, cliente, status, progresso, prazo e responsável. MUST oferecer link explícito ao detalhe canônico do processo e acesso ao monitoramento/cliente quando disponíveis. MUST NOT depender de accordion ou cards custom como layout principal no desktop.

#### Scenario: Tabela de processos no desktop

- **WHEN** o usuário abre `/work/processes` com resultados
- **THEN** vê uma tabela paginada com as colunas operacionais
- **AND** pode abrir o detalhe canônico do processo sem expansão inline obrigatória

#### Scenario: Mobile usa cards do shell

- **WHEN** a viewport é estreita
- **THEN** o `ShellDataTable` apresenta o caminho de cards mobile do kit shell
- **AND** as ações de abrir processo permanecem disponíveis
