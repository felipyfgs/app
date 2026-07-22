## Purpose

Capability `pgdasd-das-payment-column` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Estado de pagamento DAS do PA esperado

O sistema SHALL derivar um estado operacional de pagamento PGDAS-D (`PgdasdDasPaymentState`) exclusivamente a partir de evidência local do office autenticado para as operações kind DAS do período de apuração esperado. O estado MUST NOT alterar `PgdasdDeclarationState` nem a Situação de entrega. O cálculo MUST NOT disparar live SERPRO/Integra Contador.

A autoridade canônica para “este DAS foi pago?” MUST ser a evidência local de PAGTOWEB (`pagtoweb.pagamentos` / `PAGAMENTOS71`) associada por match exato do número do documento ao `das_number` (via digest/HMAC na projeção, sem exigir número em claro no read path). O campo `payment_located` (`dasPago` de CONSDECLARACAO13) MAY permanecer gravado para auditoria, mas MUST NOT decidir sozinho o `payment_state` nem a lista de competências em aberto.

Precedência MUST ser:

1. Nenhum DAS no PA esperado e há evidência produtiva de consulta PGDAS → `NO_DAS`
2. Ao menos um DAS do PA com evidência PAGTOWEB de pagamento localizado (`PAID`) → `PAID`
3. Cobertura PAGTOWEB completa de todos os DAS do PA, todos com resultado negativo (`NOT_FOUND`), e verificação ainda dentro do TTL configurado → `UNPAID`
4. Qualquer outro caso (sem cobertura, cobertura parcial, erro, ausência de procuração `00004`, TTL vencido, ou DAS sem verificação) → `UNVERIFIED`

#### Scenario: PA confirmado pago no PAGTOWEB
- **WHEN** o PA esperado tem ao menos um DAS com evidência PAGTOWEB local `PAID`
- **THEN** o `payment_state` SHALL ser `PAID`
- **AND** MUST NOT depender de `payment_located`/`dasPago` para essa decisão

#### Scenario: Pendência só com cobertura negativa fresca
- **WHEN** todos os DAS do PA esperado têm evidência PAGTOWEB `NOT_FOUND`
- **AND** a cobertura está completa e dentro do TTL
- **THEN** o `payment_state` SHALL ser `UNPAID`

#### Scenario: Sem cobertura PAGTOWEB
- **WHEN** o PA esperado tem DAS locais
- **AND** não há cobertura PAGTOWEB completa e fresca
- **AND** nenhum DAS tem evidência `PAID`
- **THEN** o `payment_state` SHALL ser `UNVERIFIED`
- **AND** a badge MUST NOT exibir “Pendências”

#### Scenario: Declaração sem DAS
- **WHEN** o PA esperado não tem operações DAS locais
- **AND** há evidência produtiva suficiente de consulta PGDAS
- **THEN** o `payment_state` SHALL ser `NO_DAS`

### Requirement: Coluna Situação exibe pagamento DAS com labels humanos

A carteira Simples/MEI submódulo PGDASD SHALL exibir o estado de pagamento DAS na coluna **Situação**, com labels humanos: Em dia, Pendências, Sem movimento. A UI MUST NOT exibir flags de máquina nem o rótulo “Não verificado”; quando não houver evidência de pagamento e a procuração estiver ausente, MUST exibir Sem procuração; nos demais casos sem evidência MUST exibir `—` (ou skeleton de consulta pendente). A entrega do PA MUST NOT aparecer como badge texto nesta coluna — mora na coluna Declaração colorida.

#### Scenario: NO_DAS exibe Sem movimento

- **WHEN** `payment_state` é `NO_DAS`
- **THEN** a coluna Situação MUST exibir o label “Sem movimento” (MUST NOT exibir “Sem DAS” como rótulo principal)

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
