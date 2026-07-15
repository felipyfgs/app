## ADDED Requirements

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
