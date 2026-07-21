## MODIFIED Requirements

### Requirement: Estado de pagamento DAS do PA esperado

O sistema SHALL derivar um estado operacional de pagamento PGDAS-D (`PgdasdDasPaymentState`) exclusivamente a partir das operações locais kind DAS do período de apuração esperado do cliente, usando o campo `payment_located` (origem `dasPago` SERPRO). O estado MUST NOT alterar `PgdasdDeclarationState` nem a classificação de entrega do PA.

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

A carteira Simples/MEI submódulo PGDASD SHALL exibir o estado de pagamento DAS na coluna **Situação** (não em coluna Pagamento separada), com labels humanos: Em dia, Pendências, Sem DAS. A UI MUST NOT exibir flags de máquina nem o rótulo “Não verificado”; quando não houver evidência de pagamento e a procuração estiver ausente, MUST exibir Sem procuração; nos demais casos sem evidência MUST exibir `—` (ou skeleton de consulta pendente). A entrega do PA MUST NOT aparecer como badge texto nesta coluna — mora na coluna Declaração colorida.

A ordem da spine PGDAS-D MUST ser: Situação · Declaração · RBT12 · Cliente · Ações · Comunicação · Consulta (sem coluna Pagamento).

#### Scenario: Ordem da spine sem Pagamento
- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem inclui Situação · Declaração · RBT12 · Cliente
- **AND** não existe coluna Pagamento na grade

#### Scenario: Badge Pendências na Situação
- **WHEN** a linha tem pagamento em aberto no PA esperado e procuração não está ausente
- **THEN** a badge Situação MUST exibir “Pendências”

#### Scenario: Sem evidência não mostra Não verificado
- **WHEN** o estado interno de pagamento não tem evidência suficiente e a procuração não está ausente
- **THEN** a célula Situação MUST NOT exibir “Não verificado”
- **AND** MUST exibir `—` ou indicação de consulta pendente
