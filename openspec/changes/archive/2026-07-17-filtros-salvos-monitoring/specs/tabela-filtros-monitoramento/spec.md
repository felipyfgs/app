## ADDED Requirements

### Requirement: Toolbar permite salvar e reutilizar filtros na lista
As listas de dados cobertas por esta change SHALL expor na toolbar ações para salvar o estado aplicado atual como preset e para abrir a lista de presets (pessoais e compartilhados do Office). Salvar MUST exigir nome não vazio e MUST capturar busca e filtros estruturados ativos. Aplicar um preset SHALL hidratar o estado aplicado, voltar à página 1, limpar a seleção da tabela e disparar uma única recarga server-side. Fechar o modal de salvar sem confirmar MUST NOT criar preset.

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

## MODIFIED Requirements

### Requirement: Cada lista expõe somente filtros aceitos por sua API
A configuração do Monitoramento e das demais superfícies cobertas SHALL declarar campos explícitos e ordenados alinhados às colunas de negócio filtráveis e aos parâmetros realmente aplicados pela API da surface. Simples/MEI e DCTFWeb/MIT SHALL oferecer, no mínimo, situação, cliente, competência e demais colunas de negócio filtráveis do módulo; Parcelamentos e SITFIS SHALL incluir situação, cliente e eixos de coluna suportados (ex.: modalidade quando a API aplicar); Declarações SHALL incluir situação, cliente, competência, status de entrega e colunas de negócio filtráveis; FGTS SHALL incluir situação, cliente, competência e eixos de coluna suportados; Guias SHALL oferecer cliente, status de pagamento e demais eixos que a API de guias aplicar (competência só se o endpoint passar a aplicá-la); Cadastro/Vínculos e Processos SHALL oferecer status, cliente quando a API aceitar, e demais colunas filtráveis. A UI MUST NOT exibir filtro decorativo.

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
