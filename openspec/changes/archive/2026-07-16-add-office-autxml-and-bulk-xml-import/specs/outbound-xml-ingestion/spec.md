## MODIFIED Requirements

### Requirement: Importação de XML de saída
O sistema SHALL permitir que `OPERATOR` e `ADMIN` criem um lote assíncrono, durável e isolado por `office_id` com um ou mais arquivos XML e/ou ZIP na mesma seleção, inclusive arquivos dos modelos NF-e 55 e NFC-e 65 e de diferentes empresas do escritório. O sistema MUST preservar os bytes originais aceitos no `SecureObjectStore`, projetar cada documento conforme seus interesses fiscais e concluir cada item independentemente, sem transformar falha parcial em perda dos itens válidos.

#### Scenario: Import procNFe
- **WHEN** um XML direto ou uma entrada de ZIP contém `procNFe` bem-formado, autorizado e emitido por estabelecimento do escritório
- **THEN** o sistema processa o item em fila, grava o `dfe_document` imutável, cria o interesse `ISSUER`/`OUT` e disponibiliza o XML no catálogo após a conclusão

#### Scenario: Seleção mista e multiempresa
- **WHEN** o usuário envia, no mesmo lote, múltiplos XML e ZIP com NF-e 55 e NFC-e 65 de mais de um estabelecimento do escritório
- **THEN** cada entrada é validada e associada individualmente sem exigir que todo o lote pertença a um único cliente

#### Scenario: Falha parcial
- **WHEN** um lote contém itens válidos, duplicados e inválidos
- **THEN** os itens válidos são importados, os duplicados são reconciliados de forma idempotente e os inválidos são reportados sem rollback dos demais

#### Scenario: Duplicata
- **WHEN** o mesmo SHA-256 já existe no escritório
- **THEN** o import não duplica bytes no vault, reporta o item como `DUPLICATE` e reconcilia aquisição e interesses ainda ausentes

#### Scenario: VIEWER
- **WHEN** `VIEWER` tenta criar, reenfileirar ou resolver um lote de importação
- **THEN** recebe 403 e nenhum arquivo, item, aquisição ou interesse é criado ou alterado

### Requirement: Kinds suportados no import MVP
O sistema SHALL aceitar como documentos principais somente `procNFe` autorizado e protocolado de NF-e modelo 55 e NFC-e modelo 65. O sistema SHALL aceitar `procEventoNFe` protocolado de cancelamento para os modelos 55/65 e de Carta de Correção para o modelo 55 quando permitido pelo leiaute oficial. O sistema MUST classificar explicitamente outros modelos, `resNFe`, DANFE, PDF, HTML, XML de consulta e `<NFe>` sem protocolo como `UNSUPPORTED` ou `INVALID`, sem projetá-los como documento fiscal completo.

#### Scenario: NF-e de saída
- **WHEN** o item é `procNFe` modelo 55 válido cujo emitente corresponde a estabelecimento do escritório
- **THEN** o sistema projeta `kind=NFE` e cria interesse `ISSUER` com direção `OUT`

#### Scenario: NFC-e de saída
- **WHEN** o item é `procNFe` modelo 65 válido cujo emitente corresponde a estabelecimento do escritório
- **THEN** o sistema projeta `kind=NFCE` e cria interesse `ISSUER` com direção `OUT`

#### Scenario: Evento de cancelamento ou Carta de Correção
- **WHEN** o lote contém `procEventoNFe` protocolado de cancelamento para modelo 55/65 ou Carta de Correção para modelo 55 cuja chave pertence a documento existente ou presente no mesmo lote
- **THEN** o sistema preserva o evento imutável, vincula-o à chave e atualiza a situação derivada quando aplicável, independentemente da ordem das entradas no ZIP

#### Scenario: Artefato sem protocolo ou modelo fora do escopo
- **WHEN** uma entrada contém somente `<NFe>`, `resNFe`, modelo diferente de 55/65 ou artefato que não seja XML fiscal suportado
- **THEN** o sistema não o apresenta como XML completo, classifica o item com código estável e continua os demais itens do lote

### Requirement: Sem emissão
O sistema MUST NOT usar A1, CSC ou ID CSC, consultar ou transmitir documento, manifestar operação, autorizar, cancelar ou inutilizar nota na SEFAZ durante o fluxo de importação; o canal SHALL apenas validar, preservar e projetar XML fiscal previamente autorizado/protocolado.

#### Scenario: Import não chama SEFAZ
- **WHEN** um lote é criado, processado, retomado ou reprocessado
- **THEN** nenhuma chamada a web service da SEFAZ nem leitura de credencial fiscal é efetuada pelo fluxo de importação

## ADDED Requirements

### Requirement: Lote assíncrono durável e retomável
O sistema SHALL persistir o lote e seus itens antes de iniciar o processamento, responder à criação com HTTP 202 e identificador opaco, e manter estados de lote `UPLOADED`, `QUEUED`, `PROCESSING`, `COMPLETED`, `COMPLETED_WITH_ERRORS` ou `FAILED`. Cada item SHALL manter estado durável `PENDING`, `IMPORTED`, `DUPLICATE`, `UNMATCHED`, `CLIENT_MISMATCH`, `INVALID`, `UNSUPPORTED`, `QUARANTINED` ou `FAILED`, tentativas e código sanitizado de resultado.

#### Scenario: Criação aceita para processamento
- **WHEN** `OPERATOR` ou `ADMIN` envia uma seleção que passa pelos limites de admissão
- **THEN** a API retorna 202 com o identificador e estado do lote, e o processamento continua em fila sem manter a requisição HTTP aberta

#### Scenario: Worker interrompido
- **WHEN** um worker termina inesperadamente depois de persistir parte dos itens
- **THEN** uma nova execução retoma somente os itens elegíveis sem duplicar bytes, aquisições, eventos ou interesses já concluídos

#### Scenario: Requisição idempotente repetida
- **WHEN** o cliente repete a criação com a mesma chave de idempotência e o mesmo digest de arquivos
- **THEN** o sistema devolve o lote existente e não cria processamento concorrente equivalente

### Requirement: Validação fiscal do XML importado
O sistema MUST validar XML bem-formado, família suportada, assinatura XML, chave e dígito verificador, correspondência entre `infNFe/@Id` e protocolo, autorização, modelo, `tpNF=1`, emitente e coerência dos campos codificados na chave antes de promover uma nota ao catálogo. Para `procEventoNFe`, o sistema MUST validar assinatura, identificador, chave, tipo, sequência, protocolo e situação de registro antes de vincular o evento. CNPJ e chave SHALL permanecer texto maiúsculo e sem máscara, inclusive quando alfanuméricos.

#### Scenario: procNFe autorizado coerente
- **WHEN** assinatura, protocolo, chave, modelo, emitente e `tpNF` são válidos e coerentes
- **THEN** o sistema preserva exatamente os bytes recebidos e permite a projeção do documento

#### Scenario: Assinatura ou protocolo inválido
- **WHEN** a assinatura não confere, o protocolo não autoriza o documento ou a chave diverge entre `infNFe` e `protNFe`
- **THEN** o item fica `INVALID`, não cria documento canônico baixável e expõe somente código e mensagem sanitizados

#### Scenario: Evento não registrado ou incoerente
- **WHEN** assinatura, identificador, chave, tipo, sequência ou protocolo do `procEventoNFe` não é válido e registrado
- **THEN** o evento fica `INVALID`, não altera a situação do documento e não é apresentado como evento fiscal aceito

#### Scenario: Versão XSD desconhecida
- **WHEN** o XML é bem-formado e sua identidade, assinatura e autorização podem ser verificadas, mas a versão de XSD ainda não é conhecida
- **THEN** os bytes são preservados com alerta de parse e o item não é perdido apenas pelo desconhecimento da versão

### Requirement: Parser XML sem acesso externo
O sistema MUST processar XML com acesso de rede, filesystem, DTD, declarações de entidade e XInclude desabilitados, e SHALL aplicar limites configuráveis de profundidade e quantidade de nós. Conteúdo proibido MUST ser recusado antes de projeção ou validação que possa resolver recurso externo.

#### Scenario: XML com DTD ou entidade
- **WHEN** um XML contém `DOCTYPE`, declaração de entidade ou referência externa
- **THEN** o item fica `INVALID`, nenhuma rede ou leitura de arquivo local ocorre e os demais itens continuam

#### Scenario: XML excede complexidade permitida
- **WHEN** profundidade ou quantidade de nós excede o teto configurado
- **THEN** o parser interrompe o item com resultado sanitizado sem esgotar a memória do worker

### Requirement: ZIP seguro com limites configuráveis
O sistema MUST inspecionar cada ZIP antes de ingerir suas entradas e reforçar os limites durante a leitura em streaming. Os valores iniciais SHALL ser configuráveis e alinhados entre aplicação, Nginx, PHP-FPM e workers: no máximo 50 arquivos de entrada, 20 MiB de payload total enviado, 5.000 entradas XML por lote, 5 MiB por XML direto ou descompactado, 250 MiB descompactados por lote e razão de compressão máxima de 100:1.

O sistema MUST NOT extrair entradas usando caminhos fornecidos pelo arquivo e MUST rejeitar ZIP aninhado, criptografado, multidisco, links, caminho absoluto, `..`, NUL, entrada repetida após normalização ou expansão que ultrapasse qualquer teto. Toda entrada não diretório SHALL receber resultado explícito; tipos não suportados não podem ser ignorados silenciosamente.

#### Scenario: Lote excede limite de admissão
- **WHEN** quantidade de arquivos ou payload HTTP ultrapassa o teto configurado
- **THEN** a API rejeita a criação antes de persistir documentos e informa limite e código sanitizados

#### Scenario: ZIP com expansão abusiva
- **WHEN** contagem, tamanho individual, total descompactado ou razão de compressão ultrapassa o teto durante preflight ou streaming
- **THEN** nenhuma entrada daquele ZIP é promovida ao catálogo, o arquivo recebe resultado de limite excedido e outros arquivos seguros do lote podem continuar

#### Scenario: ZIP com caminho ou recurso proibido
- **WHEN** o ZIP contém traversal, caminho absoluto, NUL, link, entrada duplicada normalizada, arquivo aninhado ou criptografado
- **THEN** o ZIP é recusado sem escrever o caminho no filesystem e sem processar parcialmente suas entradas

#### Scenario: ZIP válido com subdiretórios
- **WHEN** um ZIP dentro dos limites organiza XML em subdiretórios com nomes seguros
- **THEN** as entradas são lidas por stream, identificadas por caminho normalizado e processadas sem extração para diretório controlado pelo arquivo

### Requirement: Proteção e descarte dos arquivos transitórios
O sistema MUST manter uploads e arquivos expandidos somente em armazenamento privado criptografado ou temporário com permissão restrita, MUST NOT persistir XML bruto nas tabelas de lote e SHALL apagar material transitório após o prazo configurado ou conclusão definitiva, inclusive por rotina de limpeza para jobs interrompidos.

#### Scenario: Processamento concluído
- **WHEN** todos os itens chegam a estado terminal e termina o prazo de retenção operacional
- **THEN** o sistema remove o arquivo original e temporários, preservando somente documentos aceitos no vault e metadados sanitizados do relatório

#### Scenario: Falha durante abertura do ZIP
- **WHEN** o worker falha depois de materializar arquivo temporário privado
- **THEN** a limpeza imediata ou o coletor posterior remove o resíduo sem expor seu caminho em API, log ou auditoria

### Requirement: Associação automática por CNPJ completo
O sistema MUST derivar o escritório da sessão ou job e SHALL associar cada nota pelo CNPJ completo e exato de `emit`, normalizado como texto maiúsculo, a estabelecimento ativo desse escritório. Um `client_id` ou `establishment_id` opcional SHALL funcionar apenas como restrição de conferência e nunca substituir a identidade contida no XML. A presença do CNPJ do escritório em `autXML` MUST NOT ser usada para determinar o cliente emitente.

#### Scenario: ZIP multiempresa sem filtro de cliente
- **WHEN** um lote contém documentos de diferentes emitentes cadastrados no mesmo escritório e nenhum cliente foi selecionado
- **THEN** cada item é vinculado ao estabelecimento exato indicado em `emit/CNPJ`

#### Scenario: Cliente selecionado diverge do emitente
- **WHEN** o usuário restringe o lote a um cliente e o CNPJ emitente de um item não pertence a estabelecimento desse cliente
- **THEN** o item fica `CLIENT_MISMATCH`, sem ser associado por raiz, destinatário ou `autXML`

#### Scenario: Emitente ausente, inativo ou desconhecido
- **WHEN** não existe estabelecimento ativo e inequívoco para o CNPJ completo do emitente no escritório
- **THEN** o item fica em quarentena criptografada como `UNMATCHED`, não entra no catálogo de cliente e pode ser reavaliado após cadastro autorizado

#### Scenario: Tentativa de usar estabelecimento de outro escritório
- **WHEN** um identificador enviado ou um CNPJ do XML corresponde somente a estabelecimento de outro `office_id`
- **THEN** o sistema não revela o alvo, não cria vínculo e devolve o mesmo resultado sanitizado de item sem associação

### Requirement: Idempotência, quarentena e interesses múltiplos
O sistema MUST manter idempotência por `office_id` e SHA-256, unicidade canônica por identidade fiscal do documento e proveniência separada para `MANUAL_XML`, `MANUAL_ZIP` e demais canais. Reprocessar bytes existentes SHALL acrescentar aquisição ou interesse ausente sem duplicar o vault e sem inventar NSU. Um novo XML principal com a mesma chave e SHA-256 diferente MUST ser preservado em quarentena e MUST NOT substituir silenciosamente os bytes ou a projeção canônica.

#### Scenario: Mesmo XML por canal diferente
- **WHEN** um XML já capturado por `AUTXML_DIST_NSU` ou outro canal é enviado por XML direto ou ZIP
- **THEN** o vault permanece com uma cópia, a aquisição manual é registrada e o item termina como `DUPLICATE`

#### Scenario: Duplicata corrige interesse ausente
- **WHEN** o mesmo SHA-256 já existe, mas ainda não há interesse para um estabelecimento gerenciado identificado no XML
- **THEN** o sistema cria o interesse faltante de forma idempotente sem recriar o documento

#### Scenario: Mesma chave com bytes divergentes
- **WHEN** chega `procNFe` com chave canônica existente e SHA-256 diferente
- **THEN** os novos bytes ficam `QUARANTINED`, o documento canônico não é alterado e um alerta de conflito fica disponível para revisão

#### Scenario: Escritório atende emitente e destinatário
- **WHEN** o mesmo documento envolve um estabelecimento gerenciado como emitente e outro estabelecimento gerenciado como destinatário
- **THEN** o sistema mantém interesses distintos `ISSUER`/`OUT` e `TAKER`/`IN` relativos a cada estabelecimento, sem duplicar bytes nem sobrescrever um papel pelo outro

#### Scenario: Corrida entre workers
- **WHEN** dois workers tentam persistir simultaneamente o mesmo SHA-256, chave, aquisição ou interesse
- **THEN** constraints e reconciliação tratam o segundo processamento como idempotente, sem erro permanente nem estado parcial

### Requirement: Relatório paginado, sanitizado e reprocessável
O sistema SHALL expor totais do lote e itens paginados por estado, com nome de arquivo sanitizado, índice da entrada, kind, chave quando validada, estabelecimento associado, código estável e mensagem segura. O sistema MUST NOT incluir XML bruto, assinatura, caminho temporário, referência de vault, stack trace, A1, senha, CSC, chave privada ou PEM em resposta comum, log, métrica ou auditoria. Reprocessamento SHALL ser permitido somente para itens `UNMATCHED` ou `FAILED` por causa transitória, sem reabrir `CLIENT_MISMATCH` ou itens fiscais conflitantes por aceitação cega.

#### Scenario: Lote concluído com erros
- **WHEN** o lote termina com itens importados e itens não concluídos
- **THEN** o estado é `COMPLETED_WITH_ERRORS` e a API informa contagens de importados, duplicados, sem vínculo, divergência de cliente, inválidos, não suportados, quarentenados e falhos

#### Scenario: Reprocessar item sem vínculo
- **WHEN** um estabelecimento antes desconhecido é cadastrado e usuário autorizado solicita, dentro do prazo de retenção, nova tentativa dos itens `UNMATCHED`
- **THEN** o sistema reavalia os bytes privados existentes, registra a tentativa e conclui apenas os itens cuja associação passou a ser inequívoca

#### Scenario: Erro interno contém dado fiscal
- **WHEN** parser, vault ou worker lança erro que contenha trecho de XML, caminho ou dado sensível
- **THEN** API, log e auditoria registram somente código allowlisted, correlação, ator, lote, resultado e mensagem sanitizada
