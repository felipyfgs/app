## MODIFIED Requirements

### Requirement: Exportação ZIP assíncrona
O sistema SHALL criar ZIPs em fila a partir dos **mesmos filtros do catálogo de notas** (incluindo cliente e estabelecimento quando informados), com opção explícita para incluir eventos, e SHALL aceitar escopo por lista limitada de chaves de acesso (`access_keys`) para exportação de seleção em lote. O sistema MUST aplicar o escritório do usuário e a permissão de exportar (ADMIN/OPERATOR).

#### Scenario: Solicitação de exportação por filtros
- **WHEN** um usuário autorizado envia filtros válidos do catálogo
- **THEN** o sistema cria uma exportação `PENDING`, retorna seu identificador e a processa fora da requisição web

#### Scenario: Filtro por cliente do escritório
- **WHEN** o filtro inclui `client_id` do office ativo
- **THEN** o ZIP contém apenas notas com interesse em estabelecimento desse cliente no mesmo office

#### Scenario: Exportação por lista de chaves
- **WHEN** o usuário autorizado envia `access_keys` com N chaves (1 ≤ N ≤ teto configurado)
- **THEN** o job inclui somente notas dessas chaves pertencentes ao office e rejeita ou ignora chaves inexistentes/de outro office sem vazar existência indevida além do escopo do office

#### Scenario: Acima do teto de chaves
- **WHEN** a lista de chaves excede o teto permitido
- **THEN** a API responde com erro de validação e não cria exportação

#### Scenario: VIEWER não exporta
- **WHEN** um VIEWER tenta criar exportação
- **THEN** o sistema responde 403

## ADDED Requirements

### Requirement: Ponte de exportação a partir do catálogo
O sistema SHALL permitir que a interface de Notas dispare a mesma exportação ZIP assíncrona usada em Exportações, reutilizando o job e a auditoria existentes, sem embutir bytes XML na resposta JSON da solicitação.

#### Scenario: Atalho a partir de Notas
- **WHEN** o operador autorizado exporta filtro ou seleção a partir de Notas
- **THEN** é criado o mesmo tipo de recurso de exportação consultável em `/exports` (ou API equivalente), com `export.create` auditado

#### Scenario: Sem material sensível na resposta
- **WHEN** a criação da exportação retorna 202
- **THEN** o payload público não contém XML, caminhos internos de vault nem PEM
