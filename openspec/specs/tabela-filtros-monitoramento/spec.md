# tabela-filtros-monitoramento Specification

## Purpose
Filtros e casca compartilhada das listas de dados do painel (Monitoramento e superfícies relacionadas): busca dedicada, chips estruturados por API, presets salvos, paginação server-side e isolamento por Office.

## Requirements

### Requirement: Filtros rápidos e avançados possuem um único estado aplicado
As listas do Monitoramento SHALL manter busca em campo dedicado e SHALL representar os demais campos aplicados como filtros estruturados de igualdade. Busca SHALL aplicar após debounce de 320 ms ou submissão por Enter; filtros estruturados MUST NOT alterar a consulta antes da confirmação. Situação SHALL ser um chip e cliques nos KPIs SHALL criar ou atualizar esse mesmo filtro.

#### Scenario: Confirmar filtro após mudar situação pelo KPI
- **WHEN** o usuário selecionar uma situação por KPI e depois confirmar competência ou cliente
- **THEN** a consulta SHALL combinar a situação mais recente com o novo filtro sem restaurar valores antigos e SHALL executar uma única recarga por confirmação

#### Scenario: Fechar sem aplicar
- **WHEN** o usuário alterar um filtro estruturado e fechar o editor sem confirmar
- **THEN** o valor aplicado SHALL permanecer inalterado e nenhuma recarga SHALL ocorrer

#### Scenario: Busca com debounce e Enter
- **WHEN** o usuário digitar na busca ou submetê-la por Enter
- **THEN** a lista SHALL aplicar o texto após 320 ms ou imediatamente no Enter, cancelando a execução pendente

### Requirement: Painel avançado permanece recolhível e consistente
Os filtros adicionais SHALL ser exibidos como chips entre os controles da toolbar e a tabela. O botão `Adicionar filtro` SHALL abrir seletor responsivo contendo somente campos inativos; remover ou confirmar um chip SHALL retornar à primeira página e provocar uma única recarga. A ação `Limpar tudo` SHALL restaurar busca e chips em uma única transação.

#### Scenario: Adicionar e editar filtro
- **WHEN** o usuário escolher um campo inativo ou acionar a edição de um chip
- **THEN** o editor SHALL partir do default ou estado aplicado correspondente e só alterar a lista após confirmação

#### Scenario: Limpar filtros combinados
- **WHEN** busca e chips estiverem ativos e o usuário clicar em `Limpar tudo`
- **THEN** todos SHALL voltar aos defaults, a paginação SHALL voltar à página 1 e a lista SHALL recarregar uma única vez

### Requirement: Toolbar permite salvar e reutilizar filtros na lista
As listas de dados cobertas SHALL expor na toolbar ações para salvar o estado aplicado atual como preset e para abrir a lista de presets (pessoais e compartilhados do Office). Salvar MUST exigir nome não vazio e MUST capturar busca e filtros estruturados ativos. Aplicar um preset SHALL hidratar o estado aplicado, voltar à página 1, limpar a seleção da tabela e disparar uma única recarga server-side. Fechar o modal de salvar sem confirmar MUST NOT criar preset. Gerenciar presets SHALL usar modal Nuxt UI.

#### Scenario: Salvar e aplicar na mesma lista
- **WHEN** o usuário tem chips/busca ativos, salva como “Bloqueados” e depois aplica esse preset em outra visita à mesma surface
- **THEN** a lista SHALL restaurar o mesmo recorte e recarregar uma vez na página 1

#### Scenario: Cancelar salvar
- **WHEN** o usuário abre o diálogo de salvar, altera o nome e fecha sem confirmar
- **THEN** nenhum preset novo SHALL ser criado

### Requirement: Colunas de negócio filtráveis estão disponíveis como filtros
Para cada lista coberta, a configuração de filtros SHALL declarar um campo estruturado (ou busca dedicada) para cada coluna de negócio filtrável da tabela. Colunas de chrome (seleção, ações, menus) MUST NOT gerar filtros. A UI MUST NOT oferecer campo de filtro cujo valor seja ignorado pela API de listagem daquela surface. Quando a coluna for filtrável, o editor SHALL usar o kind adequado (option, month, client, text, boolean, date ou date_range) com confirmação explícita.

#### Scenario: Abrir Adicionar filtro com colunas cobertas
- **WHEN** o usuário abre o seletor de filtros em uma lista migrada
- **THEN** todas as colunas de negócio filtráveis da lista SHALL aparecer como opções (se ainda inativas) e colunas de ações MUST NOT aparecer

#### Scenario: API aplica o eixo escolhido
- **WHEN** o usuário confirma um filtro de uma coluna de negócio suportada
- **THEN** a requisição de listagem SHALL incluir o parâmetro correspondente e o resultado SHALL respeitar o filtro

### Requirement: Superfícies de dados relacionadas usam o mesmo padrão de filtros e presets
Além das nove listas padronizadas de Monitoramento, as superfícies de dados com listagem server-side — mailbox, clientes, documentos (catálogo), fila de trabalho, processos de trabalho e closing — SHALL usar o contrato de filtros estruturados (ou adapter equivalente) e os presets por `surface`. Isolamento por Office e limpeza na troca de tenant SHALL valer em todas essas superfícies.

#### Scenario: Preset de documentos não aparece no monitoring
- **WHEN** o usuário salva um preset na surface de documentos
- **THEN** ele MUST NOT ser listado nas surfaces de monitoring e vice-versa

#### Scenario: Clientes e work aceitam salvar
- **WHEN** o usuário aplica filtros na lista de clientes ou na fila de trabalho e salva um preset
- **THEN** o preset SHALL ser reaplicável na mesma surface após navegação

### Requirement: Cada lista expõe somente filtros aceitos por sua API
A configuração do Monitoramento e das demais superfícies cobertas SHALL declarar campos explícitos e ordenados alinhados às colunas de negócio filtráveis e aos parâmetros realmente aplicados pela API da surface. Simples/MEI e DCTFWeb/MIT SHALL oferecer, no mínimo, situação, cliente, competência e demais colunas de negócio filtráveis do módulo; Parcelamentos e SITFIS SHALL incluir situação, cliente e eixos de coluna suportados (ex.: modalidade e cobertura quando a API aplicar); Declarações SHALL incluir situação, cliente, competência, status de entrega e colunas de negócio filtráveis; FGTS SHALL incluir situação, cliente, competência e eixos de coluna suportados; Guias SHALL oferecer cliente, status de pagamento e demais eixos que a API de guias aplicar (competência só se o endpoint passar a aplicá-la); Cadastro/Vínculos e Processos SHALL oferecer status, cliente quando a API aceitar, e demais colunas filtráveis. A UI MUST NOT exibir filtro decorativo.

#### Scenario: Abrir filtros de Guias
- **WHEN** o usuário abrir `Adicionar filtro` na lista de Guias
- **THEN** os campos oferecidos SHALL corresponder apenas a parâmetros aplicados pelo endpoint de guias

#### Scenario: Abrir filtros de Declarações
- **WHEN** o usuário abrir `Adicionar filtro` na lista de Declarações
- **THEN** situação, cliente, competência, status de entrega e demais colunas de negócio filtráveis do módulo SHALL estar entre os campos disponíveis se e somente se a API os aplicar

### Requirement: Filtros estruturados respeitam seleção, rota e Office
Confirmar, remover, limpar filtros ou aplicar um preset SHALL invalidar a seleção da tabela e preservar a URL canônica do monitoring sem query de filtros. Na troca de Office, filtros aplicados, rascunhos, rótulos visuais de cliente e cache de presets MUST ser descartados antes da nova carga. Requests MUST NOT conter `office_id` como autoridade de escopo.

#### Scenario: Aplicar preset com linhas selecionadas
- **WHEN** o usuário aplicar um preset com linhas selecionadas na tabela
- **THEN** a seleção SHALL ser limpa, a página SHALL voltar para 1 e a URL do monitoring SHALL permanecer sem filtros

#### Scenario: Trocar de Office com cliente e preset
- **WHEN** o CurrentOffice mudar com cliente filtrado e lista de presets carregada
- **THEN** filtros, rótulos e presets do Office anterior MUST ser removidos antes da carga do novo Office

### Requirement: Tabela compartilhada preserva identidade e seleção segura
Cada lista SHALL fornecer uma chave de linha estável e única para a entidade exibida. Seleção SHALL ser limpa quando mudarem Office, rota, página, filtros ou ordenação e MAY ser preservada no refresh manual somente para IDs ainda presentes. Ações em massa MUST usar clientes deduplicados e MUST aparecer somente quando o módulo e o papel possuírem capacidade real.

#### Scenario: Guias do mesmo cliente
- **WHEN** duas guias do mesmo cliente forem exibidas
- **THEN** cada guia SHALL possuir chave de linha distinta e as ações por cliente SHALL deduplicar o `client_id`

#### Scenario: Troca de Office
- **WHEN** o usuário trocar o CurrentOffice mantendo a mesma rota
- **THEN** linhas e seleção do Office anterior MUST ser descartadas antes de ações sobre o novo contexto

#### Scenario: Módulo sem bulk fiscal
- **WHEN** Cadastro/Vínculos ou Processos forem exibidos
- **THEN** a tabela MUST NOT adicionar checkbox ou ações fiscais em massa por inferência do nome do módulo

### Requirement: Listas seguem o arquétipo compartilhado server-side
As listas de Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Declarações, FGTS, Guias, Cadastro/Vínculos e Processos SHALL usar a mesma casca compartilhada, com toolbar imediatamente acima de `UTable`, empty state dentro da tabela, visibilidade de colunas e footer de paginação server-side conforme o arquétipo `customers.vue` fixado. Filtros MUST permanecer no estado local e nas queries HTTP, sem `office_id` ou filtros efêmeros na URL do navegador.

#### Scenario: Filtrar lista paginada
- **WHEN** o usuário aplicar filtros em qualquer lista migrada
- **THEN** a API SHALL receber os valores normalizados, a URL do navegador SHALL permanecer canônica e a tabela SHALL preservar paginação e ordenação server-side
