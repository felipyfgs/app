## MODIFIED Requirements

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

## ADDED Requirements

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
