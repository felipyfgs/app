## ADDED Requirements

### Requirement: Direction a partir do papel ADN
O sistema SHALL, ao projetar NFS-e, preencher direction: ISSUER→OUT, TAKER→IN, INTERMEDIARY→IN (ou política documentada), sem alterar o XML imutável.

#### Scenario: Backfill
- **WHEN** notas NFS-e legadas não têm direction
- **THEN** um comando ou migração deriva direction a partir de fiscal_role existente
