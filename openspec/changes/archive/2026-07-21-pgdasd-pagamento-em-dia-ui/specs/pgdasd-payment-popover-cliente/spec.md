## MODIFIED Requirements

### Requirement: Popover Pagamento no nível do cliente

O popover da coluna **Pagamento** na carteira PGDAS-D SHALL apresentar o sinal operacional no nível do cliente, sem contagens de DAS e sem exibir reason codes máquina (`DAS_PAYMENT_NOT_LOCATED`, `DAS_PAYMENT_LOCATED`, etc.).

Quando `payment_state` for `PAID`, o popover MUST mostrar o estado “Em dia” e uma descrição humana curta (no espírito de “Pagamento localizado.”). O detalhe MUST NOT usar a frase longa “Pagamento do DAS do período esperado localizado até a consulta.” nem jargão técnico denso.

Quando `payment_state` for `UNPAID`, o popover MUST listar as competências em aberto do cliente (ver requirement de competências).

Quando `payment_state` for `NO_DAS` ou `UNVERIFIED`, o popover MUST mostrar o label do estado e a descrição humana correspondente (sem lista de competências).

A badge da coluna Pagamento MUST continuar derivada só do PA esperado (`PgdasdDasPaymentState` inalterado nesta change).

#### Scenario: Popover pago sem contagens
- **WHEN** a linha tem `payment_state=PAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir “Em dia” (ou equivalente humano) e MUST NOT exibir “DAS no PA”, “Com pagamento”, “Sem pagamento” nem reason code cru

#### Scenario: Popover pago com detalhe limpo
- **WHEN** a linha tem `payment_state=PAID` e o operador abre o popover Pagamento
- **THEN** o detalhe humano MUST ser curto (ex.: “Pagamento localizado.”)
- **AND** MUST NOT conter a frase “Pagamento do DAS do período esperado localizado até a consulta.”

#### Scenario: Popover unpaid com lista
- **WHEN** a linha tem `payment_state=UNPAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST listar competências em aberto e MUST NOT exibir contagens de DAS nem reason code cru

#### Scenario: Popover sem DAS
- **WHEN** a linha tem `payment_state=NO_DAS` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir o estado “Sem DAS” com descrição humana e MUST NOT exigir lista de competências
