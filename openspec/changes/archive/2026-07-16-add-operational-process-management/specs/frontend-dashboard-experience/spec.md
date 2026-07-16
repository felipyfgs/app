## ADDED Requirements

### Requirement: Navegação operacional integrada ao shell
O sistema SHALL adicionar “Minha fila”, “Processos”, “Calendário” e “Modelos” à navegação autenticada existente conforme papel, preservando sidebar, command palette, identidade do escritório, menu do usuário e alertas do template fixado em `0f30c09`.

#### Scenario: Entrada de operador
- **WHEN** `OPERATOR` conclui login com escritório ativo
- **THEN** o redirect e o primeiro destino operacional levam a “Minha fila” sem remover o acesso autorizado às demais áreas

#### Scenario: Navegação de viewer
- **WHEN** `VIEWER` abre o shell
- **THEN** as rotas de consulta aparecem conforme autorização e ações de criar, editar, gerar ou executar não são oferecidas

#### Scenario: Troca explícita de escritório
- **WHEN** usuário troca para outra membership válida
- **THEN** o módulo descarta seleção/detalhe anterior e recarrega fila e contagens do novo escritório sem aceitar seletor livre

### Requirement: Minha fila no arquétipo mestre-detalhe
O sistema SHALL implementar `/work` a partir do arquétipo `inbox.vue`, com lista priorizada e detalhe da tarefa lado a lado em desktop e `USlideover` em mobile.

#### Scenario: Seleção em desktop
- **WHEN** usuário seleciona tarefa na lista em viewport desktop
- **THEN** o painel de detalhe mostra cliente, processo, prazo, status, contexto, comentários, evidências, histórico e somente ações autorizadas

#### Scenario: Seleção em mobile
- **WHEN** usuário seleciona tarefa abaixo do breakpoint do template
- **THEN** o detalhe abre em slideover acessível e pode ser fechado sem perder filtros/aba da fila

#### Scenario: Estado vazio saudável
- **WHEN** a aba não possui tarefas
- **THEN** a interface mostra estado vazio coerente, sem gerar dados mock ou confundir vazio com erro

### Requirement: Páginas operacionais baseadas nos arquétipos do template
O sistema SHALL construir processos e modelos pelo arquétipo de lista administrativa, detalhes pelo arquétipo settings/seções, modais pelo `AddModal.vue` e dashboard pelo arquétipo home, preservando a árvore de componentes, slots e classes reconhecíveis da referência.

#### Scenario: Lista de processos
- **WHEN** usuário abre `/work/processes`
- **THEN** vê navbar, toolbar de filtros, tabela, seleção/lote autorizado e paginação server-side no padrão visual de `customers.vue`

#### Scenario: Detalhe de processo
- **WHEN** usuário abre processo autorizado
- **THEN** a página usa navbar/toolbar de seções e apresenta resumo, checklist, comentários, evidências e histórico sem duplicar shell

#### Scenario: Criação por modelo
- **WHEN** usuário inicia geração por modelo
- **THEN** o fluxo usa formulário/modal validado, mostra preview e conflitos antes de habilitar confirmação

### Requirement: Calendário dentro do shell canônico
O sistema SHALL implementar o calendário com `UDashboardPanel`, `UDashboardNavbar` e `UDashboardToolbar` do arquétipo home e SHALL limitar composição nova ao corpo mensal/semanal específico do domínio.

#### Scenario: Mês com tarefas
- **WHEN** calendário recebe agregados do mês
- **THEN** cada dia apresenta contagens e o clique abre detalhe autorizado sem substituir o shell por layout paralelo

#### Scenario: Carregamento do dia
- **WHEN** o painel lateral busca itens do dia
- **THEN** a interface distingue loading, vazio, erro e sucesso e mantém filtros refletidos na URL

### Requirement: Estados, filtros e autorização coerentes
O sistema SHALL usar `useApi`, tipos explícitos, filtros em URL, loading/vazio/erro/403/409/422, feedback de submissão e ações ocultas conforme permissão, sem tratar ocultação frontend como controle de segurança.

#### Scenario: Conflito de versão
- **WHEN** API responde 409 após edição concorrente
- **THEN** a UI preserva entrada não sensível, informa o conflito e oferece recarregar o estado atual

#### Scenario: Erro de validação
- **WHEN** API responde 422 com erros por campo
- **THEN** o formulário associa mensagens aos campos e mantém valores não sensíveis

#### Scenario: Ação forjada
- **WHEN** usuário chama diretamente uma ação ausente na UI
- **THEN** o backend ainda aplica policy e rejeita a operação não autorizada

### Requirement: Interface sem mocks e sem dados sensíveis
O sistema MUST NOT copiar `server/api/*`, `TeamsMenu`, paginação client-side de demonstração, URL pública de evidência ou campos secretos para as páginas operacionais.

#### Scenario: Build de produção
- **WHEN** o frontend é gerado para produção
- **THEN** as rotas usam a API Laravel same-origin e não introduzem processo Node, endpoint mock ou seletor livre de escritório

#### Scenario: Inspeção dos recursos frontend
- **WHEN** payloads e componentes do módulo são inspecionados
- **THEN** não aparecem `vault_object_id`, path privado, PFX, PEM, senha, Consumer Secret, token, Termo XML ou bytes de evidência

