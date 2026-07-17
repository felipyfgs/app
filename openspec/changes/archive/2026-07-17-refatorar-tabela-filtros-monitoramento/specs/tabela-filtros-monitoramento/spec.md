## ADDED Requirements

### Requirement: Filtros rápidos e avançados possuem um único estado aplicado
As listas do monitoramento SHALL manter busca e situação como filtros rápidos e SHALL manter os demais campos em um rascunho avançado controlado. Busca SHALL aplicar após debounce ou submissão explícita, situação SHALL aplicar imediatamente e campos avançados MUST NOT alterar a consulta antes de `Aplicar filtros`.

#### Scenario: Aplicar filtro avançado após mudar situação
- **WHEN** o usuário abrir os filtros, alterar a situação rápida e depois aplicar competência ou cliente
- **THEN** a consulta SHALL combinar a situação mais recente com o rascunho avançado sem restaurar valores antigos e SHALL executar uma única recarga

#### Scenario: Fechar sem aplicar
- **WHEN** o usuário alterar um campo avançado e fechar o painel sem aplicar
- **THEN** o valor aplicado SHALL permanecer inalterado e o próximo painel aberto SHALL partir do estado aplicado

### Requirement: Painel avançado permanece recolhível e consistente
Os filtros adicionais SHALL aparecer em uma faixa inline recolhível entre a toolbar e a tabela. O botão `Filtros` SHALL usar um único gatilho de abertura, SHALL indicar quantos campos avançados aplicados diferem do default e a ação `Limpar` SHALL restaurar filtros rápidos e avançados, retornar à primeira página e provocar uma única recarga.

#### Scenario: Abrir e fechar o painel
- **WHEN** o usuário clicar uma vez no botão `Filtros`
- **THEN** o painel SHALL abrir uma vez e um segundo clique SHALL fechá-lo

#### Scenario: Limpar filtros combinados
- **WHEN** busca, situação e filtros avançados estiverem ativos e o usuário clicar em `Limpar`
- **THEN** todos SHALL voltar aos defaults, a paginação SHALL voltar à página 1 e a lista SHALL recarregar uma única vez

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
