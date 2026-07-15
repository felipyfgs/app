## ADDED Requirements

### Requirement: Proveniência de aquisição de saídas MA
O sistema SHALL manter aquisições múltiplas por documento com source, channel, estabelecimento, ambiente, referência externa permitida e horário, sem inventar NSU para importação, pacote MA ou consulta por sequência. A proveniência MUST ser derivada no backend e MUST NOT sobrescrever origem anterior.

#### Scenario: Import seguido de pacote MA
- **WHEN** o mesmo XML já importado é reencontrado em pacote oficial MA
- **THEN** permanece um conteúdo imutável no vault com duas aquisições auditáveis, sem duplicação ou troca de direção

#### Scenario: Aquisição de outro escritório
- **WHEN** usuário consulta proveniência por chave pertencente a outro escritório
- **THEN** o sistema não retorna existência, source, canal ou referência externa

### Requirement: Chave descoberta sem XML permanece fora do catálogo baixável
O sistema MUST manter chave/protocolo descobertos sem XML completo na operação de recuperação e MUST NOT criar `dfe_document`, projeção baixável ou sucesso de captura até persistir os bytes originais autorizados/protocolados.

#### Scenario: Recuperação pendente
- **WHEN** existe `KEY_DISCOVERED` sem XML validado
- **THEN** a chave aparece somente como pendência operacional e `has_full_xml=false`, sem endpoint de download fictício

#### Scenario: XML chega depois
- **WHEN** o XML original é validado e persistido
- **THEN** o catálogo passa a expor a projeção e o download do conteúdo real

### Requirement: Documento técnico autorizado é registro fiscal visível
Se uma operação experimental autorizar documento real, o sistema MUST armazenar XML, protocolo e eventos subsequentes, SHALL marcá-lo explicitamente como finalidade técnica e MUST NOT apagá-lo, ocultá-lo do catálogo ou tratá-lo como rollback após cancelamento.

#### Scenario: Autorização inesperada e cancelamento confirmado
- **WHEN** uma sonda resulta em autorização e depois evento de cancelamento válido
- **THEN** documento e evento permanecem imutáveis, a projeção mostra saída cancelada e a finalidade técnica é visível

#### Scenario: Cancelamento não confirmado
- **WHEN** existe documento técnico autorizado sem protocolo de cancelamento
- **THEN** o catálogo mostra a situação fiscal real e a operação mantém incidente crítico aberto

### Requirement: Divergência de bytes para a mesma chave
O sistema MUST preservar e colocar em quarentena novo XML com a mesma chave e SHA-256 diferente do canônico, sem substituir silenciosamente projeção ou conteúdo disponível.

#### Scenario: XML divergente
- **WHEN** pacote MA contém a mesma chave com bytes diferentes do documento existente
- **THEN** ambos os hashes são preservados, o novo artefato fica em revisão e nenhuma troca automática de canônico ocorre

## MODIFIED Requirements

### Requirement: Catálogo unificado entrada e saída
O sistema SHALL listar documentos de todas as fontes habilitadas (ADN, DistDFe, import e SEFAZ-MA outbound) com kind, direction, source, channel, modo de captura e disponibilidade de XML completo, filtráveis por kind e direction.

#### Scenario: Filtro combinação NF-e
- **WHEN** `kind=NFE` e `direction=OUT`
- **THEN** retorna apenas saídas NF-e modelo 55, incluindo import e canal MA com XML completo

#### Scenario: Filtro combinação NFC-e
- **WHEN** `kind=NFCE` e `direction=OUT`
- **THEN** retorna apenas saídas NFC-e modelo 65 capturadas por import ou canal MA

#### Scenario: Descoberta sem XML
- **WHEN** uma chave MA está em recuperação pendente sem bytes originais
- **THEN** ela não aparece como documento completo nem é contabilizada como XML entregue

