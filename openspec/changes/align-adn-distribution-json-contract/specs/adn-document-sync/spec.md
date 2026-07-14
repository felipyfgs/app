## MODIFIED Requirements

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

## ADDED Requirements

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
