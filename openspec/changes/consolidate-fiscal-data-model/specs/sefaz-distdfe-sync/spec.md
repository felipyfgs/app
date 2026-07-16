## ADDED Requirements

### Requirement: Autoridade consolidada do cursor DistDFe
O sistema MUST manter uma única autoridade de progresso DistDFe por escritório, Estabelecimento, ambiente e canal, sem compartilhar progresso com ADN, autor do pedido ou sequenciamento outbound.

#### Scenario: Migração de cursor DistDFe
- **WHEN** o cursor legado é convertido para o modelo canônico
- **THEN** último NSU, `maxNSU` observado, estado, backoff, falhas e bloqueio são preservados e reconciliados antes do corte

#### Scenario: Cursor duplicado
- **WHEN** o inventário encontra dois cursores DistDFe para a mesma identidade canônica
- **THEN** o sistema não escolhe o maior NSU automaticamente, bloqueia o corte e exige comprovação pela persistência dos lotes

### Requirement: Persistência canônica sem alterar a semântica NSU
A consolidação documental MUST manter a confirmação atômica de lote, aquisições e cursor e MUST NOT avançar NSU por documento apenas identificado, divergente ou não decodificado.

#### Scenario: Reprocessamento após documento existente
- **WHEN** um lote repetido contém documento canônico já armazenado
- **THEN** a aquisição DistDFe e sua identidade de NSU são reconciliadas antes do avanço idempotente

#### Scenario: Hash divergente no lote
- **WHEN** um item usa identidade oficial existente com bytes divergentes
- **THEN** ambos os artefatos são preservados, o cursor não avança e uma revisão operacional é aberta

