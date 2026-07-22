## ADDED Requirements

### Requirement: Bulk de tarefas com ações operacionais

O sistema SHALL aceitar `POST /api/v1/work/tasks/bulk` com até 100 itens (`id` + `lock_version`) e uma ação entre `start`, `complete`, `resume`, `block`, `claim`, `assign`, `set_due_date`, `set_department`. `block` MUST exigir motivo. Autorização MUST ser avaliada por item com a mesma policy da transição unitária. A resposta MUST reportar sucessos e falhas parciais (ex.: evidência obrigatória em `complete`).

#### Scenario: Iniciar várias tarefas

- **WHEN** um executor autorizado envia bulk com `action=start` e locks válidos
- **THEN** cada tarefa elegível passa a `EM_PROGRESSO`
- **AND** itens sem permissão ou lock inválido aparecem em `failed`

#### Scenario: Completar sem evidência

- **WHEN** uma tarefa com `requires_evidence` entra no lote `complete` sem evidência
- **THEN** esse item falha sem impedir o registro das demais sucessos no relatório

### Requirement: Bulk de processos com ações de gestão

O sistema SHALL aceitar `POST /api/v1/work/processes/bulk` com até 100 itens (`id` + `lock_version`) e uma ação entre `archive`, `assign`, `set_department`, `set_due_date`. Autorização MUST ser avaliada por item: `archive` com a policy de archive unitário; demais ações com a policy de update unitário, reutilizando `OperationalProcessService::update` para `assignee_membership_id`, `work_department_id` e `due_date`. A resposta MUST reportar sucessos e falhas parciais. Exclusão física de processos MUST NOT ser exposta neste endpoint (non-goal). Status do processo MUST NOT ser alterado em massa por este endpoint (status continua derivado das tarefas).

#### Scenario: Arquivar seleção

- **WHEN** um membro autorizado arquiva processos em lote
- **THEN** cada processo elegível fica `ARQUIVADO`
- **AND** falhas por lock/policy são reportadas por item

#### Scenario: Atribuir responsável em lote

- **WHEN** um membro com permissão de update envia bulk com `action=assign` e `assignee_membership_id` válido
- **THEN** cada processo elegível atualiza o responsável
- **AND** itens sem permissão ou lock inválido aparecem em `failed`

### Requirement: Ordenação server-side nas listas Work

`GET /work/processes` e `GET /work/queue` SHALL aceitar `sort` e `direction` apenas em colunas whitelisted. A UI Lista MUST usar `manualSorting` e `sortHeader` só nessas colunas, sincronizando a query Nuxt. Colunas sem suporte MUST ter `enableSorting: false`.

#### Scenario: Ordenar processos por prazo

- **WHEN** o usuário ordena a coluna Prazo em `/work/processes`
- **THEN** a API recebe `sort=due_date` e `direction`
- **AND** a URL preserva esses parâmetros

#### Scenario: Ordenar fila por título

- **WHEN** o usuário ordena por Tarefa em `/work?view=lista`
- **THEN** a fila aplica ordenação por título após o filtro de aba
- **AND** sem `sort` a ordem default por bucket permanece

### Requirement: Status inline e modal de ações em massa

As listas tabulares de Processos e Tarefas (Lista) SHALL permitir multiseleção com modal de ações em massa e SHALL oferecer controle inline para alterar o status da tarefa conforme transições permitidas, sem exigir abrir o detalhe completo.

#### Scenario: Status na expansão do processo

- **WHEN** o usuário expande um processo e escolhe um novo status permitido na tarefa
- **THEN** a API de transição correspondente é chamada com `lock_version`
- **AND** a lista recarrega o estado atualizado

#### Scenario: Modal a partir da seleção

- **WHEN** há linhas selecionadas e o usuário confirma uma ação no modal
- **THEN** o cliente chama o endpoint bulk adequado
- **AND** limpa a seleção após o resultado
