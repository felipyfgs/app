## MODIFIED Requirements

### Requirement: Documento original imutável
O sistema MUST armazenar os bytes XML originais criptografados, seu SHA-256 e metadados de identificação sem permitir alteração posterior do conteúdo.

#### Scenario: Documento persistido
- **WHEN** um XML distribuído é aceito
- **THEN** o conteúdo recuperado após descriptografia é byte a byte igual ao conteúdo recebido

### Requirement: Interesses e papéis fiscais
O sistema SHALL relacionar cada documento aos estabelecimentos interessados e aos papéis `ISSUER`, `TAKER` ou `INTERMEDIARY`, preservando NSUs independentes.

#### Scenario: Nota entre dois clientes do escritório
- **WHEN** a mesma NFS-e é distribuída ao prestador e ao tomador cadastrados no mesmo escritório
- **THEN** o sistema mantém um documento lógico e dois interesses com seus respectivos NSUs e papéis

### Requirement: Projeções de NFS-e e eventos
O sistema SHALL extrair de documentos conhecidos os campos necessários para consulta e SHALL aplicar eventos posteriores à situação derivada sem modificar a NFS-e original.

#### Scenario: Evento de cancelamento
- **WHEN** um evento de cancelamento válido é vinculado a uma chave de acesso existente
- **THEN** a projeção da nota passa a cancelada e ambos os XMLs permanecem imutáveis

### Requirement: Evolução de leiaute tolerante
O sistema SHALL registrar o resultado da validação por versão de XSD e SHALL conservar XML bem-formado mesmo quando a versão ou um campo ainda não for reconhecido.

#### Scenario: Versão nova de XML
- **WHEN** um documento bem-formado não corresponde aos XSD conhecidos
- **THEN** o sistema armazena o original, marca a projeção para revisão e permite o avanço seguro da página

### Requirement: Consulta paginada e filtrável
O sistema SHALL listar documentos fiscais do catálogo com paginação por cursor e filtros combináveis por **tipo (`kind`)**, cliente, estabelecimento, papel, situação, competência e data de emissão.

#### Scenario: Competência diferente da emissão
- **WHEN** o usuário filtra por competência sem informar data de emissão
- **THEN** o sistema aplica somente o período de competência e não confunde os dois campos

#### Scenario: Filtro por tipo NFS-e
- **WHEN** o cliente solicita o catálogo com `kind=NFSE` (ou omite kind)
- **THEN** o sistema retorna projeções NFS-e com `kind` e `source` preenchidos

#### Scenario: Filtro por tipo sem captura
- **WHEN** o cliente solicita o catálogo com `kind` de tipo ainda sem fonte implementada (ex.: `NFE`)
- **THEN** o sistema retorna lista vazia sem erro

### Requirement: Acesso restrito ao catálogo
O sistema MUST aplicar o escritório e o perfil do usuário em toda consulta e visualização de documento.

#### Scenario: Chave válida de outro escritório
- **WHEN** um usuário consulta diretamente uma chave pertencente a outro escritório
- **THEN** o sistema não retorna metadados nem conteúdo do documento

## ADDED Requirements

### Requirement: Identidade de tipo no catálogo
O sistema SHALL identificar cada item do catálogo com um `kind` de DF-e dos tipos mais comuns (`NFSE`, `NFE`, `NFCE`, `CTE`, `MDFE`) e um `source` de captura quando conhecido (`ADN`, `SEFAZ`, etc.).

#### Scenario: Item NFS-e serializado
- **WHEN** uma projeção NFS-e é listada ou detalhada
- **THEN** a resposta inclui `kind=NFSE`, `kind_label` legível e `source=ADN`

### Requirement: API canônica de documentos
O sistema SHALL expor o catálogo em `/api/v1/documents` (listagem, by-client, insights, detalhe, XML) e MAY manter `/api/v1/notes` como alias compatível com o mesmo comportamento.

#### Scenario: Alias notes
- **WHEN** um cliente autenticado chama `GET /api/v1/notes` com os mesmos filtros de documents
- **THEN** a resposta é equivalente à de `GET /api/v1/documents` para o mesmo escopo
