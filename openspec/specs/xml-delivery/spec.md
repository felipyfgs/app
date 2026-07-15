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
O sistema SHALL permitir download de XML e exportação ZIP a partir da identidade do documento no catálogo somente para kinds capturados no escopo escritural (NFS-e, NF-e, CT-e e NFC-e se habilitada). O sistema MUST NOT baixar nem exportar MDF-e.

#### Scenario: Download XML via documents
- **WHEN** um usuário autorizado solicita o XML de um documento existente via API de documents
- **THEN** o sistema entrega o XML original do vault sem expor material de certificado

#### Scenario: Download XML de NF-e
- **WHEN** um usuário autorizado solicita o XML de uma NF-e persistida
- **THEN** o sistema entrega os bytes do vault (procNFe ou resumo, conforme o que estiver armazenado como principal) sem expor certificado

#### Scenario: Tentativa de download MDF-e
- **WHEN** um cliente antigo solicita download usando uma identidade MDF-e
- **THEN** o sistema não localiza o documento no catálogo operacional e não acessa o vault

### Requirement: Export multi-tipo
O sistema SHALL suportar filtros de export por `kind` e, quando múltiplos kinds forem selecionados, organizar o ZIP de forma que cada arquivo seja identificável por kind e chave (prefixo de pasta ou nome de arquivo).

#### Scenario: Export só NFE
- **WHEN** o operador exporta com `kind=NFE` e chaves existentes
- **THEN** o ZIP contém apenas XML desse kind

#### Scenario: Export misto
- **WHEN** o operador exporta sem filtro de kind com NFS-e e NF-e no escopo
- **THEN** o ZIP inclui ambos sem colisão de nomes

### Requirement: Ponte de exportação a partir do catálogo
O sistema SHALL permitir que a interface de Notas dispare a mesma exportação ZIP assíncrona usada em Exportações, reutilizando o job e a auditoria existentes, sem embutir bytes XML na resposta JSON da solicitação.

#### Scenario: Atalho a partir de Notas
- **WHEN** o operador autorizado exporta filtro ou seleção a partir de Notas
- **THEN** é criado o mesmo tipo de recurso de exportação consultável em `/exports` (ou API equivalente), com `export.create` auditado

#### Scenario: Sem material sensível na resposta
- **WHEN** a criação da exportação retorna 202
- **THEN** o payload público não contém XML, caminhos internos de vault nem PEM

### Requirement: Export por direção
O sistema SHALL permitir export ZIP filtrando por direction e kind, organizando arquivos de forma identificável (ex.: pastas entrada/ e saida/ ou prefixo no nome).

#### Scenario: Export só entradas
- **WHEN** o operador exporta direction=IN
- **THEN** o ZIP não inclui documentos OUT do mesmo filtro de kind

### Requirement: Preferir XML completo na entrega
O sistema SHALL, no download e na exportação de NF-e, preferir o documento `procNFe` (completo) quando existir no vault; se só houver resumo, entregar o resumo e indicar na API/UI que o full ainda não está disponível (e, se a flag permitir, apontar a ação de obter XML completo).

#### Scenario: Download com full
- **WHEN** o usuário autorizado baixa uma NF-e que tem procNFe
- **THEN** o stream é o XML completo, não o resumo

#### Scenario: Download só resumo
- **WHEN** só existe resNFe
- **THEN** o download do resumo é permitido e a resposta/UI sinaliza limitação

### Requirement: Prontidão mensal explícita
Antes de gerar entrega mensal, o sistema SHALL classificar a competência como `COMPLETE_KNOWN`, `PARTIAL_CONFIRMED` ou `NOT_READY` com base nos documentos conhecidos e XMLs canônicos. O estado MUST acompanhar a exportação e sua auditoria.

#### Scenario: Todos os conhecidos capturados
- **WHEN** todas as chaves conhecidas elegíveis da competência possuem XML canônico
- **THEN** a exportação pode ser criada como `COMPLETE_KNOWN` sem alegar completude fiscal absoluta

### Requirement: Exportação parcial confirmada
OPERATOR ou ADMIN SHALL poder confirmar exportação parcial, recebendo manifesto das pendências pertencentes ao escritório. O sistema MUST NOT inventar XML, ocultar ausências nem permitir VIEWER confirmar entrega parcial.

#### Scenario: Exportação com cinco pendências
- **WHEN** um OPERATOR confirma a entrega parcial de uma competência com cinco XMLs ausentes
- **THEN** o ZIP contém somente XMLs válidos e um manifesto auditado das pendências autorizadas

#### Scenario: VIEWER tenta confirmar parcial
- **WHEN** um VIEWER solicita exportação `PARTIAL_CONFIRMED`
- **THEN** a API responde 403 e não cria o pacote

