## MODIFIED Requirements

### Requirement: Popover Pagamento no nível do cliente

O popover da coluna **Situação** (pagamento DAS) na carteira PGDAS-D SHALL apresentar o sinal operacional no nível do cliente, sem contagens de DAS e sem exibir reason codes máquina (`DAS_PAYMENT_NOT_LOCATED`, `DAS_PAYMENT_LOCATED`, etc.).

Quando o pagamento estiver em dia, o popover MUST mostrar o estado “Em dia” e uma descrição humana curta.

Quando houver pendências de pagamento, o popover MUST listar as competências em aberto do cliente (ver requirement de competências).

Quando o estado for Sem DAS, o popover MUST mostrar o label do estado e a descrição humana correspondente (sem lista de competências).

Quando não houver evidência de pagamento (incluindo estado interno sem classificação), a célula MUST NOT abrir popover de negócio com rótulo “Não verificado”; MUST exibir Sem procuração ou `—` conforme a regra da coluna Situação.

A badge da Situação (pagamento) MUST continuar derivada só do PA esperado (`PgdasdDasPaymentState` inalterado nesta change).

#### Scenario: Popover pago sem contagens
- **WHEN** a linha tem pagamento em dia e o operador abre o popover Situação
- **THEN** o popover MUST exibir “Em dia” (ou equivalente humano) e MUST NOT exibir “DAS no PA”, “Com pagamento”, “Sem pagamento” nem reason code cru

#### Scenario: Popover unpaid com lista
- **WHEN** a linha tem pagamento em aberto e o operador abre o popover Situação
- **THEN** o popover MUST listar competências em aberto e MUST NOT exibir contagens de DAS nem reason code cru

#### Scenario: Popover sem DAS
- **WHEN** a linha está Sem DAS e o operador abre o popover Situação
- **THEN** o popover MUST exibir o estado “Sem DAS” com descrição humana e MUST NOT exigir lista de competências
