# XML Delivery

## Purpose

Download individual auditado e exportação ZIP assíncrona com estrutura determinística e expiração.

## Requirements

### Requirement: Download individual auditado
O sistema SHALL permitir o download do XML original por usuário autorizado e SHALL registrar usuário, documento, escritório, horário e resultado.

#### Scenario: Download autorizado
- **WHEN** um usuário autorizado solicita o XML de uma nota de seu escritório
- **THEN** o sistema descriptografa e transmite o conteúdo original como anexo sem expor o caminho de armazenamento

### Requirement: Exportação ZIP assíncrona
O sistema SHALL criar ZIPs em fila a partir dos **mesmos filtros do catálogo de notas** (incluindo cliente e estabelecimento quando informados), com opção explícita para incluir eventos, e SHALL aceitar escopo por lista limitada de chaves de acesso (`access_keys`) para exportação de seleção em lote. O sistema MUST aplicar o escritório do usuário e a permissão de exportar (ADMIN/OPERATOR).

#### Scenario: Solicitação de exportação
- **WHEN** um usuário autorizado envia filtros válidos
- **THEN** o sistema cria uma exportação `PENDING`, retorna seu identificador e a processa fora da requisição web

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

### Requirement: Estrutura determinística do ZIP
O sistema SHALL organizar cada NFS-e como `CNPJ/AAAA-MM/papel/chave.xml`, usando `sem-competencia` quando a competência não existir e nomes seguros para filesystem.

#### Scenario: ZIP concluído
- **WHEN** o job termina uma exportação com notas emitidas e recebidas
- **THEN** cada XML aparece uma única vez no diretório correspondente ao interesse selecionado

### Requirement: Entrega privada e expiração
O sistema MUST manter exportações fora da área pública, autorizá-las a cada download e apagá-las 24 horas após a conclusão.

#### Scenario: Exportação expirada
- **WHEN** um usuário solicita uma exportação após seu prazo de 24 horas
- **THEN** o sistema nega o download, marca o recurso como expirado e remove o arquivo remanescente

### Requirement: Falhas de exportação observáveis
O sistema SHALL marcar uma exportação como `FAILED` com mensagem sanitizada quando não puder concluí-la, sem deixar ZIP parcial disponível.

#### Scenario: Falha ao ler um XML
- **WHEN** qualquer objeto selecionado não pode ser descriptografado
- **THEN** o job remove o ZIP parcial, registra a falha e não oferece link de download

### Requirement: Download e export no catálogo de documentos
O sistema SHALL permitir download de XML e exportação ZIP a partir da identidade de documento do catálogo (chave de acesso), incluindo a superfície Documentos (`/docs`). Enquanto apenas NFS-e tiver captura no export, o sistema SHOULD recusar ou avisar quando o filtro de tipo excluir NFS-e.

#### Scenario: Download XML via documents
- **WHEN** um usuário autorizado solicita o XML de um documento existente via API de documents
- **THEN** o sistema entrega o XML original do vault sem expor material de certificado

#### Scenario: Export com filtro de tipo sem NFS-e
- **WHEN** o operador tenta exportar com filtro de kind que não inclui NFS-e
- **THEN** o sistema não gera ZIP vazio silencioso: desabilita a ação ou informa que export multi-tipo ainda não está disponível

### Requirement: Ponte de exportação a partir do catálogo
O sistema SHALL permitir que a interface de Notas dispare a mesma exportação ZIP assíncrona usada em Exportações, reutilizando o job e a auditoria existentes, sem embutir bytes XML na resposta JSON da solicitação.

#### Scenario: Atalho a partir de Notas
- **WHEN** o operador autorizado exporta filtro ou seleção a partir de Notas
- **THEN** é criado o mesmo tipo de recurso de exportação consultável em `/exports` (ou API equivalente), com `export.create` auditado

#### Scenario: Sem material sensível na resposta
- **WHEN** a criação da exportação retorna 202
- **THEN** o payload público não contém XML, caminhos internos de vault nem PEM
