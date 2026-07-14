# Fiscal Document Catalog

## Purpose

Armazenamento imutável de XML, interesses/papéis fiscais, projeções NFS-e/eventos e consulta filtrável do catálogo.

## Requirements

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
O sistema SHALL listar notas com paginação por cursor e filtros combináveis por cliente, estabelecimento, papel, situação, competência e data de emissão.

#### Scenario: Competência diferente da emissão
- **WHEN** o usuário filtra por competência sem informar data de emissão
- **THEN** o sistema aplica somente o período de competência e não confunde os dois campos

### Requirement: Acesso restrito ao catálogo
O sistema MUST aplicar o escritório e o perfil do usuário em toda consulta e visualização de documento.

#### Scenario: Chave válida de outro escritório
- **WHEN** um usuário consulta diretamente uma chave pertencente a outro escritório
- **THEN** o sistema não retorna metadados nem conteúdo do documento
