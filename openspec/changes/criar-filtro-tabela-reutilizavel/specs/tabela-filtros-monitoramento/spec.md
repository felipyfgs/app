## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Cada lista expõe somente filtros aceitos por sua API
A configuração do Monitoramento SHALL declarar campos explícitos e ordenados. Simples/MEI e DCTFWeb/MIT SHALL oferecer situação, cliente e competência; Parcelamentos e SITFIS SHALL oferecer situação e cliente; Declarações SHALL oferecer situação, cliente, competência e status de entrega; FGTS SHALL oferecer situação, cliente e competência; Guias SHALL oferecer cliente e status de pagamento; Cadastro/Vínculos e Processos SHALL oferecer status. Guias MUST NOT oferecer competência enquanto o endpoint não aplicar esse parâmetro.

#### Scenario: Abrir filtros de Guias
- **WHEN** o usuário abrir `Adicionar filtro` na lista de Guias
- **THEN** cliente e status de pagamento SHALL estar disponíveis e competência MUST NOT estar disponível

#### Scenario: Abrir filtros de Declarações
- **WHEN** o usuário abrir `Adicionar filtro` na lista de Declarações
- **THEN** situação, cliente, competência e status de entrega SHALL ser os campos disponíveis

### Requirement: Filtros estruturados respeitam seleção, rota e Office
Confirmar, remover ou limpar filtros SHALL invalidar a seleção da tabela e preservar a URL canônica sem query de filtros. Na troca de Office, filtros aplicados, rascunhos e rótulos visuais de cliente MUST ser descartados antes da nova carga. Requests MUST NOT conter `office_id`.

#### Scenario: Alterar filtro com linhas selecionadas
- **WHEN** o usuário confirmar, remover ou limpar um filtro
- **THEN** a seleção SHALL ser limpa, a página SHALL voltar para 1 e a URL do navegador SHALL permanecer sem filtros

#### Scenario: Trocar de Office com cliente filtrado
- **WHEN** o CurrentOffice mudar enquanto cliente e seu rótulo visual estiverem aplicados
- **THEN** ambos MUST ser removidos antes da carga do novo Office e nenhum request SHALL enviar `office_id`
