## MODIFIED Requirements

### Requirement: Detalhe da fila de Tarefas é colapsável no desktop

Na visão Fila de `/work` e `/work/tasks/:id`, o sistema SHALL exibir o painel de detalhe em viewport `lg+` quando o detalhe estiver aberto, inclusive sem tarefa selecionada (empty state). A lista MUST expandir horizontalmente somente quando o operador colapsar o detalhe via toggle. Ao carregar `/work` na Fila desktop com tarefas na fila e sem id no path, o sistema SHALL auto-selecionar a primeira tarefa e abrir o detalhe. Deep-link com `/work/tasks/:id` MUST abrir o detalhe. Fechar pelo controle de painel MUST manter a seleção na URL; fechar pelo dismiss explícito (X / limpar seleção) MUST voltar a `/work` preservando filtros na query.

#### Scenario: Operador fecha o detalhe e a lista ganha espaço

- **WHEN** há tarefa selecionada em viewport `lg+` e o detalhe está aberto
- **THEN** ao acionar o toggle de painel o detalhe some e a lista ocupa a largura disponível sem limpar o path `/work/tasks/:id`

#### Scenario: Auto-seleção no carregamento da Fila

- **WHEN** o operador abre `/work` em desktop na visão Fila com tarefas na fila e sem id no path
- **THEN** a primeira tarefa é selecionada
- **AND** o painel de detalhe abre ao lado da lista

#### Scenario: Empty state sem seleção com detalhe aberto

- **WHEN** o detalhe está aberto em desktop e não há tarefa selecionada
- **THEN** o painel de detalhe permanece visível com empty state orientando a seleção

#### Scenario: Deep-link abre o detalhe

- **WHEN** o operador navega para `/work/tasks/{id}` válido
- **THEN** o painel de detalhe abre com essa tarefa
