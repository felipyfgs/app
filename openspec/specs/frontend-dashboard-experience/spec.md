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
O sistema SHALL apresentar Clientes, Notas, Exportações e Sincronizações com cabeçalho, bordas, densidade e paginação consistentes com o template, preservando o modelo server-side de cada API. Em Notas, chips de situação MUST usar o vocabulário NFS-e Nacional após o alinhamento de status.

#### Scenario: Lista de clientes paginada
- **WHEN** o usuário muda a página da lista de Clientes
- **THEN** o sistema solicita a página à API, mantém busca e filtros e não pagina localmente apenas os registros já recebidos

#### Scenario: Lista paginada por cursor
- **WHEN** o usuário carrega mais Notas
- **THEN** o sistema usa o cursor da API e mantém filtros, inclusive filtro de situação operacional

#### Scenario: Coluna secundária no mobile
- **WHEN** uma tabela é exibida em viewport móvel
- **THEN** identidade, estado e ação principal permanecem disponíveis e colunas secundárias são ocultadas ou transferidas ao detalhe

#### Scenario: Controle sem função real
- **WHEN** uma lista não possui ação em massa ou preferência de colunas funcional
- **THEN** o sistema não exibe seleção em massa ou seletor de colunas apenas para imitar o template

#### Scenario: Seleção em Notas com export
- **WHEN** o usuário autorizado pode exportar a partir do catálogo de Notas
- **THEN** a seleção em massa é permitida e cada seleção leva a exportação de filtro ou de chaves, nunca a um estado sem ação

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
O sistema SHALL apresentar Notas como catálogo mestre–detalhe, com painel de lista e painel adjacente redimensionável em desktop e detalhe em slideover/modal em viewport menor que `lg` (ou padrão já adotado no painel), mantendo `/notes/:accessKey` como rota canônica da seleção. Chips de situação na lista MUST usar o vocabulário operacional Autorizada/Cancelada/Em revisão.

#### Scenario: Seleção de nota em desktop
- **WHEN** o usuário seleciona uma nota em viewport `lg` ou maior
- **THEN** a rota muda para `/notes/:accessKey`, a linha fica selecionada e o detalhe aparece no painel adjacente sem desmontar o catálogo

#### Scenario: Seleção de nota no mobile
- **WHEN** o usuário seleciona uma nota em viewport menor que `lg`
- **THEN** a rota muda para `/notes/:accessKey` e o detalhe abre em slideover/modal que pode ser fechado por teclado ou controle visível

#### Scenario: Catálogo sem seleção no desktop
- **WHEN** nenhuma nota está selecionada em viewport `lg` ou maior
- **THEN** o painel de detalhe apresenta estado neutro orientando a selecionar uma nota

#### Scenario: Navegação pelo teclado
- **WHEN** o foco está no catálogo e o usuário usa os comandos de seleção documentados
- **THEN** a seleção avança ou recua entre registros visíveis e o item selecionado permanece visível

#### Scenario: Abertura direta do detalhe
- **WHEN** o usuário abre diretamente `/notes/:accessKey`
- **THEN** o sistema carrega o detalhe autorizado e disponibiliza retorno ao catálogo sem revelar existência de nota de outro escritório

#### Scenario: Chip operacional na grade
- **WHEN** a grade de notas renderiza a coluna de situação
- **THEN** o chip usa Autorizada, Cancelada ou Em revisão conforme o `status` granular da nota

### Requirement: Detalhe de Cliente organizado por seções
O sistema SHALL apresentar o detalhe de Cliente em página dedicada no arquétipo Settings com subnavegação para `Resumo`, `Cadastro`, `Estabelecimentos`, `Certificado A1` e `Sincronização`, condicionando conteúdo e ações às permissões e mantendo a seção reproduzível na URL.

#### Scenario: Abertura do cliente
- **WHEN** o usuário abre `/clients/:id` sem seção especificada
- **THEN** o sistema apresenta Resumo com identidade da raiz, estado e progresso do onboarding

#### Scenario: Seção Cadastro
- **WHEN** o usuário autorizado abre a seção Cadastro
- **THEN** o sistema apresenta dados da raiz, estado, origem/atualização e contatos em cards Settings, editáveis somente para administrador ou operador

#### Scenario: Operador sem gestão de credencial
- **WHEN** um usuário que não pode gerir A1 visualiza o onboarding
- **THEN** a etapa informa que o certificado é gerenciado por `ADMIN` sem expor formulário sensível nem representar falta de permissão como falha operacional

#### Scenario: Seção reproduzível
- **WHEN** o usuário abre uma URL válida da seção Cadastro, Estabelecimentos, Certificado A1 ou Sincronização
- **THEN** a toolbar destaca a seção e o corpo renderiza somente o conteúdo correspondente

### Requirement: Criação assistida permanece focada e transacional
O sistema SHALL apresentar a criação de Cliente em modal derivado de `customers/AddModal.vue`, solicitar o CNPJ completo e oferecer os dados básicos do onboarding, contato responsável, notas, A1 autorizado e campos adicionais sem reproduzir a ficha cadastral pública completa.

#### Scenario: Prévia encontrada
- **WHEN** a consulta de CNPJ retorna dados sanitizados
- **THEN** o modal preenche razão social e nome fantasia editáveis e não exige redigitar o CNPJ

#### Scenario: Consulta falha sem perder trabalho
- **WHEN** a consulta externa falha depois de o usuário preencher campos
- **THEN** o modal mantém valores não sensíveis, informa o fallback e permite continuar manualmente

#### Scenario: CNPJ já pertence a cliente do escritório
- **WHEN** a API informa que a raiz já está cadastrada no escritório ativo
- **THEN** o modal não cria duplicata e oferece abrir a seção Estabelecimentos do Cliente existente

#### Scenario: Criação concluída
- **WHEN** a API cria Cliente e primeiro Estabelecimento
- **THEN** a interface fecha e limpa o modal, informa sucesso e navega ao detalhe do novo Cliente

#### Scenario: Contato responsável opcional
- **WHEN** o usuário informa nome e ao menos um canal do contato interno responsável
- **THEN** a interface envia o contato separado do e-mail e telefone públicos e a API o cria na mesma transação do cadastro inicial

#### Scenario: Certificado opcional autorizado
- **WHEN** um administrador com 2FA informa PFX e senha válidos no modal
- **THEN** a interface cria o cadastro básico, ativa o A1 pelo endpoint protegido, limpa o material sensível e navega ao Cliente sem expor senha ou PFX

#### Scenario: Campo adicional secreto
- **WHEN** um administrador autorizado adiciona um campo do tipo Segredo
- **THEN** a interface envia o valor somente na gravação e, depois, apresenta apenas rótulo e estado configurado sem recuperar o conteúdo

### Requirement: Manutenção cadastral completa segue o arquétipo Settings
O sistema SHALL oferecer formulários completos de Cliente, contatos, campos adicionais e estabelecimentos usando `UForm`, cards e overlays reconhecíveis do template fixado, sem transformar overlays focados em fichas públicas extensas.

#### Scenario: Edição da raiz
- **WHEN** um administrador ou operador edita dados na seção Cadastro
- **THEN** os campos são agrupados semanticamente, erros locais e 422 aparecem junto ao campo e valores válidos permanecem após falha

#### Scenario: Edição de estabelecimento
- **WHEN** um administrador ou operador abre um estabelecimento
- **THEN** a interface apresenta identidade imutável, dados cadastrais, endereço, contato público e habilitação de captura sem permitir alterar raiz ou NSU

#### Scenario: Viewer consulta cadastro
- **WHEN** um `VIEWER` abre Cadastro ou Estabelecimentos
- **THEN** a mesma informação autorizada aparece em modo somente leitura sem botões de salvar, criar, inativar ou habilitar captura

#### Scenario: Contato interno e contato público
- **WHEN** contatos aparecem no detalhe
- **THEN** a interface distingue visual e semanticamente contatos internos editáveis de telefone/e-mail públicos do estabelecimento

### Requirement: Proveniência e elegibilidade são visíveis sem depender de cor
O sistema SHALL apresentar origem e data da última consulta cadastral, situação externa, estado interno e habilitação da captura como conceitos distintos, por texto e ícone além da cor.

#### Scenario: Dados externos possivelmente defasados
- **WHEN** o cadastro possui origem externa e data de atualização
- **THEN** a interface mostra a fonte e a data sem afirmar atualização em tempo real

#### Scenario: Captura desabilitada
- **WHEN** um estabelecimento está cadastrado mas não elegível para captura
- **THEN** a interface explica qual condição falhou, remove o disparo disponível e preserva acesso ao cadastro e histórico

#### Scenario: Situação desconhecida
- **WHEN** o cadastro manual não possui situação externa confirmada
- **THEN** a interface apresenta “Não consultada” ou equivalente e não a confunde com baixa, inaptidão ou falha operacional

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

### Requirement: Home com alerta de backup e atalho da inbox
O sistema SHALL apresentar no dashboard home o estado de backup da instância (ok, atrasado ou nunca) e um bloco de atenção operacional com os itens mais graves da inbox e link para a lista completa de saúde, sem botões de restore em produção.

#### Scenario: Backup atrasado no home
- **WHEN** o resumo indica backup `stale` ou `never`
- **THEN** o home exibe alerta visual de severidade adequada e não mostra a chave mestra nem caminhos de dump

#### Scenario: Itens críticos na inbox
- **WHEN** a inbox retorna itens de severidade crítica ou alta
- **THEN** o home lista um subconjunto priorizado com deep-link funcional para o destino do item

### Requirement: Lista de saúde operacional
O sistema SHALL oferecer uma lista server-side de itens da inbox com filtros de severidade e tipo refletidos na URL, estados de carregamento/vazio/erro e paginação ou cursor alinhados à API, no visual de tabela administrativa do template.

#### Scenario: Filtro por severidade
- **WHEN** o usuário aplica filtro `critical` na lista de saúde
- **THEN** a URL reflete o filtro, a API é consultada de novo e apenas itens críticos são exibidos

#### Scenario: Lista vazia saudável
- **WHEN** a inbox não retorna itens
- **THEN** a UI mostra estado vazio positivo (sem problemas operacionais) sem inventar alertas cosméticos

### Requirement: Slideover de alertas alimentado pela inbox
O sistema SHALL preferir a inbox operacional como fonte do painel global de alertas e SHALL degradar de forma sanitizada se a inbox falhar, sem exibir segredos nem corpo bruto de erros remotos.

#### Scenario: Carregamento do slideover
- **WHEN** o usuário abre o painel de alertas
- **THEN** os itens exibidos correspondem a entradas da inbox ou a um fallback explícito de erro de carga

#### Scenario: Clique no alerta de cursor
- **WHEN** o usuário ativa um alerta de cursor bloqueado
- **THEN** a navegação leva ao destino de sincronização do cliente associado

### Requirement: Status de backup na Administração
O sistema SHALL exibir, na área de Administração restrita a `ADMIN` com segundo fator quando exigido, o último backup `SUCCESS`, o último restore drill e o estado de atraso, em modo somente leitura.

#### Scenario: Admin com 2FA
- **WHEN** um administrador autorizado abre Administração
- **THEN** o card de backup mostra timestamps e status sem oferecer restore pela UI

#### Scenario: Não administrador
- **WHEN** um `OPERATOR` ou `VIEWER` tenta a rota de Administração
- **THEN** o conteúdo administrativo de backup não é oferecido além do alerta já presente no home

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

### Requirement: Superfície Documentos em /docs
O sistema SHALL apresentar o catálogo fiscal na rota canônica `/docs` com a mesma base operacional da antiga tela de notas (lista, filtros, insights, detalhe, export), sob o rótulo de navegação **Documentos**.

#### Scenario: Navegação principal
- **WHEN** o usuário autenticado abre o menu principal
- **THEN** existe o destino Documentos apontando para `/docs`

#### Scenario: Redirect de /notes
- **WHEN** o usuário acessa `/notes` ou `/notes/:accessKey`
- **THEN** é redirecionado para `/docs` ou `/docs/:accessKey` preservando query string quando aplicável

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo e exibir o tipo de cada linha somente para `NFSE`, `NFE`, `NFCE` e `CTE`. Tipos com captura habilitada MUST listar dados reais; tipos sem captura MUST manter empty state informativo. MDF-e MUST NOT aparecer como opção operacional.

#### Scenario: Tipo sem captura
- **WHEN** o operador filtra por kind sem captura habilitada ou sem dados
- **THEN** a UI explica a indisponibilidade sem erro

#### Scenario: NFS-e com dados
- **WHEN** o operador filtra por NFS-e (ou Todos, com apenas NFS-e populado)
- **THEN** a lista mostra documentos NFS-e com coluna/badge de tipo

#### Scenario: NF-e com captura
- **WHEN** a captura DistDFe está habilitada e há documentos
- **THEN** o filtro NF-e mostra linhas com badge NFE e não exibe “em breve” como único estado

#### Scenario: MDF-e ausente
- **WHEN** o operador abre os filtros e estados do catálogo
- **THEN** MDF-e não é apresentado como opção disponível ou futura

### Requirement: Manifestação no detalhe do documento
O sistema SHALL oferecer no detalhe de NF-e (quando pendente de manifestação) ações de ciência/confirmação/desconhecimento/não realizada para perfis autorizados, com confirmação e feedback de sucesso/erro sanitizado.

#### Scenario: Operador manifesta ciência
- **WHEN** o operador confirma ciência em uma NF-e resumo
- **THEN** a UI envia a ação, atualiza o estado e não exibe material de certificado

### Requirement: Sincronizações multi-canal
O sistema SHALL apresentar status de cursors SEFAZ DistDFe e CT-e nas telas de sincronização e saúde de forma distinguível dos cursors ADN, sem apresentar canal MDF-e.

#### Scenario: Cursor DistDFe bloqueado
- **WHEN** o cursor DistDFe está BLOCKED
- **THEN** a UI de sync ou health mostra o canal e severidade sem dump SOAP bruto

#### Scenario: Superfície operacional sem MDF-e
- **WHEN** o usuário abre sincronização ou saúde
- **THEN** não existe filtro, status ou ação para MDF-e

### Requirement: Catálogo de notas como posto fiscal escaneável
O sistema SHALL apresentar Notas fiscais em layout de alta densidade (tabela administrativa full-width com detalhe adjacente ou drawer, ou mestre–detalhe com painel mestre de largura mínima efetiva ≥ 36% no desktop), de modo que o operador leia número, papel, contraparte, competência e valor sem abrir o XML.

#### Scenario: Linha de nota no desktop
- **WHEN** o catálogo devolve notas com projeção enriquecida
- **THEN** cada linha exibe ao menos número da NFS-e (ou fallback de chave curta), papel fiscal legível, identificação da contraparte (nome quando existir), competência e valor formatado em BRL

#### Scenario: Detalhe da nota
- **WHEN** o usuário seleciona uma nota
- **THEN** o detalhe mostra emitente e tomador com nome e CNPJ mascarado visualmente, locais quando existirem, situação e chave completa copiável, sem renderizar o XML bruto

#### Scenario: Mobile
- **WHEN** o viewport é móvel
- **THEN** identidade da nota, valor e status permanecem visíveis na lista e o detalhe usa slideover ou rota dedicada sem perda dos filtros na URL

### Requirement: Lista de clientes como posto de captura
O sistema SHALL apresentar Clientes em tabela ou lista densa com indicadores de certificado A1 e de captura/sincronização na própria linha, e SHALL exibir no topo contagens agregadas reais do escritório ativo relevantes à operação (ao menos total e situação de certificado).

#### Scenario: Triagem de A1 na lista
- **WHEN** a listagem inclui clientes com e sem credencial ACTIVE
- **THEN** o operador distingue visualmente A1 válido, ausente e em alerta de vencimento sem abrir o detalhe

#### Scenario: KPI de carteira
- **WHEN** o usuário abre a lista de Clientes
- **THEN** vê contagens derivadas dos dados do escritório (não inventadas) e pode usar busca por nome ou CNPJ

#### Scenario: Hierarquia de ações em Clientes
- **WHEN** o usuário AUTHORIZED abre Clientes
- **THEN** a navbar mantém no máximo uma ação primária de criação e as ações por linha permanecem no fim da linha ou menu de linha

### Requirement: Tipografia e ênfase semântica operacional
O sistema SHALL usar tipografia monoespaçada apenas para identificadores técnicos (CNPJ, chave de acesso, fingerprint) e SHALL apresentar nomes empresariais e números de nota em tipografia de leitura corrente, com chips de status em cores semânticas do tema.

#### Scenario: Nome vs CNPJ
- **WHEN** uma nota ou cliente possui nome e CNPJ
- **THEN** o nome é o texto principal e o CNPJ aparece formatado como secundário ou mono, não o contrário

### Requirement: Notas como posto operacional em tabela densa
O sistema SHALL apresentar o catálogo de Notas em tabela administrativa densa alinhada visualmente a Clientes (cabeçalho, densidade, bordas, estados de loading/vazio), com colunas prioritárias legíveis ao operador (número ou chave curta, papel, contraparte por nome, competência, valor, situação), tipografia mono apenas em CNPJ/chave, e paginação por cursor da API (carregar mais), sem baixar o catálogo inteiro no cliente.

#### Scenario: Linha de nota no desktop
- **WHEN** o catálogo devolve notas com projeção enriquecida
- **THEN** cada linha exibe ao menos número (ou fallback de chave curta), papel fiscal legível, contraparte (nome quando existir; CNPJ mono secundário), competência, valor em BRL e status

#### Scenario: Cursor preservado
- **WHEN** o usuário carrega mais páginas do catálogo
- **THEN** o sistema usa `next_cursor`, acumula linhas e não simula offset client-side sobre o universo do escritório

#### Scenario: Mobile
- **WHEN** o viewport é móvel
- **THEN** identidade da nota, valor e status permanecem visíveis; colunas secundárias somem ou vão ao detalhe

### Requirement: Tabs de visualização Por documento e Por empresa
O sistema SHALL oferecer no shell de Notas abas de visualização **Por documento** e **Por empresa**, refletidas na URL, sem inventar métricas.

#### Scenario: Por documento
- **WHEN** o usuário está na aba Por documento
- **THEN** vê a tabela de notas com os filtros ativos da URL

#### Scenario: Por empresa
- **WHEN** o usuário está na aba Por empresa
- **THEN** vê linhas agregadas por cliente do escritório ativo (identidade + contagem de notas no filtro) derivadas da API, não de um agrupamento incompleto só no browser

#### Scenario: Drill-down
- **WHEN** o usuário abre uma empresa a partir da aba Por empresa
- **THEN** o sistema navega para Por documento com `client_id` (e filtros compatíveis) na URL

### Requirement: Seleção em massa com exportação a partir de Notas
O sistema SHALL permitir multi-seleção de notas **já carregadas** somente quando o usuário tiver permissão de exportar, e SHALL oferecer ações reais: exportar seleção (lista limitada de chaves) e exportar filtro atual. O sistema MUST NOT exibir checkboxes cosméticos sem ação.

#### Scenario: Exportar filtro
- **WHEN** um usuário ADMIN ou OPERATOR solicita exportar com os filtros atuais do catálogo
- **THEN** o sistema cria uma exportação assíncrona com esses filtros e informa o resultado de forma observável (toast e/ou link para Exportações)

#### Scenario: Exportar seleção
- **WHEN** o usuário seleciona N notas carregadas (N ≤ teto configurado) e solicita exportar seleção
- **THEN** o sistema envia as chaves de acesso como escopo da exportação e não inclui chaves de outro escritório

#### Scenario: VIEWER
- **WHEN** o usuário é VIEWER
- **THEN** não vê multi-select de exportação nem botão de criar export a partir de Notas

#### Scenario: Acima do teto
- **WHEN** a seleção excede o teto de chaves permitido
- **THEN** o sistema impede a solicitação e explica o limite sem iniciar job

### Requirement: Detalhe de nota sem abandonar o posto
O sistema SHALL manter a rota canônica `/notes/:accessKey` e o detalhe legível (partes, locais, cStat, chave copiável, download XML auditado) em painel adjacente, drawer ou slideover, de modo que a tabela e os filtros permaneçam utilizáveis ao fechar o detalhe.

#### Scenario: Seleção a partir da tabela
- **WHEN** o usuário abre uma nota na tabela em desktop
- **THEN** a URL atualiza para `/notes/:accessKey` com query de filtros e o detalhe fica acessível sem perder o contexto da lista

#### Scenario: Mobile
- **WHEN** o viewport é menor que `lg`
- **THEN** o detalhe usa slideover e o fechamento restaura a lista com os mesmos filtros

### Requirement: Situações de NFS-e legíveis na UI
O sistema SHALL apresentar a situação da nota com labels do domínio NFS-e Nacional (Gerada, Substituta, Cancelada, Substituída, Decisão judicial, Em revisão), cores semânticas e, no detalhe, o cStat oficial quando existir.

#### Scenario: Chip na lista
- **WHEN** a listagem exibe uma nota com `status=SUBSTITUTE`
- **THEN** o chip mostra “Substituta” (ou equivalente pt-BR) e não “Cancelada”

#### Scenario: Detalhe modal
- **WHEN** o usuário abre o detalhe de uma nota com cStat 100
- **THEN** vê situação Gerada/Ativa e indicação `cStat 100` (ou texto oficial curto)

#### Scenario: Filtros e export
- **WHEN** o usuário filtra por situação no catálogo ou na exportação
- **THEN** as opções refletem situações NFS-e (sem “Autorizada” como sinônimo de nota de serviço nacional)

### Requirement: Triagem não confunde parse com situação fiscal
O sistema SHALL tratar a fila “Em revisão” como notas com situação indefinida ou parse problemático (`UNKNOWN`), e MUST NOT contar `AUTHORIZED` como revisão de NFS-e nacional.

#### Scenario: Chip Em revisão
- **WHEN** o insights calcula contagem de revisão
- **THEN** inclui apenas notas `UNKNOWN` (e critérios de parse documentados), não status de NF-e de mercadoria

### Requirement: Situação operacional Autorizada/Cancelada/Em revisão na UI de Notas
O sistema SHALL apresentar a situação da NFS-e no catálogo, chips, insights e exportação com **apenas** os labels operacionais **Autorizada**, **Cancelada** e **Em revisão**, conforme o agrupamento de domínio:

- **Autorizada** ← `ACTIVE`, `SUBSTITUTE`, `JUDICIAL`
- **Cancelada** ← `CANCELLED`, `SUPERSEDED`
- **Em revisão** ← `UNKNOWN`

O sistema MUST NOT exibir como chip principal da grade os labels por enum “Gerada”, “Substituta”, “Substituída” ou “Decisão judicial”. Essas nuances MUST aparecer no detalhe da nota (cStat, eventos, textos oficiais).

#### Scenario: Chip na lista para nota válida
- **WHEN** a listagem exibe uma nota com `status=ACTIVE` ou `status=SUBSTITUTE`
- **THEN** o chip mostra **Autorizada** (tom de sucesso) e não “Gerada” nem “Substituta”

#### Scenario: Chip na lista para nota inválida
- **WHEN** a listagem exibe uma nota com `status=CANCELLED` ou `status=SUPERSEDED`
- **THEN** o chip mostra **Cancelada** (tom de erro)

#### Scenario: Chip Em revisão
- **WHEN** a listagem exibe uma nota com `status=UNKNOWN`
- **THEN** o chip mostra **Em revisão** (tom de alerta)

### Requirement: Filtros e export usam grupos operacionais
O sistema SHALL oferecer no filtro de situação do catálogo de Notas e na tela de Exportações as opções **Autorizada**, **Cancelada** e **Em revisão** (além de “todas”), e MUST acionar a API com o grupo ou com a expansão de enums correspondente.

#### Scenario: Filtro Autorizada
- **WHEN** o usuário seleciona situação Autorizada e aplica o filtro
- **THEN** a consulta reinicia a paginação e retorna apenas notas do grupo autorizado

#### Scenario: Export com situação Cancelada
- **WHEN** o usuário gera export com filtro de situação Cancelada
- **THEN** o escopo inclui notas canceladas e supersedidas conforme o grupo

### Requirement: Insights de triagem por grupo operacional
O sistema SHALL calcular cards de triagem de notas por grupo operacional (autorizadas/válidas, canceladas, em revisão), sem contar `AUTHORIZED` de vocabulário de NF-e de mercadoria e sem exigir cards separados para Substituta/Substituída.

#### Scenario: Contagem de canceladas
- **WHEN** o insights é calculado
- **THEN** o card de canceladas inclui `CANCELLED` e `SUPERSEDED`

#### Scenario: Contagem de revisão
- **WHEN** o insights é calculado
- **THEN** o card de revisão inclui apenas `UNKNOWN` (e critérios de parse documentados no backend), não `ACTIVE` nem `SUBSTITUTE`

### Requirement: Detalhe da nota mostra operacional e oficial
O sistema SHALL, no modal/painel de detalhe da nota, exibir o badge de situação **operacional** e, em seção ou linha de situação fiscal, o **cStat** e a descrição oficial curta quando existirem, além de eventos e indicação de substituição quando aplicável.

#### Scenario: Detalhe cStat 100
- **WHEN** o usuário abre o detalhe de uma nota com cStat 100
- **THEN** vê badge **Autorizada** e indicação de situação oficial Gerada / cStat 100

#### Scenario: Detalhe cStat 101
- **WHEN** o usuário abre o detalhe de uma nota com cStat 101 e `status=SUBSTITUTE`
- **THEN** vê badge **Autorizada** e indicação de que se trata de NFS-e de substituição (cStat 101)

#### Scenario: Detalhe supersedida
- **WHEN** o usuário abre o detalhe de uma nota `SUPERSEDED`
- **THEN** vê badge **Cancelada** e texto legível de que a nota foi substituída (quando a API fornecer dados)

### Requirement: Filtros Entrada e Saída
O sistema SHALL expor na UI Documentos filtro de direção (Todas / Entradas / Saídas) além do tipo (NFS-e, NF-e, …).

#### Scenario: Filtro só entradas
- **WHEN** o operador seleciona Entradas no catálogo Documentos
- **THEN** a listagem restringe a direction=IN

### Requirement: Import de saídas
O sistema SHALL oferecer a OPERATOR upload de XML/ZIP para saídas, com resultado (importados / duplicados / erros) e sem exigir manifestação SEFAZ.

#### Scenario: Upload ZIP de saídas
- **WHEN** o operador envia um ZIP com XML de NF-e emitida
- **THEN** a UI mostra contagem de importados/duplicados/erros sem material de certificado

### Requirement: Entrega prioritária
O sistema SHALL manter o download como ação principal; ciência de unlock e MD-e opcional permanecem secundários (conforme change de manifestação/entrega).

#### Scenario: CTA principal no detalhe
- **WHEN** o operador abre o detalhe de um documento com XML no vault
- **THEN** a ação principal de download/export fica em evidência em relação a manifestação opcional

### Requirement: Documentos prioriza entrega de XML
O sistema SHALL, na UI de Documentos para NF-e, colocar como ação principal o **download** (e export quando couber). Ações de manifestação (ciência de unlock e conclusivas) MUST ser secundárias ou em seção “opcional/avançado”.

#### Scenario: Detalhe NF-e com full
- **WHEN** o full está no vault
- **THEN** o botão primário é baixar XML; não há bloqueio pedindo manifestação

#### Scenario: Detalhe só resumo com flag on
- **WHEN** só há resumo e o usuário pode desbloquear
- **THEN** existe ação secundária do tipo “Obter XML completo” com texto de que não confirma a operação

#### Scenario: Conclusivas
- **WHEN** o usuário abre ações opcionais de MD-e
- **THEN** confirmação/desconhecimento/não realizada exigem confirmação explícita e não são o fluxo default
