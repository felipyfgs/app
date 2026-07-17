# tabela-filtros-monitoramento Specification

## Purpose
Filtros e casca compartilhada das nove listas padronizadas de Monitoramento: busca dedicada, chips estruturados por API, paginação server-side e isolamento por Office.

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
