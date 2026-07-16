## ADDED Requirements

### Requirement: Contexto operacional persistente e tenant-safe
O sistema SHALL manter visíveis, conforme a superfície, o escritório ativo, cliente/contribuinte, competência ou período, ambiente e origem/cobertura do dado, sem aceitar `office_id` livre nem misturar contexto de tenants após troca explícita.

#### Scenario: Lista fiscal por competência
- **WHEN** o usuário abre uma carteira fiscal com competência selecionada
- **THEN** a toolbar identifica competência, escritório da sessão e origem/cobertura aplicável sem ocupar colunas da tabela

#### Scenario: Detalhe de cliente
- **WHEN** o usuário navega entre seções de `/clients/:id`
- **THEN** identidade da raiz, escritório ativo e seção permanecem reconhecíveis sem recarregar ou misturar outro cliente

#### Scenario: Troca explícita de escritório
- **WHEN** o usuário confirma outro escritório entre memberships válidas
- **THEN** seleção, paginação, detalhes e caches tenant-scoped são invalidados antes de renderizar dados do novo escritório

### Requirement: Anatomia e hierarquia uniformes em todas as páginas
Cada página autenticada SHALL usar navbar para título e uma ação primária, toolbar para subnavegação/contexto/filtros globais, corpo para utilidades e conteúdo e footer para paginação ou totalização quando aplicável; controles sem função real MUST NOT ser exibidos.

#### Scenario: Lista com criação
- **WHEN** uma lista oferece criação autorizada
- **THEN** existe no máximo uma ação primária de criação e ações adicionais ficam em menu, toolbar ou linha conforme o template

#### Scenario: Página sem filtro temporal
- **WHEN** a API não oferece período ou série temporal real
- **THEN** a toolbar não apresenta seletor temporal decorativo

#### Scenario: Ação por registro
- **WHEN** uma linha possui múltiplas ações secundárias
- **THEN** elas aparecem no fim da linha em menu acessível, com ação destrutiva separada semanticamente

### Requirement: Densidade operacional progressiva
O sistema SHALL oferecer densidade suficiente para triagem em desktop e SHALL preservar no mobile identidade, estado, prazo ou valor e ação principal, movendo informação secundária para detalhe responsivo sem depender somente de cor ou ícone.

#### Scenario: Tabela densa em desktop
- **WHEN** uma lista operacional é exibida em largura `lg` ou superior
- **THEN** o usuário consegue comparar registros prioritários sem abrir cada detalhe e identificadores técnicos permanecem secundários

#### Scenario: A mesma lista em mobile
- **WHEN** a lista é exibida abaixo de `lg`
- **THEN** as informações essenciais e o controle de abrir o detalhe permanecem acessíveis sem rolagem horizontal obrigatória

### Requirement: Filtros e totalizações próximos dos dados
Listas de alto volume SHALL apresentar busca e filtros server-side frequentes próximos da tabela e SHALL apresentar contagem, paginação/cursor e totalizações somente quando derivadas do mesmo escopo real consultado.

#### Scenario: Filtro frequente por situação
- **WHEN** o usuário altera situação, competência, cliente ou outro critério frequente
- **THEN** a lista reinicia paginação/cursor conforme seu contrato e consulta somente o escritório ativo

#### Scenario: Total monetário disponível
- **WHEN** a API retorna total monetário do filtro completo
- **THEN** o rodapé identifica o total e seu escopo sem somar apenas a página no navegador

#### Scenario: Total global indisponível
- **WHEN** uma API cursor-based não fornece total do universo
- **THEN** a interface informa somente quantidade carregada e disponibilidade de mais resultados, sem estimativa inventada

### Requirement: Calendário operacional em múltiplas escalas
O sistema SHALL oferecer em `/work/calendar` visões `Mês`, `Semana` e `Dia`, navegação por data, filtros de departamento/responsável/cliente/status/risco e painel da data selecionada, usando prazos reais e sem simular horários de compromisso inexistentes.

#### Scenario: Visão mensal
- **WHEN** o usuário escolhe `Mês`
- **THEN** cada dia mostra contagens e severidade de prazos com acesso ao conjunto correspondente

#### Scenario: Visão semanal
- **WHEN** o usuário escolhe `Semana`
- **THEN** tarefas são agrupadas por dia e ordenadas por risco/prioridade, sem posicionamento em faixas horárias fictícias

#### Scenario: Visão diária
- **WHEN** o usuário escolhe `Dia`
- **THEN** a página mostra a fila detalhada da data, ações permitidas e acesso ao mestre–detalhe da tarefa

#### Scenario: Mobile
- **WHEN** o calendário é usado em viewport móvel
- **THEN** o seletor de data e a lista do dia permanecem operáveis e o detalhe abre em slideover ou drawer

### Requirement: Fluxos complexos com divulgação progressiva
Importações, geração por modelo, exportações configuráveis e outros fluxos com preview SHALL apresentar etapas explícitas de seleção, configuração, validação, confirmação e resultado, preservando dados não sensíveis após erro e mantendo o backend como autoridade.

#### Scenario: Importação de arquivo
- **WHEN** o usuário seleciona XML ou ZIP permitido
- **THEN** a interface mostra arquivo, limites e configuração antes de permitir confirmação

#### Scenario: Validação falha
- **WHEN** o backend rejeita campo, arquivo ou conflito
- **THEN** a etapa atual permanece aberta, erros são associados ao contexto correto e nenhuma conclusão é apresentada

#### Scenario: Processamento assíncrono
- **WHEN** a confirmação cria um job ou lote
- **THEN** a interface navega ou vincula ao acompanhamento real de progresso e resultado

### Requirement: Formulários e autenticação especializados
Login e desafio 2FA SHALL usar `UAuthForm`; setup 2FA e formulários multi-etapa SHALL usar `UForm` com schema tipado e `UStepper` quando aplicável; formulários settings SHALL mapear 422 por campo e conflitos 409 sem descartar entrada não sensível.

#### Scenario: Login inválido
- **WHEN** credenciais são rejeitadas
- **THEN** `UAuthForm` mantém e-mail, limpa somente o que for necessário e apresenta erro acessível sem deslocamento incoerente de foco

#### Scenario: Setup 2FA
- **WHEN** ADMIN precisa configurar segundo fator
- **THEN** a interface apresenta etapas de confirmação de senha, QR/código e códigos de recuperação, impedindo avanço sem validação da etapa atual

#### Scenario: Erro 422 em Settings
- **WHEN** a API retorna erro por campo
- **THEN** a mensagem aparece no `UFormField` correspondente e valores não sensíveis permanecem editáveis

### Requirement: Overlays coerentes com a natureza da tarefa
O sistema SHALL usar modal para confirmação/formulário curto, slideover para detalhe secundário, drawer para interação móvel e rota dedicada para fluxo longo ou canônico, mantendo foco, teclado, retorno ao acionador e contexto da lista.

#### Scenario: Detalhe de registro em desktop
- **WHEN** um detalhe não exige abandonar a lista
- **THEN** ele abre em painel adjacente ou slideover conforme o arquétipo e a lista preserva filtros e seleção

#### Scenario: Detalhe no mobile
- **WHEN** a mesma ação ocorre abaixo de `lg`
- **THEN** o detalhe usa slideover/drawer ou rota canônica e oferece fechamento visível com retorno de foco

#### Scenario: Confirmação destrutiva
- **WHEN** o usuário solicita ação irreversível
- **THEN** um modal identifica alvo e consequência e exige confirmação semanticamente `error`

### Requirement: Cobertura integral do inventário de páginas
Nenhum arquivo de página do inventário da change SHALL ser considerado concluído sem revisão de arquétipo, contexto, estados, permissões, responsividade e teste aplicável, mesmo que reutilize um componente compartilhado ou apenas redirecione.

#### Scenario: Página fina que delega a componente
- **WHEN** o arquivo da rota apenas renderiza um workspace ou painel compartilhado
- **THEN** seu aceite verifica rota, estado inicial, props de visão e comportamento do componente no contexto daquele destino

#### Scenario: Página protegida
- **WHEN** uma rota administrativa é revisada
- **THEN** o aceite inclui acesso autorizado, acesso negado, 2FA quando exigido e ausência de conteúdo tenant-scoped indevido

#### Scenario: Alias legado
- **WHEN** uma rota legada é aberta com identificador válido ou inválido
- **THEN** ela redireciona ao destino canônico sem propagar estado inseguro nem renderizar shell duplicado
