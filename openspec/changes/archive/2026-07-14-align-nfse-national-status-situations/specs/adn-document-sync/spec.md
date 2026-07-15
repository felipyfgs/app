## ADDED Requirements

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
