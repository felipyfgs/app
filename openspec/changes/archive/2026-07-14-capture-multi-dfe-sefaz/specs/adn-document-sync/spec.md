## ADDED Requirements

### Requirement: Coexistência ADN e SEFAZ
O sistema SHALL manter a captura ADN de NFS-e independente dos canais SEFAZ: falha ou bloqueio em DistDFe MUST NOT interromper cursors ADN do mesmo estabelecimento, e vice-versa.

#### Scenario: DistDFe bloqueado, ADN segue
- **WHEN** o cursor DistDFe está BLOCKED e o cursor ADN está IDLE com captura ligada
- **THEN** o scheduler continua elegendo o estabelecimento para jobs ADN
