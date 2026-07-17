## ADDED Requirements

### Requirement: Rotas canônicas identificam submódulos
O painel SHALL representar submódulos de Simples/MEI e DCTFWeb/MIT como segmentos do caminho, sem usar query string na URL canônica. Filtros efêmeros de carteira SHALL permanecer no estado local da página e `office_id` MUST NOT ser aceito ou propagado.

#### Scenario: Abrir PGDAS-D por caminho
- **WHEN** o usuário acessar a visão PGDAS-D de Simples/MEI
- **THEN** a URL canônica será `/monitoring/simples-mei/pgdasd` e a API continuará recebendo o código `PGDASD`

#### Scenario: Trocar submódulo pela navegação
- **WHEN** o usuário trocar de PGDAS-D para PGMEI
- **THEN** o painel navegará para `/monitoring/simples-mei/pgmei` sem acrescentar `submodule` ou `tab` à query string

#### Scenario: Hierarquia visual do submódulo
- **WHEN** um módulo possuir navegação interna, como PGDAS-D/PGMEI ou DCTFWeb/MIT
- **THEN** as tabs internas aparecerão abaixo da barra de módulos e antes da busca, filtros e dados da carteira

#### Scenario: Não propagar Office na URL
- **WHEN** uma URL de monitoramento contiver `office_id`
- **THEN** a navegação canônica removerá esse parâmetro e manterá o Office obtido exclusivamente da sessão

### Requirement: URLs legadas redirecionam para o caminho canônico
O painel SHALL redirecionar URLs legadas de Simples/MEI e DCTFWeb/MIT para a rota canônica equivalente, descartando a query string.

#### Scenario: Migrar deep-link legado
- **WHEN** o usuário acessar `/monitoring/simples-mei?submodule=PGMEI&situation=PENDING`
- **THEN** o painel redirecionará para `/monitoring/simples-mei/pgmei`

### Requirement: Filtros de monitoramento não alteram a rota
Busca, situação, competência, cliente, status, ordenação e paginação SHALL ser mantidos em estado local por instância de página e MUST NOT ser serializados na URL do navegador. Os mesmos valores MAY ser enviados como query HTTP aos endpoints da API.

#### Scenario: Filtrar pendências da DCTFWeb
- **WHEN** o usuário selecionar `Pendências` em `/monitoring/dctfweb/dctfweb`
- **THEN** a tabela será recarregada com `situation=PENDING` na requisição da API e a URL do navegador permanecerá `/monitoring/dctfweb/dctfweb`

#### Scenario: Normalizar submódulo desconhecido
- **WHEN** a URL informar um submódulo inexistente
- **THEN** o painel substituirá a localização pela rota do submódulo padrão do módulo

### Requirement: Navegação existente aponta para rotas canônicas
O painel SHALL manter todos os módulos diretamente visíveis na barra horizontal rolável e na sidebar, sem páginas de grupo ou menu de overflow. A faixa horizontal SHALL preservar seus rótulos e ordem anteriores, omitir ícones, preservar a tipografia padrão e compactar apenas o espaçamento. Na sidebar, os grupos principais SHALL preservar seus ícones e os itens filhos SHALL ser exibidos sem ícones; os filhos de `Monitoramento` SHALL usar rótulos próprios e resumidos, sem alterar os rótulos da barra. Os destinos de Simples/MEI e DCTFWeb/MIT SHALL apontar para seus submódulos padrão por caminho.

#### Scenario: Navegação visual permanece familiar
- **WHEN** o usuário abrir qualquer página de monitoramento
- **THEN** a barra continuará exibindo todos os módulos no mesmo nível, com os rótulos e a ordem anteriores e rolagem quando necessário

#### Scenario: Submenus seguem o padrão do shell
- **WHEN** a sidebar exibir os itens filhos de qualquer grupo principal
- **THEN** somente o grupo principal terá ícone e seus itens filhos serão apresentados apenas por rótulo

#### Scenario: Submenu de Monitoramento usa nomes resumidos
- **WHEN** a sidebar exibir os itens filhos de `Monitoramento`
- **THEN** serão usados rótulos curtos como `Resumo`, `Simples/MEI`, `Caixas`, `Vínculos` e `Processos`, sem alterar os nomes das tabs horizontais

#### Scenario: Abrir Simples pela navegação
- **WHEN** o usuário selecionar Simples/MEI na barra ou na sidebar
- **THEN** o painel navegará diretamente para `/monitoring/simples-mei/pgdasd`

### Requirement: Ações de carteira dependem de seleção
O painel SHALL manter o cabeçalho dos módulos fiscais livre de ações globais de carteira. Associar categorias, solicitar consulta e exportar SHALL aparecer somente no corpo da listagem quando houver ao menos uma linha selecionada. Cadastro de clientes SHALL permanecer na área própria de Clientes e nos atalhos autorizados.

#### Scenario: Abrir módulo sem seleção
- **WHEN** o usuário abrir um módulo fiscal sem selecionar linhas
- **THEN** o cabeçalho exibirá apenas o título e nenhuma ação de carteira será apresentada

#### Scenario: Selecionar clientes da carteira
- **WHEN** o usuário selecionar uma ou mais linhas e tiver a permissão necessária
- **THEN** as ações em massa compatíveis com o módulo serão exibidas junto aos filtros da tabela com a contagem selecionada

### Requirement: Sidebar separa operação e gestão
A sidebar SHALL usar grupos nativos do `UNavigationMenu` para separar áreas operacionais das áreas de gestão. `Início`, `Trabalho`, `Monitoramento`, `Documentos` e `Operações` SHALL compor o grupo operacional quando autorizados. `Clientes`, `Configurações` e `Admin` SHALL compor o grupo de gestão quando autorizados. Grupos vazios MUST NOT produzir separadores órfãos.

#### Scenario: Operador com acesso a clientes
- **WHEN** um operador autenticado abrir a sidebar
- **THEN** `Clientes` aparecerá abaixo do separador e as áreas operacionais aparecerão acima
