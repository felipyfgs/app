# ADN Document Sync

## Purpose

Captura de documentos NFS-e via API oficial do ADN (mTLS, cursor por estabelecimento/NSU, persistência atômica e sincronização horária).

## Requirements

### Requirement: Comunicação oficial por mTLS
O sistema MUST consultar a API de contribuintes do ADN por mTLS usando o A1 da mesma raiz do CNPJ consultado, TLS 1.2 ou superior e validação de hostname e cadeia habilitadas. O transporte MUST usar o PFX somente em memória (sem materializar PEM ou chave privada em disco) e MUST NOT depender de biblioteca comunitária de ADN/NFS-e como cliente de runtime.

#### Scenario: Consulta de filial
- **WHEN** um estabelecimento usa o certificado ativo de sua raiz
- **THEN** o cliente envia o CNPJ completo em `cnpjConsulta` e o PFX somente em memória

#### Scenario: Falha de verificação TLS
- **WHEN** a cadeia ou o hostname do servidor não pode ser validado
- **THEN** o sistema encerra a chamada e não aceita a resposta

#### Scenario: Biblioteca comunitária proibida no runtime
- **WHEN** o grafo de dependências ou o transporte de produção é inspecionado
- **THEN** a comunicação ADN não passa por cliente comunitário que grave PEM temporário ou desative verificação TLS

### Requirement: Cursor independente por estabelecimento
O sistema SHALL manter um último NSU por estabelecimento e ambiente, iniciando em zero e consultando lotes até alcançar o fim da distribuição disponível. A consulta MUST usar `GET /DFe/{NSU}` com o último NSU consumido e `lote=true` quando a captura em lote estiver habilitada.

#### Scenario: Primeira sincronização
- **WHEN** um estabelecimento ainda não possui histórico de sincronização
- **THEN** a primeira consulta usa NSU zero e processa o histórico fornecido pelo ADN

#### Scenario: Nenhum documento novo
- **WHEN** o ADN responde `StatusProcessamento` igual a `NENHUM_DOCUMENTO_LOCALIZADO`
- **THEN** o sistema preserva o cursor e agenda a próxima execução para uma hora depois

### Requirement: Persistência atômica e idempotente de página
O sistema MUST persistir todos os documentos válidos da página antes de avançar o cursor e MUST tolerar o reprocessamento da mesma página sem duplicação.

#### Scenario: Falha parcial de banco
- **WHEN** qualquer persistência da página falha antes do commit
- **THEN** o cursor não avança e a página inteira pode ser processada novamente

#### Scenario: Documento já persistido
- **WHEN** uma repetição contém o mesmo estabelecimento, NSU e documento
- **THEN** o sistema mantém uma única associação e conclui a página sem duplicar o XML

### Requirement: Decodificação dos documentos distribuídos
O sistema SHALL decodificar o Base64 e o GZip de cada item e SHALL preservar os bytes XML resultantes sem normalização.

#### Scenario: Payload corrompido
- **WHEN** um item não pode ser decodificado como Base64/GZip
- **THEN** o sistema não avança o cursor, registra uma falha sanitizada e agenda uma tentativa

### Requirement: Processamento justo e limitado
O sistema SHALL limitar cada job a 20 páginas, impedir duas execuções simultâneas do mesmo estabelecimento e aplicar limites globais configuráveis de concorrência e taxa.

#### Scenario: Cliente com grande backfill
- **WHEN** um job conclui sua vigésima página e ainda existem documentos
- **THEN** o sistema atualiza o progresso e reenfileira o estabelecimento após os demais trabalhos elegíveis

### Requirement: Tratamento explícito de falhas
O sistema SHALL aplicar backoff exponencial com jitter a 429 e 5xx e SHALL bloquear o cursor em erros permanentes ou após cinco falhas consecutivas de decodificação.

#### Scenario: Limite de requisições
- **WHEN** o ADN responde HTTP 429
- **THEN** o sistema preserva o cursor e reagenda a chamada respeitando o atraso calculado

#### Scenario: Quinta falha de decodificação
- **WHEN** o mesmo cursor acumula cinco falhas consecutivas de decodificação
- **THEN** o estabelecimento passa a `BLOCKED` e requer intervenção sem pular o NSU

### Requirement: Sincronização horária distribuída
O sistema SHALL selecionar cursores vencidos a cada minuto e distribuir deterministicamente suas execuções ao longo de uma janela máxima de uma hora.

#### Scenario: Mais de mil estabelecimentos vencidos
- **WHEN** começa um novo ciclo horário
- **THEN** o Scheduler escalona todos os estabelecimentos elegíveis sem dispará-los simultaneamente

### Requirement: Envelope JSON da distribuição de contribuintes
O sistema MUST interpretar a resposta de distribuição do ADN como envelope JSON com `StatusProcessamento` e, quando houver documentos, array `LoteDFe`. O sistema MUST mapear cada item do lote para o modelo interno antes de persistir e MUST NOT exigir campos legados de envelope XML (`cStat`, `docZip`, `retDistDFeInt`) na API real.

#### Scenario: Documentos localizados
- **WHEN** o ADN responde HTTP 2xx com `StatusProcessamento` = `DOCUMENTOS_LOCALIZADOS` e `LoteDFe` contendo um ou mais itens com `NSU`, `TipoDocumento` e `ArquivoXml`
- **THEN** o cliente converte a página em DTO interno com documentos tipados e indica que há continuidade de sincronização enquanto o status for de documentos localizados e o lote não estiver vazio

#### Scenario: Rejeição oficial
- **WHEN** o ADN responde com `StatusProcessamento` = `REJEICAO` ou com erros estruturais no envelope
- **THEN** o sistema não avança o NSU, registra falha sanitizada (sem corpo bruto sensível em log de aplicação) e aplica a política de erro permanente ou retentativa conforme a classe do erro

#### Scenario: Tipo de documento no lote
- **WHEN** um item do lote traz `TipoDocumento` = `NFSE` ou `EVENTO`
- **THEN** o sistema classifica o item como nota ou evento respectivamente e encaminha o `ArquivoXml` à decodificação Base64/GZip sem alterar os bytes XML resultantes

### Requirement: Semântica de paginação sem maxNSU no JSON
Na ausência dos campos `maxNSU` e `ultNSU` no envelope JSON oficial, o sistema MUST derivar o NSU final da página como o maior `NSU` presente em `LoteDFe` e MUST usar o status de processamento (e a presença de itens) para decidir se reenfileira a sincronização, sem inventar avanço de cursor além dos documentos efetivamente aceitos na página.

#### Scenario: Página intermediária com lote
- **WHEN** a página retorna `DOCUMENTOS_LOCALIZADOS` com vários itens de NSU contíguos maiores que o cursor atual
- **THEN** após persistir a página com sucesso o cursor avança para o maior NSU do lote e a sincronização pode continuar na mesma execução ou por reenfileiramento justo

#### Scenario: Fim da distribuição
- **WHEN** a página retorna `NENHUM_DOCUMENTO_LOCALIZADO` com lote vazio
- **THEN** o cursor permanece no último NSU confirmado e `hasMore` é falso

### Requirement: Decodificação a partir de ArquivoXml
O sistema SHALL decodificar o campo `ArquivoXml` de cada item do lote como Base64 seguido de GZip e SHALL preservar os bytes XML resultantes sem normalização, aplicando a mesma regra de não avançar o cursor em falha de decodificação já exigida para documentos distribuídos.

#### Scenario: Payload GZip válido
- **WHEN** um item `NFSE` traz `ArquivoXml` Base64+GZip bem-formado
- **THEN** o sistema obtém o XML original, calcula SHA-256 e persiste o objeto no cofre antes de concluir a página

#### Scenario: Payload corrompido no lote JSON
- **WHEN** um item do lote não pode ser decodificado como Base64/GZip
- **THEN** o sistema não avança o cursor, registra falha sanitizada e agenda nova tentativa conforme a política de falhas de decodificação

### Requirement: Mapeamento de cStat da NFS-e no parse ADN
O sistema SHALL, ao projetar NFS-e a partir do XML distribuído pelo ADN, mapear o `cStat` da nota conforme o leiaute nacional: `100` → situação gerada/ativa, `101` → substituta, sem tratar `101` como cancelamento.

#### Scenario: Parse cStat 100
- **WHEN** o XML da nota contém cStat 100
- **THEN** a projeção persiste official_status_code 100 e status ACTIVE

#### Scenario: Parse cStat 101
- **WHEN** o XML da nota contém cStat 101
- **THEN** a projeção persiste official_status_code 101 e status SUBSTITUTE

#### Scenario: Evento de cancelamento na sincronização
- **WHEN** a página ADN inclui evento de cancelamento vinculado a uma chave já projetada
- **THEN** o evento é persistido e a projeção da nota é atualizada para CANCELLED ou SUPERSEDED conforme o tipo de cancelamento (simples vs por substituição)

### Requirement: Não saltar documento por status desconhecido
O sistema SHALL persistir XML bem-formado mesmo quando o cStat for desconhecido, marcando status UNKNOWN na projeção quando não houver mapeamento, sem avançar NSU em falha de decode (regras ADN existentes permanecem).

#### Scenario: cStat não mapeado
- **WHEN** o XML é bem-formado mas o cStat não está na tabela de mapeamento
- **THEN** o documento e a projeção são persistidos com status UNKNOWN e official_status_code igual ao valor encontrado

### Requirement: Coexistência ADN e SEFAZ
O sistema SHALL manter a captura ADN de NFS-e independente dos canais SEFAZ: falha ou bloqueio em DistDFe MUST NOT interromper cursors ADN do mesmo estabelecimento, e vice-versa.

#### Scenario: DistDFe bloqueado, ADN segue
- **WHEN** o cursor DistDFe está BLOCKED e o cursor ADN está IDLE com captura ligada
- **THEN** o scheduler continua elegendo o estabelecimento para jobs ADN

### Requirement: Direction a partir do papel ADN
O sistema SHALL, ao projetar NFS-e, preencher direction: ISSUER→OUT, TAKER→IN, INTERMEDIARY→IN (ou política documentada), sem alterar o XML imutável.

#### Scenario: Backfill
- **WHEN** notas NFS-e legadas não têm direction
- **THEN** um comando ou migração deriva direction a partir de fiscal_role existente
