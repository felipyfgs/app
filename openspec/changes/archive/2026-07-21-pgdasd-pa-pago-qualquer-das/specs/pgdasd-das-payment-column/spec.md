## MODIFIED Requirements

### Requirement: Estado de pagamento DAS do PA esperado

O sistema SHALL derivar um estado operacional de pagamento PGDAS-D (`PgdasdDasPaymentState`) exclusivamente a partir das operações locais kind DAS do período de apuração esperado do cliente, usando o campo `payment_located` (origem `dasPago` SERPRO / CONSDECLARACAO13). O estado MUST NOT alterar `PgdasdDeclarationState` nem a Situação de entrega.

`dasPago` informa pagamento **daquele número de DAS**, não o débito apurado na Situação Fiscal (SITFIS). Para o estado do PA, o sistema MUST interpretar quitação assim: se existir ao menos um DAS do PA com `payment_located=true`, o PA MUST ser `PAID` mesmo que outros DAS do mesmo PA permaneçam `payment_located=false` (guias reemitidas/substituídas).

Precedência MUST ser:

1. Sem evidência produtiva suficiente e nenhum DAS → `UNVERIFIED`
2. Pelo menos um DAS do PA com `payment_located=true` → `PAID`
3. Nenhum DAS com `true` e ao menos um com `payment_located=false` → `UNPAID`
4. DAS existem no PA mas todos com `payment_located` nulo → `UNVERIFIED`
5. Nenhum DAS no PA (com evidência produtiva) → `NO_DAS`

#### Scenario: DAS em aberto sem nenhum pago no PA esperado
- **WHEN** o cliente tem operação(ões) DAS no PA esperado com `payment_located=false`
- **AND** nenhum DAS do mesmo PA tem `payment_located=true`
- **THEN** o `payment_state` exposto no resumo PGDAS SHALL ser `UNPAID`

#### Scenario: PA com DAS pago e guias antigas unpaid
- **WHEN** o PA esperado tem ao menos um DAS com `payment_located=true`
- **AND** existem outros DAS do mesmo PA com `payment_located=false`
- **THEN** o `payment_state` SHALL ser `PAID`

#### Scenario: Todos os DAS do PA pagos
- **WHEN** o cliente tem um ou mais DAS no PA esperado e todos com `payment_located=true`
- **THEN** o `payment_state` SHALL ser `PAID`

#### Scenario: Declaração sem DAS
- **WHEN** o PA esperado não tem operações DAS locais
- **AND** há evidência produtiva suficiente
- **THEN** o `payment_state` SHALL ser `NO_DAS`
