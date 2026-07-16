# Frontend Dashboard Experience

## Purpose

Experiência do painel SPA Nuxt UI: shell, navegação, tabelas, filtros, mestre–detalhe, estados e acessibilidade.
## Requirements

### Requirement: Shell autenticado equivalente ao template de referência
O sistema SHALL manter, em todas as rotas autenticadas, um shell composto por sidebar recolhível e redimensionável, navegação vertical, command palette, identidade do escritório ativo, menu do usuário e painel global de alertas, adaptado do template fixado no commit `0f30c09`. Usuários com múltiplas memberships SHALL poder trocar somente entre escritórios autorizados por um controle explícito e acessível.

#### Scenario: Sidebar expandida ou recolhida
- **WHEN** o usuário expande ou recolhe a sidebar em desktop
- **THEN** os mesmos destinos continuam disponíveis e os itens recolhidos possuem identificação acessível por tooltip e nome acessível

#### Scenario: Navegação móvel
- **WHEN** o usuário escolhe um destino pela sidebar em viewport móvel
- **THEN** a navegação fecha e o conteúdo de destino recebe o espaço principal da tela

#### Scenario: Escritório ativo no cabeçalho
- **WHEN** a identidade autenticada contém um escritório ativo
- **THEN** o shell exibe sua identidade e oferece somente memberships autorizadas quando a troca estiver disponível

#### Scenario: Troca de escritório
- **WHEN** o usuário confirma outro escritório autorizado
- **THEN** a UI invalida dados tenant-scoped, atualiza o shell e recarrega a rota sem misturar resultados anteriores

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
O sistema SHALL apresentar o detalhe de Cliente em página dedicada no arquétipo Settings com subnavegação para `Resumo`, `Cadastro`, `Estabelecimentos`, `Certificado A1`, `Captura de saídas` e `Sincronização`, condicionando conteúdo e ações às permissões e mantendo a seção reproduzível na URL.

#### Scenario: Abertura do cliente
- **WHEN** o usuário abre `/clients/:id` sem seção especificada
- **THEN** o sistema apresenta Resumo com identidade da raiz, estado e progresso do onboarding

#### Scenario: Seção Cadastro
- **WHEN** o usuário autorizado abre a seção Cadastro
- **THEN** o sistema apresenta dados da raiz, estado, origem/atualização e contatos em cards Settings, editáveis somente para administrador ou operador

#### Scenario: Operador sem gestão de credencial
- **WHEN** um usuário que não pode gerir A1 visualiza o onboarding
- **THEN** a etapa informa que o certificado é gerenciado por `ADMIN` sem expor formulário sensível nem representar falta de permissão como falha operacional

#### Scenario: Seção Captura de saídas
- **WHEN** o usuário abre a seção de captura de saídas
- **THEN** a página lista somente estabelecimentos e séries do escritório ativo, com modo/estado e ações permitidas ao papel

#### Scenario: Seção reproduzível
- **WHEN** o usuário abre uma URL válida da seção Cadastro, Estabelecimentos, Certificado A1, Captura de saídas ou Sincronização
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
O sistema SHALL apresentar o catálogo fiscal sob o destino principal **Documentos**, mantendo `/docs` como visão por empresa e `/docs/catalog` como rota canônica da visão por documento, com a mesma base operacional de lista, filtros, insights, detalhe, importação, exportação e download.

#### Scenario: Navegação principal
- **WHEN** o usuário autenticado abre o menu principal
- **THEN** existe o destino Documentos com as visões Por empresa em `/docs` e Catálogo em `/docs/catalog`, sem destino top-level por tipo documental

#### Scenario: Visão por documento
- **WHEN** o usuário abre `/docs/catalog`
- **THEN** a interface apresenta no mesmo catálogo os tipos documentais autorizados, inclusive NF-e, NFC-e e CT-e, diferenciados por `kind` e filtros

#### Scenario: Redirect de /notes
- **WHEN** o usuário acessa `/notes` ou `/notes/:accessKey`
- **THEN** é redirecionado para `/docs` ou `/docs/:accessKey` preservando query string quando aplicável

#### Scenario: Redirect legado de CT-e
- **WHEN** o usuário acessa `/settings/cte`
- **THEN** é redirecionado com substituição de histórico para `/docs/catalog` com o filtro `kind=CTE`, sem renderizar uma página CT-e em Configurações

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo e exibir o tipo de cada linha somente para `NFSE`, `NFE`, `NFCE` e `CTE`. Tipos com captura habilitada MUST listar dados reais; tipos sem captura MUST manter empty state informativo. NFC-e MA MUST distinguir canal assistido de automático, e MDF-e MUST NOT aparecer como opção operacional.

#### Scenario: Tipo sem captura
- **WHEN** o operador filtra por kind sem captura habilitada ou sem dados
- **THEN** a UI explica a indisponibilidade sem erro

#### Scenario: NFS-e com dados
- **WHEN** o operador filtra por NFS-e (ou Todos, com apenas NFS-e populado)
- **THEN** a lista mostra documentos NFS-e com coluna/badge de tipo

#### Scenario: NF-e com captura
- **WHEN** a captura DistDFe ou saída MA está habilitada e há documentos
- **THEN** o filtro NF-e mostra linhas com badge NFE e não exibe “em breve” como único estado

#### Scenario: NFC-e MA com dados
- **WHEN** há XML modelo 65 de saída persistido para estabelecimento MA
- **THEN** o filtro NFC-e mostra linhas NFCE com direção Saída e proveniência legível

#### Scenario: MDF-e ausente
- **WHEN** o operador abre os filtros e estados do catálogo
- **THEN** MDF-e não é apresentado como opção disponível ou futura

### Requirement: Manifestação no detalhe do documento
O sistema SHALL oferecer no detalhe de NF-e (quando pendente de manifestação) ações de ciência/confirmação/desconhecimento/não realizada para perfis autorizados, com confirmação e feedback de sucesso/erro sanitizado.

#### Scenario: Operador manifesta ciência
- **WHEN** o operador confirma ciência em uma NF-e resumo
- **THEN** a UI envia a ação, atualiza o estado e não exibe material de certificado

### Requirement: Sincronizações multi-canal
O sistema SHALL apresentar status de cursors SEFAZ DistDFe e CT-e e de séries outbound MA nas telas de sincronização e saúde de forma distinguível dos cursors ADN, sem apresentar canal MDF-e. Canal baseado em NSU SHALL mostrar NSU; série outbound SHALL mostrar modelo, série e posição `nNF`, nunca rotulada como NSU.

#### Scenario: Cursor DistDFe bloqueado
- **WHEN** o cursor DistDFe está BLOCKED
- **THEN** a UI de sync ou health mostra o canal e severidade sem dump SOAP bruto

#### Scenario: Série outbound bloqueada
- **WHEN** série NF-e/NFC-e MA está BLOCKED
- **THEN** a UI mostra estabelecimento, modelo, série, `nNF`, motivo sanitizado e ação permitida sem editar a posição diretamente

#### Scenario: Recuperação pendente
- **WHEN** há chaves descobertas sem XML
- **THEN** sincronização mostra contagem `XML_PENDING` separada da quantidade capturada

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
O sistema SHALL oferecer a `OPERATOR` e `ADMIN` uma superfície de importação de saídas que aceite, na mesma seleção, múltiplos XML e ZIP de NF-e 55 e NFC-e 65, apresente os limites vigentes antes do envio e crie um lote assíncrono sem exigir manifestação ou credencial SEFAZ. A opção padrão SHALL ser associar automaticamente cada item pelo CNPJ completo do emitente; a interface SHALL permitir restringir a conferência a um cliente ou estabelecimento sem substituir a identidade do XML.

#### Scenario: Upload ZIP de saídas
- **WHEN** o operador envia um ZIP com XML de NF-e 55 e/ou NFC-e 65 emitidas por estabelecimentos do escritório
- **THEN** a UI cria o lote, mostra progresso de envio e processamento e apresenta contagens de importados, duplicados, sem vínculo, divergência de cliente, inválidos, não suportados, quarentenados e falhos sem material de certificado ou XML bruto

#### Scenario: Seleção mista de arquivos
- **WHEN** o usuário seleciona vários XML e ZIP no mesmo envio
- **THEN** a interface mostra quantidade e tamanho total, valida os limites observáveis no navegador e envia todos como um lote, mantendo a validação do backend como autoridade

#### Scenario: ZIP multiempresa com associação automática
- **WHEN** nenhum cliente é usado como restrição e o ZIP contém emitentes distintos
- **THEN** a interface informa que cada item será associado pelo `emit/CNPJ` e exibe o cliente/estabelecimento resolvido no resultado de cada item

#### Scenario: Cliente usado como restrição
- **WHEN** o usuário seleciona um cliente para conferir o lote
- **THEN** a UI deixa claro que XML de emitente divergente será marcado como `CLIENT_MISMATCH`, sem oferecer associação forçada

#### Scenario: Modal fechado durante processamento
- **WHEN** o usuário fecha a superfície de upload depois que a API aceitou o lote
- **THEN** o processamento continua e a interface oferece acesso ao lote pelo histórico sem reenviar os arquivos

#### Scenario: Arquivo excede limite conhecido
- **WHEN** a seleção excede quantidade ou tamanho total informado pela API
- **THEN** a interface impede o envio, identifica o limite excedido e mantém a seleção editável

#### Scenario: VIEWER
- **WHEN** o usuário é `VIEWER`
- **THEN** não vê ação de importar ou repetir lote e uma tentativa por URL/API recebe 403

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

### Requirement: Card de recovery NFC-e via SVRS
O detalhe de Sincronização do estabelecimento SHALL apresentar card “XML NFC-e via SVRS” com estado da integração, elegibilidade, modo manual/automático, backlog, última tentativa/captura, próximo retry, breaker e motivo sanitizado, seguindo o template de dashboard fixado.

#### Scenario: Perfil elegível e saudável
- **WHEN** o estabelecimento MA possui perfil 65 ativo, A1 válido e canal habilitado
- **THEN** o card mostra integração disponível, progresso real e ações autorizadas sem exibir A1 ou chave completa

#### Scenario: Modelo 55
- **WHEN** o usuário visualiza uma série NF-e 55
- **THEN** a interface não oferece download SVRS e explica que este canal atende somente NFC-e 65

### Requirement: Lista de pendências e tentativas
A interface SHALL oferecer lista server-side de recoveries com filtros de estado/motivo, paginação, estado vazio, erro, deep-link para estabelecimento e ações por registro coerentes com a API.

#### Scenario: Pendência em retry
- **WHEN** uma recuperação está `RETRY_SCHEDULED`
- **THEN** a linha mostra tentativa, próximo horário e ação permitida sem simular captura concluída

#### Scenario: Falha ao carregar
- **WHEN** a API de recoveries falha
- **THEN** a UI mantém dados válidos anteriores, informa erro sanitizado e oferece tentar novamente

### Requirement: Ações por papel e 2FA
A UI MUST ocultar e bloquear controles administrativos de flag, allowlist, kill switch e breaker para quem não é ADMIN com 2FA recente. OPERATOR SHALL receber retry e fallback somente quando elegíveis; VIEWER MUST permanecer somente leitura.

#### Scenario: Operator com fallback disponível
- **WHEN** o canal está bloqueado e o OPERATOR visualiza a pendência
- **THEN** a interface oferece upload XML/ZIP existente e não oferece resetar breaker

#### Scenario: Admin sem 2FA recente
- **WHEN** ADMIN tenta alterar allowlist ou kill switch sem segundo fator vigente
- **THEN** a interface conduz ao desafio e não envia a mutação

### Requirement: Estados honestos de descoberta e captura
A interface MUST distinguir `Chave descoberta`, `XML pendente`, `Em recuperação`, `XML capturado`, `Fallback necessário` e `Bloqueado`, sem usar sucesso de consulta de protocolo como sucesso de XML.

#### Scenario: Chave descoberta sem XML
- **WHEN** o número possui `KEY_DISCOVERED` e recovery ainda não concluído
- **THEN** a UI mostra XML pendente e não habilita download do documento inexistente

### Requirement: Conteúdo remoto e segredos nunca renderizados
A interface MUST NOT receber ou renderizar HTML/JavaScript remoto, XML fiscal bruto, PFX, senha, PEM, cookie, token, `vault_object_id` ou mensagem remota não sanitizada. A chave, quando necessária à identificação autorizada, SHALL usar formato mascarado conforme política existente.

#### Scenario: Parser retorna contrato alterado
- **WHEN** a API informa `RESPONSE_CONTRACT_CHANGED`
- **THEN** a UI exibe texto local sanitizado e não injeta o corpo retornado pela SVRS no DOM

### Requirement: Fallback assistido integrado
A experiência SHALL ligar uma recuperação indisponível ao upload em massa XML/ZIP existente, preservar o contexto do cliente/estabelecimento e atualizar o estado quando a mesma chave for ingerida.

#### Scenario: Upload fecha pendência
- **WHEN** o operador conclui upload válido da chave pendente
- **THEN** o card e a lista atualizam para XML capturado por fallback, mantendo a proveniência correta

### Requirement: Configuração de captura de saídas MA no arquétipo Settings
O sistema SHALL apresentar no detalhe do cliente uma seção reproduzível de captura de saídas, organizada por estabelecimento, ambiente, modelo e série, mostrando modo `ASSISTED|AUTOMATIC`, estado do A1/CSC sem valores, semente, posição `nNF`, última execução, próxima tentativa, lacunas e bloqueios. A estrutura visual MUST seguir o template Nuxt UI fixado no repositório.

#### Scenario: Série NF-e configurada
- **WHEN** o usuário abre uma série modelo 55
- **THEN** vê semente, número inicial/posição atual, captura e pendências sem campo ou estado de CSC

#### Scenario: Série NFC-e configurada
- **WHEN** o usuário abre uma série modelo 65
- **THEN** vê estado do CSC somente como configurado/ausente quando aplicável, nunca seu valor

#### Scenario: Modo assistido
- **WHEN** não existe contrato M2M aprovado para a plataforma MA
- **THEN** a UI rotula claramente `Assistido`, orienta obtenção/upload do pacote oficial e não usa texto de sincronização automática

#### Scenario: Modo automático
- **WHEN** contrato M2M, flag, perfil e allowlist estão válidos
- **THEN** a UI mostra `Automático`, competência coberta, próxima execução e última recuperação concluída

#### Scenario: Lacuna esgotada
- **WHEN** um número chega a `EXHAUSTED_VISIBLE`
- **THEN** a série exibe o `nNF`, dez tentativas, último resultado e ação de revisão permitida sem ocultar a lacuna

### Requirement: Ações da captura MA respeitam papel e 2FA
O sistema SHALL permitir upload de semente/pacote e consulta somente leitura a OPERATOR/ADMIN quando elegíveis, e MUST restringir cadastro de CSC, ativação de produção, mandato, allowlist, reset, fallback mutante e kill switch a ADMIN com 2FA recente e confirmação explícita.

#### Scenario: Operador envia pacote oficial
- **WHEN** OPERATOR autorizado seleciona estabelecimento/competência e envia ZIP oficial MA
- **THEN** a UI mostra progresso e resultado por XML sem material de certificado ou payload bruto

#### Scenario: Operador tenta ativar perfil
- **WHEN** OPERATOR tenta habilitar produção, alterar mandato ou resetar sequência
- **THEN** a ação não é oferecida ou retorna 403 sem alteração parcial

#### Scenario: Admin reseta sequência
- **WHEN** ADMIN com 2FA recente confirma posição e motivo do reset
- **THEN** a UI envia a ação protegida, mantém histórico visível e informa que resultados fiscais anteriores não serão refeitos

#### Scenario: Confirmação mutante
- **WHEN** ADMIN autorizado abre ação de inutilização/sonda experimental
- **THEN** a UI apresenta série/período, riscos fiscais, gates e efeito irreversível, exigindo confirmação específica; confirmação genérica de modal não basta

### Requirement: Operação e catálogo distinguem chave de XML
O sistema SHALL diferenciar visualmente lacuna, chave descoberta, XML pendente, XML capturado e incidente fiscal. Documento técnico autorizado MUST aparecer com finalidade e situação reais; chave sem XML MUST NOT oferecer download.

#### Scenario: Chave descoberta
- **WHEN** o número está `KEY_DISCOVERED` ou `XML_PENDING`
- **THEN** a tela operacional mostra a chave e pendência de recuperação, enquanto o catálogo não apresenta download inexistente

#### Scenario: Documento técnico cancelado
- **WHEN** documento técnico possui autorização e evento de cancelamento
- **THEN** Documentos mostra `Saída`, finalidade `Validação técnica` e situação `Cancelada`, sem ocultá-lo

#### Scenario: Cancelamento falho
- **WHEN** existe `FISCAL_INCIDENT`
- **THEN** a interface destaca alerta crítico persistente e kill switch ativo até resolução humana

### Requirement: Visualização de calendário e urgência
A interface SHALL apresentar competência, `target_at`, `due_at`, faixa, conclusão estimada e fonte prevista com texto/ícone/cor acessíveis. Estados de prazo MUST permanecer distintos de falha técnica e bloqueio do canal.

#### Scenario: Prazo saudável com canal bloqueado
- **WHEN** uma pendência ainda está `PLANNED` mas o breaker SVRS está aberto
- **THEN** a UI mostra simultaneamente prazo saudável, canal bloqueado e fontes alternativas, sem fundir os estados

### Requirement: Operação calma como padrão
A UI MUST impedir ações que aumentem frequência, furam ordem justa ou criem retry imediato. ADMIN com 2FA recente poderá antecipar a meta interna dentro da política, mas MUST NOT postergar além do dia 1 nem alterar budget pela interface.

#### Scenario: ADMIN tenta postergar prazo
- **WHEN** um ADMIN configura `due_at` depois do fim do dia 1 seguinte
- **THEN** a interface/API recusa a alteração e preserva o SLA vigente

### Requirement: Contingência progressiva
Em `ATTENTION` a UI SHALL preparar ações/lotes; em `CONTINGENCY` SHALL destacar importação/pacote; em `OVERDUE` SHALL escalar a inbox. Nenhuma transição MUST iniciar rajada automática.

#### Scenario: Mudança para contingência
- **WHEN** a capacidade projetada deixa de atender a meta
- **THEN** a tela atualiza risco e ações assistidas sem alterar limites ou enfileirar retries extras

### Requirement: Navegação fiscal segue o catálogo de módulos
A interface SHALL oferecer Dashboard Fiscal, Simples Nacional/MEI, DCTFWeb/MIT, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias e FGTS conforme permissão, feature flag e cobertura, reutilizando os arquétipos do template oficial.

#### Scenario: Módulo indisponível para a coorte
- **WHEN** um módulo não está habilitado para o tenant
- **THEN** a navegação o oculta ou apresenta estado indisponível coerente, sem ação falsa ou dados demonstrativos

### Requirement: Tabelas fiscais são server-side e tenant-scoped
O sistema SHALL usar tabelas server-side com busca, filtros, competência, situação, cobertura, paginação e ações por registro, preservando filtros reproduzíveis na URL.

#### Scenario: Filtrar clientes pendentes
- **WHEN** usuário aplica categoria, competência e situação `PENDING`
- **THEN** a UI reinicia paginação, consulta a API do tenant ativo e atualiza a URL sem carregar toda a carteira localmente

### Requirement: Estados de cobertura são visíveis por texto e fonte
A interface MUST distinguir dados atuais, processando, pendentes, em atenção, desconhecidos, não aplicáveis, não suportados e bloqueados por texto, ícone e origem, sem depender somente de cor.

#### Scenario: Fonte sem API oficial
- **WHEN** a informação desejada não possui integração M2M oficial
- **THEN** a tela mostra `Não suportado`, a limitação e a fonte disponível, sem botão de atualização por portal

### Requirement: Consumo do plano não expõe custo global
A interface SHALL mostrar ao tenant uso atribuído, franquia, saldo, alertas e período conforme seu plano e MUST NOT revelar fatura consolidada, preço comercial interno ou consumo de outros escritórios.

#### Scenario: Admin do tenant abre consumo
- **WHEN** usuário autorizado acessa o detalhamento mensal
- **THEN** a tabela mostra apenas operações/agregados do escritório ativo e valores permitidos pelo plano

### Requirement: Ação mutante apresenta confirmação fiscal reforçada
A interface MUST apresentar contribuinte, competência, efeito, fonte, custo estimado, estado de procuração e consequência antes de emissão ou transmissão e SHALL solicitar 2FA recente quando exigido.

#### Scenario: Transmissão em coorte somente leitura
- **WHEN** usuário acessa detalhe de obrigação no piloto
- **THEN** a UI não oferece transmissão e explica que a coorte está restrita a monitoramento

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

### Requirement: Experiência SITFIS apresenta estado e próxima ação
A interface SHALL preservar os arquétipos Settings e lista/detalhe do template oficial e SHALL apresentar processamento, idade, próxima atualização, bloqueio e conclusão em linguagem operacional para o contador.

#### Scenario: Snapshot recente
- **WHEN** o usuário abre SITFIS com resultado verificável dentro do TTL
- **THEN** a tela mostra quando foi atualizado, informa que os dados ainda são recentes e não oferece uma chamada externa redundante

#### Scenario: Relatório em processamento
- **WHEN** existe run SITFIS aguardando a fonte
- **THEN** a tela mostra progresso não bloqueante e uma notificação interna leva ao resultado quando concluir

### Requirement: Erros usam revelação progressiva e sanitizada
A interface MUST mostrar primeiro causa operacional e próxima ação, podendo expor correlação, código e horário em detalhes, mas MUST NOT mostrar token, Termo XML, payload bruto ou material de certificado.

#### Scenario: Autorização indisponível
- **WHEN** SITFIS é bloqueado por contrato, Termo ou procuração
- **THEN** a tela orienta a ação necessária sem revelar credenciais globais ou valores protegidos

### Requirement: Origem não pode ser confundida com validação produtiva
A interface SHALL representar proveniência e verificação retornadas pela API e MUST NOT rotular dados simulados ou não verificados como situação fiscal oficial.

#### Scenario: Ambiente interno simulado
- **WHEN** desenvolvedores acessam SITFIS com driver simulado
- **THEN** a interface identifica o contexto de desenvolvimento e não exibe selo de integração produtiva

### Requirement: Navegação horizontal do Monitoramento Fiscal
O sistema SHALL exibir, nas rotas `/monitoring`, uma navegação horizontal compartilhada para Dashboard, Simples/MEI, DCTFWeb/MIT, FGTS, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações e Guias, preservando a navegação lateral do shell e indicando a rota ativa semanticamente.

#### Scenario: Troca de módulo
- **WHEN** o usuário seleciona outro módulo na toolbar
- **THEN** a rota correspondente é aberta, o item fica ativo e os filtros do módulo anterior não contaminam a nova consulta

#### Scenario: Navegação móvel
- **WHEN** a toolbar é exibida em viewport móvel
- **THEN** todos os destinos continuam acessíveis por rolagem ou composição responsiva sem causar overflow horizontal no documento

### Requirement: Carteira fiscal por módulo com visão operacional
Cada módulo SHALL apresentar Total, Em dia, Processando, Pendências e Atenção, seguido de filtros e tabela server-side por cliente. Os indicadores e a tabela MUST derivar do mesmo escopo tenant-scoped e dos mesmos filtros normalizados.

#### Scenario: Página preenchida
- **WHEN** a API retorna overview e clientes monitorados
- **THEN** a página mostra faixa de KPIs, razão social, CNPJ mascarado, competência, situação, cobertura/origem, última consulta e ações permitidas

#### Scenario: KPI acionado
- **WHEN** o usuário seleciona Pendências ou Atenção
- **THEN** o filtro reproduzível na URL é atualizado, a paginação reinicia e a API server-side é consultada

#### Scenario: Atualização falha após dados válidos
- **WHEN** uma atualização falha depois de uma resposta válida
- **THEN** os dados anteriores permanecem visíveis, o horário da última atualização válida é preservado e o erro sanitizado é apresentado

### Requirement: Busca e filtros orientados ao operador
As carteiras MUST oferecer busca server-side por razão social, nome fantasia ou CNPJ, filtros por situação e competência e filtros especializados somente quando suportados pelo módulo. A UI MUST NOT exigir ID numérico do cliente como fluxo principal.

#### Scenario: Busca por CNPJ
- **WHEN** o usuário informa CNPJ com ou sem máscara
- **THEN** a URL recebe o valor normalizado, a página volta para 1 e somente clientes do office ativo compatíveis são retornados

#### Scenario: Navegação voltar/avançar
- **WHEN** o usuário usa histórico do navegador após alterar filtros
- **THEN** os controles, submódulo, página e resultados refletem a query atual sem estado obsoleto

#### Scenario: Filtro não suportado
- **WHEN** um módulo não oferece determinada dimensão de filtro
- **THEN** o controle não é exibido como elemento decorativo ou processado apenas sobre a página atual

### Requirement: Contratos fiscais tipados e discriminados
O frontend SHALL consumir DTOs discriminados por `module_key` para overview, carteira e detalhes e MUST NOT usar fallback genérico que apresente registro de outro módulo ou converta incompatibilidade de contrato em campo vazio.

#### Scenario: Contrato incompatível
- **WHEN** a API retorna payload que não corresponde ao contrato esperado
- **THEN** a tela apresenta erro de carregamento sanitizado e os testes de contrato falham, em vez de preencher colunas com `—` silenciosamente

#### Scenario: Simples sem snapshots próprios
- **WHEN** a consulta dedicada de Simples/MEI não retorna registros
- **THEN** a página mostra vazio de Simples/MEI e não usa snapshots genéricos de DCTFWeb, SITFIS ou outro módulo

#### Scenario: Valores monetários
- **WHEN** Guia, DARF ou Parcela retorna valor em centavos
- **THEN** a interface usa o campo tipado em centavos e formata moeda sem tratar o valor como string arbitrária

### Requirement: Experiência completa de Simples Nacional e MEI
A página Simples/MEI SHALL oferecer submódulos PGDAS-D, PGMEI, DASN-SIMEI e Regime, apresentando aplicabilidade, competência, última obrigação, guia e situação por cliente por meio dos endpoints dedicados.

#### Scenario: Alternância de submódulo
- **WHEN** o usuário alterna entre PGDAS-D, PGMEI, DASN-SIMEI e Regime
- **THEN** a URL, KPIs, colunas e consulta mudam para o contrato do submódulo sem perder filtros compatíveis

#### Scenario: Cliente não aplicável
- **WHEN** um cliente não pertence ao regime exigido pelo submódulo
- **THEN** a linha apresenta `NOT_APPLICABLE` com motivo e não oferece geração/transmissão indevida

### Requirement: Experiência completa de DCTFWeb e MIT
A página DCTFWeb/MIT SHALL separar encerramento MIT, transmissão DCTFWeb, recibos, evidências, DARF e pagamento, usando códigos oficiais do catálogo no preflight e nas ações permitidas.

#### Scenario: DCTFWeb transmitida sem pagamento conhecido
- **WHEN** uma declaração possui recibo de transmissão e DARF ainda sem pagamento conhecido
- **THEN** a tabela apresenta os dois estados de forma independente e não reduz ambos a um único badge

#### Scenario: Preflight de transmissão
- **WHEN** um administrador autorizado inicia transmissão
- **THEN** o request usa `solution_code`, `service_code` e `operation_code` tipados do registro/catálogo, apresenta consequência e respeita o gate somente leitura/2FA

### Requirement: Experiência completa de Parcelamentos
A página Parcelamentos SHALL apresentar modalidades do catálogo, pedidos, saldo, parcelas, próxima parcela, atrasos e guias associadas, com detalhe navegável e sem aba inexistente no cliente.

#### Scenario: Pedido com parcela atrasada
- **WHEN** um pedido possui parcela vencida e não paga
- **THEN** a carteira contabiliza Atenção/Pendência, mostra a próxima ação e abre o detalhe das parcelas do mesmo office

#### Scenario: Deep-link do cliente
- **WHEN** o usuário abre Parcelamentos a partir de um cliente
- **THEN** a rota de detalhe fiscal renderiza a seção Parcelamentos com dados lazy daquele cliente

### Requirement: Situação Fiscal em carteira e detalhe normalizado
A página SITFIS SHALL mostrar a carteira completa com situação, idade/TTL do snapshot, quantidade de achados e atualização, e SHALL renderizar o detalhe normalizado em componente acessível sem exibir JSON bruto.

#### Scenario: Snapshot vigente
- **WHEN** o cliente possui snapshot SITFIS dentro do TTL
- **THEN** a linha mostra situação, cobertura, origem, idade e vencimento provenientes de `snapshot` e do envelope tipado

#### Scenario: Snapshot expirado
- **WHEN** o snapshot ultrapassa o TTL
- **THEN** a página informa expiração, mantém o último resultado identificado e oferece refresh somente a papel permitido

#### Scenario: Detalhe de pendências
- **WHEN** o usuário abre um cliente SITFIS
- **THEN** o slideover lista pendências normalizadas, protocolos e timestamps sem `<pre>` de resposta remota

### Requirement: Caixa Postal em mestre–detalhe responsivo
A página Caixa Postal SHALL seguir o arquétipo Inbox: lista e detalhe adjacentes no desktop, detalhe em slideover/drawer abaixo de `lg` e `/monitoring/mailbox/{id}` como rota canônica. Campos e triagem MUST seguir o contrato backend.

#### Scenario: Seleção de mensagem no desktop
- **WHEN** o usuário seleciona uma mensagem em desktop
- **THEN** a URL muda para o ID autorizado, a lista permanece visível e o painel mostra `subject_preview`, `received_at_official`, DTE, prazo e leitura oficial

#### Scenario: Seleção de mensagem no mobile
- **WHEN** o usuário seleciona uma mensagem em mobile
- **THEN** o detalhe abre em overlay acessível, pode ser fechado por teclado e retorna foco ao item selecionado

#### Scenario: Triagem interna
- **WHEN** um usuário permitido altera a triagem
- **THEN** somente `NEW`, `IN_REVIEW` ou `RESOLVED` é enviado e a interface reafirma que isso não altera ciência/leitura oficial

#### Scenario: Corpo ou anexo protegido
- **WHEN** o usuário autorizado solicita corpo ou anexo
- **THEN** a UI usa o endpoint protegido, não embute identificador de cofre e trata indisponibilidade separadamente da ausência de mensagem

### Requirement: Central de Declarações baseada no resumo real
A página Declarações SHALL renderizar o resumo retornado pela API e listar obrigação, aplicabilidade, competência, vencimento, situação de entrega e evidência com nomes de campos alinhados ao resource backend.

#### Scenario: Resumo e lista preenchidos
- **WHEN** a API retorna contagens e projeções
- **THEN** os KPIs correspondem à consulta e cada linha usa `obligation_code`/`obligation_name`, período, prazo e situação tipados

#### Scenario: Filtro por competência e situação
- **WHEN** o usuário aplica competência e situação
- **THEN** o frontend envia os parâmetros aceitos pela API e não filtra somente os registros da página atual

### Requirement: Central de Guias com estados independentes
A página Guias SHALL apresentar tipo/sistema, competência, `amount_cents`, vencimento, emissão, validade, pagamento e versão como dimensões independentes, com detalhe, download protegido e ações submetidas ao preflight real.

#### Scenario: Guia emitida e não paga
- **WHEN** a API retorna emissão concluída e `payment_status` pendente
- **THEN** a linha mostra valor correto e diferencia emissão de pagamento

#### Scenario: Download autorizado
- **WHEN** um usuário permitido solicita uma versão válida
- **THEN** a UI obtém token de download efêmero e não expõe caminho interno ou objeto do cofre

#### Scenario: Guia demonstrativa
- **WHEN** a guia pertence ao dataset demo
- **THEN** o detalhe e o arquivo informam ausência de validade fiscal e nenhuma confirmação é apresentada como pagamento real

### Requirement: FGTS com cobertura parcial permanente
A página FGTS/eSocial SHALL apresentar fechamento, totalização, eventos e divergências cobertos e MUST manter guia/pagamento como `UNSUPPORTED` quando não existir fonte M2M oficial.

#### Scenario: Competência com fechamento eSocial
- **WHEN** eventos oficiais/sintéticos de eSocial permitem projetar o fechamento
- **THEN** a tela mostra fonte, timestamps, situação e detalhe dos eventos sem afirmar consulta ao portal FGTS Digital

#### Scenario: Guia sem fonte oficial
- **WHEN** não há API oficial suportada para guia ou pagamento FGTS Digital
- **THEN** badge, texto e ajuda mostram `UNSUPPORTED`, sem botão de portal, scraping ou atualização falsa

### Requirement: Detalhe fiscal do cliente completo e lazy
O detalhe `/monitoring/clients/{clientId}` SHALL oferecer seções funcionais para Resumo, Execuções, Findings, Pendências, Parcelamentos, Declarações, Guias, FGTS e SITFIS, carregando somente a seção ativa e distinguindo falha de vazio.

#### Scenario: Abertura por deep-link
- **WHEN** uma carteira abre `tab=installments`, `tab=declarations` ou `tab=fgts`
- **THEN** a seção correspondente existe, fica ativa e carrega somente os endpoints necessários ao cliente autorizado

#### Scenario: Falha parcial
- **WHEN** uma seção falha e dados de resumo do cliente foram carregados
- **THEN** o erro da seção é apresentado com retry e não é convertido silenciosamente em “nenhum registro”

#### Scenario: Cliente de outro office
- **WHEN** a URL contém ID pertencente a outro tenant
- **THEN** o sistema apresenta não encontrado sem revelar identidade, contagens ou existência do cliente

### Requirement: Ações de carteira reais e autorizadas
As páginas de Monitoramento SHALL oferecer somente ações funcionais: adicionar cliente pelo fluxo existente, associar categorias/clientes, atualizar leitura, exportar por filtro e abrir detalhe, condicionadas a papel, feature flag, cobertura e modo demo.

#### Scenario: Viewer na carteira
- **WHEN** um `VIEWER` abre um módulo
- **THEN** a leitura e navegação permanecem disponíveis, mas associação, atualização, exportação proibida e mutações não são oferecidas

#### Scenario: Exportação por filtro
- **WHEN** usuário autorizado exporta a carteira filtrada
- **THEN** o job server-side recebe filtros reproduzíveis, escopo do office ativo e campos sanitizados, sem exportar material sensível

#### Scenario: Controle sem backend
- **WHEN** uma ação não possui endpoint ou comportamento implementado
- **THEN** a interface não exibe botão decorativo que simule conclusão

### Requirement: Estados e testes completos do Monitoramento
Todas as rotas `/monitoring` MUST distinguir carregamento inicial, atualização, preenchido, vazio, erro, não suportado, bloqueado e dado demonstrativo, e SHALL ser cobertas por testes de contrato, interação, permissão e responsividade.

#### Scenario: Matriz visual preenchida
- **WHEN** Playwright abre cada rota com fixtures determinísticas em `1440×900` e `390×844`
- **THEN** navbar, navegação, KPIs, filtros, tabela/detalhe e ações permitidas permanecem utilizáveis e visualmente estáveis

#### Scenario: Largura mínima
- **WHEN** uma rota é executada em 360 px
- **THEN** não existe rolagem horizontal do documento e ações essenciais permanecem alcançáveis por teclado/toque

#### Scenario: Troca de office durante carregamento
- **WHEN** a membership ativa muda enquanto uma requisição fiscal está pendente
- **THEN** a resposta anterior é descartada e nenhum dado do tenant anterior é renderizado

### Requirement: Paginação server-side em toda lista potencialmente não limitada
Clientes, Exportações, Documentos por empresa e qualquer lista tabular cujo universo possa crescer SHALL paginar ou usar cursor no backend, MUST retornar metadados suficientes ao controle visual do template e MUST NOT baixar todas as páginas para aplicar paginação, busca, filtro ou ordenação somente no navegador.

#### Scenario: Carteira com várias páginas de clientes
- **WHEN** o operador abre Clientes e muda busca, estado, ordenação ou página
- **THEN** o frontend solicita somente o recorte correspondente, mantém a URL canônica `/clients` sem estado efêmero da tabela e preserva os KPIs do escritório independentes da página atual

#### Scenario: Histórico com mais de cinquenta exportações
- **WHEN** o usuário navega no histórico de Exportações
- **THEN** consegue acessar páginas anteriores sem limite silencioso aos cinquenta registros mais recentes

#### Scenario: Agregação por empresa
- **WHEN** o filtro de Documentos alcança muitas empresas e notas
- **THEN** o PostgreSQL agrega e pagina por empresa antes da resposta, sem carregar todas as notas e vínculos em memória da aplicação

### Requirement: Cursor sem simulação de offset
Uma API cursor-based SHALL oferecer navegação incremental e MUST NOT ser convertida em paginação aleatória que execute consultas intermediárias ocultas. Alterar filtro ou tamanho de lote SHALL limpar linhas e cursor da consulta anterior.

#### Scenario: Carregar mais documentos
- **WHEN** o operador solicita a próxima página do catálogo
- **THEN** o frontend usa exatamente o `next_cursor`, acumula as novas linhas e não consulta páginas intermediárias para alcançar um número de página

#### Scenario: Alteração de filtro
- **WHEN** busca, situação, cliente, competência ou tipo muda
- **THEN** a lista, seleção e cursor anteriores são reiniciados antes da primeira resposta do novo filtro

### Requirement: Vocabulário tabular em pt-BR
Cabeçalhos, estados vazios, ações e badges SHALL usar labels pt-BR do domínio, enquanto códigos técnicos MAY permanecer em dica, descrição ou detalhe acessível.

#### Scenario: Resultado técnico de importação
- **WHEN** um item possui estado `DUPLICATE`, `UNMATCHED` ou `QUARANTINED`
- **THEN** a tabela mostra “Duplicado”, “Sem vínculo” ou “Em quarentena” e preserva o código técnico no detalhe quando necessário

### Requirement: URL canônica sem estado efêmero de tabela
Filtros, busca, ordenação, paginação, cursor, seleção e abertura de overlay SHALL permanecer em estado local da interface e MUST NOT ser serializados em query parameters do navegador. Visões que constituem destinos navegáveis SHALL usar paths próprios; parâmetros enviados pelo frontend à API MAY continuar em query HTTP sem alterar a URL visível.

#### Scenario: Filtrar uma lista administrativa
- **WHEN** o operador altera um filtro, a ordenação ou a página de Clientes, Saúde, Fechamento ou Exportações
- **THEN** a lista solicita o recorte correto à API e a URL visível permanece no path canônico da tela

#### Scenario: Alternar a visão de Documentos
- **WHEN** o operador alterna entre a agregação por cliente e o catálogo documental
- **THEN** o sistema navega entre `/docs` e `/docs/catalog`, preserva filtros no estado local e não cria `?view=` nem queries de filtro

### Requirement: CT-e integrado à experiência de Documentos
A interface SHALL tratar CT-e como `kind=CTE` do catálogo unificado, ao lado de NF-e e NFC-e, e MUST concentrar em `/docs/catalog` sua listagem, detalhe, filtros, cobertura, orientação `autXML`, pendências, importação, exportação e download. A interface MUST NOT apresentar CT-e como módulo, página ou item de Configurações separado.

#### Scenario: Operador consulta documentos mistos
- **WHEN** um OPERATOR abre o Catálogo sem filtro de tipo
- **THEN** NF-e, NFC-e e CT-e autorizados aparecem na mesma estrutura de lista e detalhe, com diferenças expressas por tipo, papel, direção, origem e qualidade

#### Scenario: Contexto de captura CT-e necessário
- **WHEN** o filtro `kind=CTE` está ativo e o escritório precisa configurar `autXML` ou resolver cobertura pendente
- **THEN** o catálogo apresenta orientação, CNPJ copiável, metadados seguros e ações documentais permitidas sem navegar para Configurações

#### Scenario: Saúde operacional permanece em Sincronizações
- **WHEN** o usuário precisa consultar cursor, `maxNSU`, quiet, fila ou circuito `656` de CT-e
- **THEN** a interface mantém esses dados em Sincronizações e usa deep-link para `/docs/catalog?kind=CTE` somente quando a ação for documental

#### Scenario: VIEWER consulta CT-e
- **WHEN** um VIEWER abre `/docs/catalog?kind=CTE`
- **THEN** visualiza documentos, cobertura e metadados autorizados em modo somente leitura, sem ações de importação, resolução, identidade, A1 ou flags

#### Scenario: Troca de escritório no catálogo CT-e
- **WHEN** o usuário troca explicitamente o escritório ativo enquanto há dados ou requests CT-e carregados
- **THEN** a interface descarta o estado do office anterior e nenhuma resposta atrasada repopula documentos, CNPJ ou pendências de outro escritório

### Requirement: Configurações sem superfície CT-e
A navegação de Configurações, a sidebar e a command palette MUST NOT oferecer destino CT-e próprio. Identidade fiscal e A1 SHALL permanecer na Administração protegida por papel e 2FA, enquanto o catálogo pode mostrar somente metadados sanitizados e ação autorizada para a área administrativa.

#### Scenario: Usuário abre Configurações
- **WHEN** um usuário autorizado visualiza as seções de Configurações
- **THEN** não existe aba ou item CT-e e os destinos restantes obedecem ao gate administrativo normal

#### Scenario: A1 exige administração
- **WHEN** o catálogo informa A1 ausente, expirado ou bloqueado
- **THEN** somente ADMIN com confirmação vigente recebe deep-link para Administração e nenhum usuário recebe PFX, senha, PEM ou chave privada

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

### Requirement: Onboarding CT-e autXML no dashboard
A interface interna SHALL apresentar no catálogo de Documentos (`/docs/catalog`, contexto `kind=CTE`) um checklist CT-e para copiar o CNPJ completo canônico do escritório, orientar sua inclusão prévia em `autXML`, mostrar o A1 do escritório apenas por metadados seguros e habilitar o canal somente para ADMIN com 2FA recente. A UI MUST NOT oferecer alteração retroativa do XML nem automação de portal. A UI MUST NOT tratar CT-e como página ou item de Configurações separado.

#### Scenario: Cliente ainda não configurado
- **WHEN** o escritório não observou CT-e válido daquele emitente com `autXML`
- **THEN** a UI mostra onboarding pendente no catálogo, instrução copiável e fallback XML/ZIP sem prometer histórico

#### Scenario: A1 do escritório expirado
- **WHEN** a credencial necessária está expirada ou bloqueada
- **THEN** a UI desabilita ativação, mostra ação administrativa segura e não exibe PFX, senha ou PEM

#### Scenario: VIEWER consulta CT-e no catálogo
- **WHEN** VIEWER abre `/docs/catalog?kind=CTE`
- **THEN** os estados são somente leitura e nenhuma ação de credencial ou flag é oferecida

### Requirement: Sincronizações CT-e distinguem cliente e escritório
A página de Sincronizações SHALL exibir cards separados para `CTE_DISTDFE` do cliente e `CTE_AUTXML_DISTDFE` do escritório, com estado, ambiente, cursor, `maxNSU`, última execução, próxima execução, fila, bloqueio e cobertura. Conteúdo fiscal bruto MUST NOT ser renderizado na saúde.

#### Scenario: Fila vazia saudável
- **WHEN** um stream recebe `cStat=137` e está no quiet obrigatório
- **THEN** o card mostra “fila alcançada” e o horário da próxima consulta, não erro genérico

#### Scenario: Consumo indevido
- **WHEN** ocorre `cStat=656`
- **THEN** o card mostra circuito aberto, prazo e recomendação sem botão de retry antes do desbloqueio

### Requirement: Documentos CT-e mostram papel, origem e qualidade
Listagem e detalhe de Documentos SHALL mostrar `CTE`, cliente/estabelecimento, papéis, direção, origem e qualidade por texto e ícone acessível, incluindo aviso para `AUTXML_REDACTED`. Filtros SHALL permitir CT-e, entrada/saída, papel, origem, qualidade e estado de cobertura.

#### Scenario: Cliente expedidor
- **WHEN** o usuário visualiza CT-e emitido por terceiro em que o cliente é expedidor
- **THEN** a linha mostra `Expedidor`, `Entrada`, `CTE_DIST_NSU` e `Original`

#### Scenario: Cliente emitente por autXML
- **WHEN** a aquisição é redigida e o cliente é emitente
- **THEN** a linha mostra `Emitente`, `Saída`, `AutXML do escritório` e `Cópia oficial com referências protegidas`

### Requirement: Import e pendências CT-e integrados
ADMIN e OPERATOR SHALL poder enviar XML/ZIP de CT-e no import em massa existente, acompanhar lote/item, reprocessar estados elegíveis e resolver `PENDING_IMPORT` ou quarentena dentro do próprio escritório. VIEWER SHALL permanecer somente leitura.

#### Scenario: Lote misto
- **WHEN** o operador envia ZIP com NF-e, NFC-e e CT-e de múltiplos clientes
- **THEN** a UI acompanha resultados por entrada e destaca importado, duplicado, sem vínculo, inválido e quarentena sem perder itens válidos

#### Scenario: Pendência resolvida por upload
- **WHEN** `cteProc` original válido é importado para período `PENDING_IMPORT`
- **THEN** a cobertura é atualizada depois da conclusão backend e a interface não mantém pendência fantasma

### Requirement: Estados de recuperação e limite preventivo
A interface SHALL distinguir documento já disponível, aguardando fonte preferencial, aguardando orçamento SVRS, em recuperação, capturado, breaker/cooldown e fallback assistido. Ela SHALL explicar que os budgets são preventivos e que a SVRS não publicou o limite do formulário `NFESSL`.

#### Scenario: Chave aguarda orçamento diário
- **WHEN** uma NF-e elegível permanece na fila após esgotar o budget diário
- **THEN** a UI preserva o estado pendente, mostra a próxima janela e destaca importação XML/ZIP como alternativa

### Requirement: Ações por papel sem bypass
A UI MUST ocultar e bloquear flags, allowlist, extensão de cooldown e seleção de canário para quem não é ADMIN com 2FA recente. Nenhum papel MUST receber controle para antecipar `next_probe_at`, aumentar limites ou trocar coorte/IP.

#### Scenario: ADMIN durante cooldown
- **WHEN** um ADMIN com 2FA recente abre um item bloqueado antes de `next_probe_at`
- **THEN** pode desligar o canal, estender o cooldown ou preparar fallback, mas não disparar prova antecipada

#### Scenario: OPERATOR usa contingência
- **WHEN** o canal está bloqueado e um OPERATOR acessa uma NF-e pendente
- **THEN** a ação primária disponível é importar XML/ZIP ou pacote oficial conforme elegibilidade, sem retry remoto

### Requirement: Gestão da identidade fiscal e do A1 do escritório
O sistema SHALL oferecer em Configurações uma superfície própria para a identidade fiscal e a credencial A1 do escritório, separada de Clientes. Somente ADMIN com 2FA recente SHALL poder cadastrar, substituir, ativar ou revogar a credencial; OPERATOR e VIEWER SHALL visualizar apenas estado e metadados públicos permitidos. A interface MUST NOT oferecer recuperação, download ou cópia de PFX, senha, chave privada, PEM ou referência de vault.

#### Scenario: ADMIN cadastra o A1 do escritório
- **WHEN** ADMIN com 2FA recente envia PFX e senha na superfície da identidade fiscal
- **THEN** a UI apresenta somente titular, CNPJ, fingerprint, validade e estado devolvidos pelo backend e descarta o segredo do formulário após a resposta

#### Scenario: Falha de validação do A1
- **WHEN** senha, titular, raiz, validade ou certificado não passam na validação
- **THEN** a interface mostra mensagem sanitizada, não ecoa senha/material e mantém a credencial anterior ativa quando existir

#### Scenario: OPERATOR abre a identidade fiscal
- **WHEN** OPERATOR consulta a configuração do escritório
- **THEN** vê CNPJ copiável, estado do canal e validade, mas não possui ação de upload, substituição, revogação ou recuperação do A1

### Requirement: Onboarding autXML por estabelecimento sem promessa retroativa
O sistema SHALL apresentar a OPERATOR/ADMIN um checklist por estabelecimento para inclusão do CNPJ completo do escritório em `autXML` pelo ERP do emitente, com estados `PENDING`, `CONFIRMED` e `INACTIVE`. A interface MUST informar que a tag deve integrar o XML antes da autorização, que novo usuário `distNSU` não recebe NSU retroativo e que NFC-e 65 não é capturada por esse canal. O sistema SHALL indicar XML/ZIP como caminho para histórico, lacunas e NFC-e.

#### Scenario: Copiar CNPJ para o ERP
- **WHEN** o operador inicia o onboarding de um estabelecimento
- **THEN** a interface permite copiar o CNPJ completo normalizado do escritório, explica que a alteração é feita no ERP do cliente e não oferece ação para editar XML autorizado

#### Scenario: Stream ainda não ativado
- **WHEN** a identidade fiscal ainda não executou a primeira `distNSU` e cumpriu o quiet mínimo
- **THEN** a UI impede marcar novos enrollments como ativos e orienta concluir primeiro a ativação do stream

#### Scenario: Primeiro XML observado
- **WHEN** o canal recebe NF-e 55 válida do estabelecimento contendo o CNPJ esperado em `autXML`
- **THEN** a interface mostra `first_seen_at`, permite confirmar o enrollment e distingue evidência observada de simples declaração manual

#### Scenario: Usuário procura NFC-e no autXML
- **WHEN** o usuário consulta a cobertura do onboarding
- **THEN** a UI informa explicitamente “NF-e modelo 55” para o canal automático e direciona NFC-e modelo 65 ao import XML/ZIP ou canal específico habilitado

### Requirement: Sincronização autXML central distinguível dos clientes
O sistema SHALL apresentar o cursor `NFE_AUTXML_DISTDFE` como sincronização central do escritório por identidade fiscal/CNPJ-base e ambiente, separado das sincronizações por cliente. A UI SHALL mostrar estado, último/maior NSU, último sucesso, próximo agendamento, heartbeat e cStat/motivo sanitizado, e MUST NOT oferecer edição ou reset direto do NSU.

#### Scenario: Primeira consulta sem documentos
- **WHEN** a ativação retorna `cStat=137`
- **THEN** a interface registra a primeira consulta, mostra a espera mínima de uma hora e não descreve o resultado como falha nem como backfill concluído

#### Scenario: Consumo indevido
- **WHEN** o cursor registra `cStat=656`
- **THEN** a UI mostra circuito aberto e horário mínimo da próxima tentativa, sem botão de retry antecipado ou envelope SOAP bruto

#### Scenario: Cursor autXML bloqueado
- **WHEN** o canal do escritório está `BLOCKED`, mas sincronizações de clientes estão saudáveis
- **THEN** a interface atribui o problema somente ao stream autXML e mantém as ações dos canais de clientes independentes

### Requirement: Acompanhamento durável dos lotes de importação
O sistema SHALL apresentar histórico tenant-aware dos lotes com estado, autor, horário, quantidade de arquivos, totais por resultado e progresso persistido. A tela SHALL permitir reabrir um lote após navegação ou recarga e MUST distinguir upload concluído de processamento fiscal concluído.

#### Scenario: Lote ainda em processamento
- **WHEN** o usuário abre um lote `QUEUED` ou `PROCESSING`
- **THEN** a interface mostra progresso indeterminado ou contagens reais processadas, atualiza com polling controlado e não anuncia sucesso antes do estado terminal

#### Scenario: Retorno após recarga
- **WHEN** o usuário recarrega a página ou retorna ao painel depois de fechar o upload
- **THEN** o lote continua acessível pelo identificador/URL reproduzível e seu estado vem novamente da API

#### Scenario: Falha do polling
- **WHEN** a atualização de progresso falha depois de um estado válido ter sido carregado
- **THEN** a UI preserva o último estado conhecido, informa falha sanitizada e permite tentar atualizar sem recriar o lote

### Requirement: Resultado item a item e exportação do relatório
O sistema SHALL apresentar itens paginados e filtráveis por resultado, com arquivo/entrada, tipo, chave validada, emitente, cliente/estabelecimento associado e motivo sanitizado. O sistema SHALL permitir exportar CSV do relatório sem incluir XML, assinatura, referência de vault, caminho interno ou material criptográfico.

#### Scenario: Lote concluído parcialmente
- **WHEN** um lote chega a `COMPLETED_WITH_ERRORS`
- **THEN** a interface mostra resumo por estado e permite filtrar os itens que exigem ação sem ocultar os documentos importados com sucesso

#### Scenario: Consulta de lote volumoso
- **WHEN** o lote possui milhares de entradas
- **THEN** a interface pagina e filtra no servidor, sem carregar todos os itens ou qualquer XML bruto no navegador

#### Scenario: Exportar relatório
- **WHEN** usuário autorizado solicita o CSV do lote
- **THEN** recebe somente metadados e resultados sanitizados do escritório ativo

### Requirement: Retentativa orientada e resolução sem associação forçada
O sistema SHALL oferecer retentativa somente para itens `UNMATCHED` e falhas transitórias elegíveis, explicando a ação necessária antes do reprocessamento. Itens `CLIENT_MISMATCH`, `INVALID`, `UNSUPPORTED` ou `QUARANTINED` por conflito de chave MUST NOT possuir ação de aceitar cegamente ou trocar o cliente indicado pelo XML.

#### Scenario: Reprocessar após cadastrar estabelecimento
- **WHEN** o operador cadastra o estabelecimento correspondente ao emitente de itens `UNMATCHED` e solicita retentativa dentro do prazo de retenção
- **THEN** a interface acompanha nova tentativa dos itens elegíveis sem exigir novo upload e atualiza cliente/estabelecimento somente após confirmação do backend

#### Scenario: Conflito de chave
- **WHEN** um item está `QUARANTINED` porque a chave já possui bytes canônicos diferentes
- **THEN** a UI mostra alerta e encaminha à revisão operacional, sem botão de substituir o XML canônico

#### Scenario: Erro inválido não retentável
- **WHEN** assinatura, protocolo, formato ou modelo tornou o item `INVALID` ou `UNSUPPORTED`
- **THEN** a interface explica que o arquivo precisa ser corrigido na origem e não oferece retry que repetiria o mesmo erro

### Requirement: Importação acessível e sem conteúdo fiscal bruto
O sistema SHALL oferecer seleção por controle de arquivo e drag-and-drop acessíveis por teclado, anunciar mudanças relevantes de progresso e resultado a tecnologias assistivas e MUST NOT renderizar XML bruto, stack trace, caminho temporário, A1, senha, CSC, chave privada ou PEM em modal, histórico, toast, tabela, CSV ou log do navegador.

#### Scenario: Uso por teclado
- **WHEN** o usuário opera a importação sem mouse
- **THEN** consegue selecionar arquivos, remover itens, enviar o lote, acompanhar estado e abrir o resultado com foco visível e nomes acessíveis

#### Scenario: Erro interno com trecho de XML
- **WHEN** a API sanitiza uma falha interna que originalmente continha payload fiscal
- **THEN** a interface exibe somente código, mensagem segura e correlação, sem inserir o conteúdo original no DOM ou console
