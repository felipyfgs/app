## ADDED Requirements

### Requirement: Estado de pagamento DAS do PA esperado

O sistema SHALL derivar um estado operacional de pagamento PGDAS-D (`PgdasdDasPaymentState`) exclusivamente a partir das operações locais kind DAS do período de apuração esperado do cliente, usando o campo `payment_located` (origem `dasPago` SERPRO). O estado MUST NOT alterar `PgdasdDeclarationState` nem a Situação de entrega.

Precedência MUST ser:

1. Sem evidência produtiva suficiente → `UNVERIFIED`
2. Qualquer DAS do PA com `payment_located=false` → `UNPAID`
3. Pelo menos um DAS com `payment_located=true` e nenhum `false` → `PAID`
4. DAS existem no PA mas todos com `payment_located` nulo → `UNVERIFIED`
5. Nenhum DAS no PA → `NO_DAS`

#### Scenario: DAS em aberto no PA esperado
- **WHEN** o cliente tem operação DAS no PA esperado com `payment_located=false`
- **THEN** o `payment_state` exposto no resumo PGDAS SHALL ser `UNPAID`

#### Scenario: Todos os DAS do PA pagos
- **WHEN** o cliente tem um ou mais DAS no PA esperado, ao menos um com `payment_located=true`, e nenhum com `false`
- **THEN** o `payment_state` SHALL ser `PAID`

#### Scenario: Declaração sem DAS
- **WHEN** o PA esperado não tem operações DAS locais
- **THEN** o `payment_state` SHALL ser `NO_DAS`

### Requirement: Coluna Pagamento na carteira PGDAS-D

A carteira Simples/MEI submódulo PGDASD SHALL exibir a coluna **Pagamento** na spine após RBT12 e antes de Cliente, com labels: `PAID`→Em dia, `UNPAID`→Pendências, `NO_DAS`→Sem DAS, `UNVERIFIED`→Não verificado. A coluna Situação MUST continuar refletindo só a entrega do PA.

#### Scenario: Ordem da spine com Pagamento
- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem inclui Situação · Últ. Declaração · RBT12 · Pagamento · Cliente

#### Scenario: Badge Pendências de guia
- **WHEN** a linha tem `payment_state=UNPAID`
- **THEN** a badge Pagamento MUST exibir “Pendências”
