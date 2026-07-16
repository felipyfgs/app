## ADDED Requirements

### Requirement: Autoridade consolidada do cursor ADN
O sistema MUST manter uma única autoridade de progresso ADN por escritório, Estabelecimento e ambiente, distinguida do DistDFe e de streams do autor, e SHALL impedir escrita no cursor legado depois do corte.

#### Scenario: Migração de cursor existente
- **WHEN** um cursor ADN legado é migrado
- **THEN** o novo cursor preserva último NSU confirmado, estado, contagem de falhas, bloqueio e agendamento sem retroceder nem avançar o progresso

#### Scenario: Canais coexistentes
- **WHEN** o mesmo Estabelecimento possui ADN e DistDFe habilitados
- **THEN** cada canal usa seu cursor, lock e política de falha independentes

### Requirement: Aquisição ADN e avanço permanecem atômicos
A página ADN, suas aquisições documentais e o novo NSU MUST ser confirmados na mesma transação; falha em qualquer item MUST preservar o cursor anterior e permitir reprocessamento idempotente.

#### Scenario: Documento canônico já existe
- **WHEN** uma página ADN contém XML já capturado por outra fonte
- **THEN** o sistema registra a aquisição ADN e o interesse comprovado antes de avançar o NSU, sem duplicar o documento

#### Scenario: Falha de backfill de aquisição
- **WHEN** o cursor foi migrado mas sua proveniência documental não foi reconciliada
- **THEN** o corte do canal permanece bloqueado e o cursor legado continua disponível somente para leitura compatível

