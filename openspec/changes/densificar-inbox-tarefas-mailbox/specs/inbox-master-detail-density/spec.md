## ADDED Requirements

### Requirement: Detalhe da fila de Tarefas é colapsável no desktop
Na visão Fila de `/work` e `/work/tasks/:id`, o sistema SHALL exibir o painel de detalhe em viewport `lg+` somente quando o detalhe estiver aberto. A lista MUST expandir horizontalmente quando o detalhe estiver fechado. O sistema MUST NOT auto-selecionar a primeira tarefa ao carregar a fila no desktop. Deep-link com `/work/tasks/:id` MUST abrir o detalhe. Fechar pelo controle de painel MUST manter a seleção na URL; fechar pelo dismiss explícito (X / limpar seleção) MUST voltar a `/work` preservando filtros na query.

#### Scenario: Operador fecha o detalhe e a lista ganha espaço
- **WHEN** há tarefa selecionada em viewport `lg+` e o detalhe está aberto
- **THEN** ao acionar o toggle de painel o detalhe some e a lista ocupa a largura disponível sem limpar o path `/work/tasks/:id`

#### Scenario: Sem auto-seleção no carregamento
- **WHEN** o operador abre `/work` em desktop com tarefas na fila e sem id no path
- **THEN** nenhuma tarefa é selecionada automaticamente e o detalhe permanece fechado

#### Scenario: Deep-link abre o detalhe
- **WHEN** o operador navega para `/work/tasks/{id}` válido
- **THEN** o painel de detalhe abre com essa tarefa

### Requirement: Detalhe da Caixa Postal é colapsável no desktop
Em `/monitoring/mailbox`, o sistema SHALL exibir o painel de detalhe em viewport `lg+` somente quando aberto. Selecionar uma mensagem MUST abrir o detalhe na rota canônica `/monitoring/mailbox/{id}`. O toggle MUST permitir fechar o painel mantendo a rota; o dismiss explícito MUST voltar a `/monitoring/mailbox`. Em viewport `<lg`, o slideover MUST continuar sendo o veículo do detalhe.

#### Scenario: Lista priorizada com detalhe fechado
- **WHEN** o operador fecha o detalhe via toggle em desktop com mensagem selecionada
- **THEN** a lista de mensagens expande e a URL pode permanecer em `/monitoring/mailbox/{id}`

#### Scenario: Seleção abre o detalhe
- **WHEN** o operador seleciona uma mensagem na lista em desktop
- **THEN** o detalhe abre ao lado da lista

### Requirement: Chrome de monitoramento da Mailbox é recolhível
A superfície de Caixa Postal SHALL permitir recolher o card/bloco de monitoramento e sync para liberar altura do inbox. O estado default MUST favorecer o inbox (recolhido ou compacto). Quando houver erro de monitoramento, o header recolhido MUST sinalizar o problema sem exigir expandir primeiro.

#### Scenario: Operador recolhe o monitoramento
- **WHEN** o bloco de monitoramento está expandido
- **THEN** o operador pode recolhê-lo e a área da lista/detalhe ganha altura

### Requirement: Controles de densidade usam Nuxt UI no padrão do Atendimento
Tarefas e Mailbox SHALL expor um `UButton` com ícone de painel (`i-lucide-panel-right` ou equivalente) e tooltip para alternar o detalhe, com estado visual ativo quando aberto, espelhando o padrão de toggle do Atendimento. Escape MUST fechar o detalhe aberto antes de outras ações de dismiss quando aplicável.

#### Scenario: Toggle visível na superfície
- **WHEN** o operador está na Fila de Tarefas ou na Caixa Postal em desktop
- **THEN** o controle de abrir/fechar detalhe está disponível na navbar/toolbar da lista ou do detalhe
