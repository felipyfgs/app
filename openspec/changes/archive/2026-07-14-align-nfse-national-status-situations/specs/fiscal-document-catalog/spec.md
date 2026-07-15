## ADDED Requirements

### Requirement: Situação da NFS-e alinhada ao padrão nacional
O sistema SHALL projetar a situação operacional da NFS-e a partir do `cStat` do XML nacional e dos eventos de cancelamento/substituição, e SHALL persistir o código oficial em `official_status_code` sem inventar situação.

#### Scenario: NFS-e gerada (cStat 100)
- **WHEN** o parse obtém cStat `100`
- **THEN** a projeção grava `official_status_code=100` e `status=ACTIVE` (label de UI: Gerada)

#### Scenario: NFS-e de substituição gerada (cStat 101)
- **WHEN** o parse obtém cStat `101`
- **THEN** a projeção grava `official_status_code=101` e `status=SUBSTITUTE` (label: Substituta), e MUST NOT gravar `CANCELLED` só por esse cStat

#### Scenario: cStat ausente ou desconhecido
- **WHEN** o parse não obtém cStat reconhecido
- **THEN** `status=UNKNOWN` e o XML bem-formado continua preservado

#### Scenario: Cancelamento por evento
- **WHEN** um evento de cancelamento de NFS-e é persistido para a chave
- **THEN** a projeção da nota passa a `status=CANCELLED` sem apagar o XML original

#### Scenario: Cancelamento por substituição
- **WHEN** um evento de cancelamento por substituição é persistido na nota original
- **THEN** a projeção da original passa a `status=SUPERSEDED` (label: Substituída)

### Requirement: Listagem e detalhe expõem situação legível e cStat
O sistema SHALL expor na API de catálogo e detalhe o `status` operacional e o `official_status_code`, de modo que a interface possa mostrar situação legível e o código oficial sem baixar o XML.

#### Scenario: Item de listagem
- **WHEN** o cliente lista notas
- **THEN** cada item inclui `status` e `official_status_code` (quando conhecido) sem corpo XML

#### Scenario: Detalhe
- **WHEN** o usuário abre o detalhe por chave
- **THEN** a resposta permite apresentar situação + cStat e eventos relacionados

## MODIFIED Requirements

### Requirement: Projeções de NFS-e e eventos
O sistema SHALL manter projeções de NFS-e e eventos a partir do XML capturado, incluindo número, partes, valor, competência, papel, locais quando parseados, **situação operacional alinhada ao cStat nacional e eventos**, e código de situação oficial quando existir.

#### Scenario: Parse bem-sucedido com projeção
- **WHEN** o XML da NFS-e é bem-formado e o parse extrai campos conhecidos
- **THEN** a projeção é atualizada com os campos extraídos e o status derivado do cStat (e eventos já aplicados)

#### Scenario: Evento posterior altera situação
- **WHEN** um evento de cancelamento ou substituição é processado após a nota
- **THEN** a projeção de status da nota é atualizada conforme o tipo de evento e o evento permanece no histórico
