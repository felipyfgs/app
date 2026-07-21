## MODIFIED Requirements

### Requirement: Coluna Pagamento na carteira PGDAS-D

A carteira Simples/MEI submódulo PGDASD SHALL exibir a coluna **Pagamento** na spine após RBT12 e antes de Cliente, com labels: `PAID`→Em dia, `UNPAID`→Pendências, `NO_DAS`→Sem DAS, `UNVERIFIED`→Não verificado. A coluna Situação MUST continuar refletindo só a entrega do PA.

A badge da coluna Pagamento MUST usar cor de sucesso (verde / token `success`) quando `payment_state` for `PAID`.

#### Scenario: Ordem da spine com Pagamento
- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem inclui Situação · Últ. Declaração · RBT12 · Pagamento · Cliente

#### Scenario: Badge Pendências de guia
- **WHEN** a linha tem `payment_state=UNPAID`
- **THEN** a badge Pagamento MUST exibir “Pendências”

#### Scenario: Badge Em dia verde
- **WHEN** a linha tem `payment_state=PAID`
- **THEN** a badge Pagamento MUST exibir “Em dia”
- **AND** MUST usar cor de sucesso (verde / `success`)
