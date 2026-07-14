# Frontend Dashboard Experience

## Purpose

Experiência do painel SPA Nuxt UI: shell, navegação, tabelas, filtros, mestre–detalhe, estados e acessibilidade.

## Requirements

### Requirement: Shell autenticado equivalente ao template de referência
O sistema SHALL manter, em todas as rotas autenticadas, um shell composto por sidebar recolhível e redimensionável, navegação vertical, command palette, identidade do escritório ativo, menu do usuário e painel global de alertas, adaptado do template fixado no commit `0f30c09`.

#### Scenario: Sidebar expandida ou recolhida
- **WHEN** o usuário expande ou recolhe a sidebar em desktop
- **THEN** os mesmos destinos continuam disponíveis e os itens recolhidos possuem identificação acessível por tooltip e nome acessível

#### Scenario: Navegação móvel
- **WHEN** o usuário escolhe um destino pela sidebar em viewport móvel
- **THEN** a navegação fecha e o conteúdo de destino recebe o espaço principal da tela

#### Scenario: Escritório ativo no cabeçalho
- **WHEN** a identidade autenticada contém um escritório ativo
- **THEN** o shell exibe sua identidade sem oferecer troca arbitrária por um escritório não autorizado

### Requirement: Navegação e command palette coerentes com permissões
O sistema MUST derivar sidebar, command palette, atalhos e ações rápidas das mesmas permissões tipadas dos papéis `ADMIN`, `OPERATOR` e `VIEWER` e do estado de confirmação do segundo fator.

#### Scenario: Ação rápida permitida
- **WHEN** um `OPERATOR` abre a command palette
- **THEN** a palette oferece somente destinos e ações rápidas autorizados para esse usuário

#### Scenario: Administração sem segundo fator confirmado
- **WHEN** um `ADMIN` sem confirmação vigente tenta usar atalho ou URL da Administração
- **THEN** o sistema não renderiza conteúdo administrativo e conduz ao desafio ou apresenta acesso restrito

#### Scenario: Viewer navega pelo painel
- **WHEN** um `VIEWER` usa sidebar, command palette ou atalhos
- **THEN** não são oferecidas ações de criação, gestão de A1, sincronização manual ou exportação não autorizadas

### Requirement: Hierarquia uniforme de ações e contexto
O sistema SHALL posicionar ação primária na navbar, filtros globais ou subnavegação na toolbar, utilidades de tabela no início do corpo e ações secundárias por registro no fim da linha.

#### Scenario: Tela de lista com criação
- **WHEN** o usuário autorizado abre Clientes ou Exportações
- **THEN** a navbar apresenta no máximo uma ação primária de criação e a busca ou filtros da lista aparecem na faixa utilitária do corpo

#### Scenario: Tela com subnavegação
- **WHEN** o usuário abre o detalhe de Cliente
- **THEN** a toolbar apresenta as seções disponíveis e indica visualmente e semanticamente a seção ativa

#### Scenario: Ação destrutiva
- **WHEN** o usuário inicia uma ação destrutiva ou irreversível
- **THEN** um modal identifica alvo e consequência e exige confirmação em ação de cor semântica `error`

### Requirement: Tabelas administrativas consistentes e server-side
O sistema SHALL apresentar Clientes, Notas, Exportações e Sincronizações com cabeçalho, bordas, densidade, alinhamento, estado de carregamento e paginação visualmente consistentes com a tabela do template, preservando o modelo server-side de cada API.

#### Scenario: Lista de clientes paginada
- **WHEN** o usuário muda a página da lista de Clientes
- **THEN** o sistema solicita a página à API, mantém busca e filtros e não pagina localmente apenas os registros já recebidos

#### Scenario: Lista paginada por cursor
- **WHEN** o usuário carrega mais Notas ou Sincronizações
- **THEN** o sistema usa o cursor fornecido pela API, mantém os registros anteriores e não converte o fluxo em paginação offset fictícia

#### Scenario: Coluna secundária no mobile
- **WHEN** uma tabela é exibida em viewport móvel
- **THEN** identidade, estado e ação principal permanecem disponíveis e colunas secundárias são ocultadas ou transferidas ao detalhe

#### Scenario: Controle sem função real
- **WHEN** uma lista não possui ação em massa ou preferência de colunas funcional
- **THEN** o sistema não exibe seleção em massa ou seletor de colunas apenas para imitar o template

### Requirement: Filtros reproduzíveis e retorno de contexto
O sistema SHALL refletir na URL os filtros, busca, seção e seleção que precisem sobreviver a recarga, link compartilhado ou retorno do detalhe, sem persistir cursor quando a API não permitir retomada segura.

#### Scenario: Aplicação de filtro
- **WHEN** o usuário altera um filtro do catálogo ou uma busca de lista
- **THEN** o sistema reinicia a paginação aplicável, consulta a API e atualiza a URL sem parâmetros vazios

#### Scenario: Retorno do detalhe da nota
- **WHEN** o usuário fecha ou retorna de uma nota selecionada
- **THEN** o catálogo restaura os filtros representáveis e a posição lógica disponível sem misturar resultados de consultas diferentes

#### Scenario: URL aberta diretamente
- **WHEN** uma URL com filtros ou seção válida é aberta diretamente
- **THEN** os controles e o conteúdo inicial refletem esses parâmetros

### Requirement: Dashboard operacional baseado em dados reais
O sistema SHALL apresentar o resumo operacional em grade contínua de indicadores, seguido de alertas acionáveis e horário da atualização, usando somente métricas reais retornadas pela API do escritório ativo.

#### Scenario: Abertura do dashboard
- **WHEN** o usuário autenticado abre o dashboard
- **THEN** indicadores priorizam bloqueios, falhas, pendências e vencimentos antes de totais informativos e cada indicador leva ao módulo correspondente quando aplicável

#### Scenario: Ausência de série temporal
- **WHEN** a API fornece somente totais pontuais
- **THEN** o sistema não apresenta gráfico, variação percentual ou seletor de período com dados inventados

#### Scenario: Falha ao atualizar resumo já carregado
- **WHEN** uma atualização manual falha após um resumo válido ter sido exibido
- **THEN** o sistema mantém o resumo anterior, informa a falha sanitizada e preserva o horário da última atualização válida

### Requirement: Catálogo de Notas em mestre–detalhe responsivo
O sistema SHALL apresentar Notas como catálogo mestre–detalhe, com painel de lista e painel adjacente redimensionável em desktop e detalhe em slideover em viewport menor que `lg`, mantendo `/notes/:accessKey` como rota canônica da seleção.

#### Scenario: Seleção de nota em desktop
- **WHEN** o usuário seleciona uma nota em viewport `lg` ou maior
- **THEN** a rota muda para `/notes/:accessKey`, a linha fica selecionada e o detalhe aparece no painel adjacente sem desmontar o catálogo

#### Scenario: Seleção de nota no mobile
- **WHEN** o usuário seleciona uma nota em viewport menor que `lg`
- **THEN** a rota muda para `/notes/:accessKey` e o detalhe abre em slideover que pode ser fechado por teclado ou controle visível

#### Scenario: Catálogo sem seleção no desktop
- **WHEN** nenhuma nota está selecionada em viewport `lg` ou maior
- **THEN** o painel de detalhe apresenta estado neutro orientando a selecionar uma nota

#### Scenario: Navegação pelo teclado
- **WHEN** o foco está no catálogo e o usuário usa os comandos de seleção documentados
- **THEN** a seleção avança ou recua entre registros visíveis e o item selecionado permanece visível

#### Scenario: Abertura direta do detalhe
- **WHEN** o usuário abre diretamente `/notes/:accessKey`
- **THEN** o sistema carrega o detalhe autorizado e disponibiliza retorno ao catálogo sem revelar existência de nota de outro escritório

### Requirement: Detalhe de Cliente organizado por seções
O sistema SHALL apresentar o detalhe de Cliente em página dedicada com subnavegação para `Resumo`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, condicionando seções e ações às permissões.

#### Scenario: Abertura do cliente
- **WHEN** o usuário abre `/clients/:id` sem seção especificada
- **THEN** o sistema apresenta Resumo com identidade da raiz, estado e progresso do onboarding

#### Scenario: Operador sem gestão de credencial
- **WHEN** um usuário que não pode gerir A1 visualiza o onboarding
- **THEN** a etapa informa que o certificado é gerenciado por `ADMIN` sem expor formulário sensível nem representar falta de permissão como falha operacional

#### Scenario: Seção reproduzível
- **WHEN** o usuário abre uma URL válida da seção Estabelecimentos, Certificado A1 ou Sincronização
- **THEN** a toolbar destaca a seção e o corpo renderiza somente o conteúdo correspondente

### Requirement: Formulários e diálogos consistentes
O sistema SHALL usar `UForm`, validação local tipada quando aplicável, campos associados por nome, erros 422 junto aos campos, loading de submissão e ações de cancelar/confirmar consistentes com o template.

#### Scenario: Criação com erro local
- **WHEN** o usuário submete um formulário com campo inválido detectável localmente
- **THEN** o envio não ocorre, o erro é associado ao campo e o foco pode alcançar o primeiro erro

#### Scenario: Criação com erro 422
- **WHEN** a API rejeita um formulário com erros por campo
- **THEN** o sistema preserva valores não sensíveis e apresenta cada erro no campo correspondente

#### Scenario: Submissão em andamento
- **WHEN** uma criação ou ativação está sendo enviada
- **THEN** a ação final indica carregamento e nova submissão equivalente é impedida

#### Scenario: Fechamento da gestão de A1
- **WHEN** o usuário fecha ou conclui o modal de certificado
- **THEN** senha e referência ao arquivo PFX são removidas do estado da interface

### Requirement: Alertas operacionais distinguem vazio de erro
O sistema SHALL carregar alertas ao abrir o slideover e apresentar separadamente carregamento, lista acionável, ausência de ocorrências e falha de consulta.

#### Scenario: Alertas disponíveis
- **WHEN** existem bloqueios, vencimentos ou falhas recentes
- **THEN** cada item apresenta severidade, título, resumo sanitizado, horário e destino operacional

#### Scenario: Nenhum alerta
- **WHEN** a consulta conclui com sucesso sem ocorrências
- **THEN** o sistema apresenta estado saudável sem alegar que dados falhos foram carregados

#### Scenario: Falha ao consultar alertas
- **WHEN** uma das fontes necessárias não pode ser carregada
- **THEN** o slideover apresenta erro sanitizado e ação de tentar novamente em vez de “nenhum alerta recente”

### Requirement: Estados assíncronos preservam trabalho válido
O sistema SHALL distinguir carregamento inicial, atualização, processamento, sucesso, falha, bloqueio e expiração por texto, ícone e cor, preservando dados válidos durante atualizações transitórias.

#### Scenario: Exportação pendente
- **WHEN** uma exportação permanece `PENDING` ou `PROCESSING`
- **THEN** a lista continua utilizável, indica o estado e atualiza somente enquanto houver item pendente

#### Scenario: Cursor bloqueado
- **WHEN** um estabelecimento atinge `BLOCKED` após falhas consecutivas de decodificação
- **THEN** a interface destaca bloqueio e motivo sanitizado sem oferecer avanço, edição ou salto manual de NSU

#### Scenario: Exportação expirada
- **WHEN** uma exportação pronta ultrapassa sua expiração
- **THEN** o download deixa de ser ação disponível e o estado informa que um novo pacote deve ser solicitado

### Requirement: Tema e identidade visual controlados
O sistema SHALL preservar modo claro/escuro, preferência persistida, fonte Public Sans e tokens semânticos do Nuxt UI, usando paleta que mantenha contraste adequado.

#### Scenario: Alternância de aparência
- **WHEN** o usuário alterna entre modo claro e escuro
- **THEN** o shell e todas as telas atualizam cores sem perder legibilidade e a preferência permanece em nova navegação

#### Scenario: Personalização de paleta
- **WHEN** o produto oferecer escolha de cor primária ou neutra
- **THEN** somente combinações validadas para contraste ficam disponíveis

### Requirement: Acessibilidade por teclado e semântica
O sistema MUST fornecer nomes acessíveis a controles icônicos, foco visível, ordem de tabulação coerente, associação entre campos e erros e operação por teclado de menus, tabelas selecionáveis, modais e slideovers.

#### Scenario: Controle somente com ícone
- **WHEN** um leitor de tela encontra atualizar, alertas, voltar, fechar ou menu de linha
- **THEN** o controle possui nome acessível que descreve a ação sem depender do tooltip

#### Scenario: Diálogo por teclado
- **WHEN** um modal ou slideover é aberto por teclado
- **THEN** o foco entra no diálogo, permanece contido, permite fechamento e retorna ao acionador

#### Scenario: Estado sem depender de cor
- **WHEN** sucesso, aviso, falha, bloqueio ou seleção é apresentado
- **THEN** texto ou ícone comunica o estado além da cor

### Requirement: Responsividade verificável
O sistema SHALL permitir concluir login, navegação, cadastro de cliente, consulta e download de nota, solicitação de exportação e inspeção de sincronização em 390×844, e SHALL preservar uso essencial em largura de 360 px sem rolagem horizontal da página.

#### Scenario: Fluxo principal em 390×844
- **WHEN** os testes de navegador executam um fluxo principal em 390×844
- **THEN** campos, mensagens, ações e conteúdo necessário permanecem visíveis e acionáveis sem sobreposição

#### Scenario: Largura mínima de 360 px
- **WHEN** uma tela principal é inspecionada com largura de 360 px
- **THEN** o documento não exige rolagem horizontal e controles não ficam fora da viewport

### Requirement: Proteção de dados e isolamento na interface
O sistema MUST mostrar somente dados do escritório ativo e MUST NOT renderizar PFX, senha, chave privada, PEM, XML fiscal bruto ou resposta ADN não sanitizada em páginas, notificações, erros, logs do navegador ou artefatos de teste.

#### Scenario: Falha na ativação de certificado
- **WHEN** o envio ou ativação do A1 falha
- **THEN** a interface apresenta mensagem sanitizada e metadados públicos sem oferecer recuperação do certificado ou senha

#### Scenario: Nota de outro escritório
- **WHEN** o usuário abre uma chave inexistente ou pertencente a outro escritório
- **THEN** a interface apresenta o mesmo estado de não encontrado sem revelar a qual escritório a nota pertence

#### Scenario: Mudança de identidade ou logout
- **WHEN** a identidade autenticada muda ou a sessão termina
- **THEN** dados, seleção, alertas e estado sensível da sessão anterior deixam de ser apresentados antes de nova consulta

### Requirement: Validação automatizada da refatoração
O sistema SHALL possuir lint, typecheck, testes de componentes e Playwright cobrindo os padrões de tela, permissões, estados e viewports definidos, sem acessar ADN real ou usar certificado de homologação no CI.

#### Scenario: Suíte do frontend
- **WHEN** a validação do change é executada
- **THEN** lint, typecheck e testes de componentes passam para shell, listas, mestre–detalhe, formulários e estados assíncronos

#### Scenario: Matriz visual funcional
- **WHEN** Playwright executa os fluxos em 1440×900 e 390×844
- **THEN** navegação, Cliente, Notas, Exportações e Sincronizações cumprem os comportamentos responsivos especificados

#### Scenario: Ausência de conteúdo proibido
- **WHEN** testes exercitam erros, alertas, histórico e gestão de A1
- **THEN** asserções confirmam que material criptográfico, senha, XML bruto e resposta remota não sanitizada não aparecem na interface
