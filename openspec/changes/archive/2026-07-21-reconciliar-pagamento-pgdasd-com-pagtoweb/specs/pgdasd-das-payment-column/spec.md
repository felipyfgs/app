## MODIFIED Requirements

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

### Requirement: Coluna Pagamento na carteira PGDAS-D

A carteira Simples/MEI submódulo PGDASD SHALL exibir a coluna **Pagamento** na spine após RBT12 e antes de Cliente, com labels: `PAID`→Em dia, `UNPAID`→Pendências, `NO_DAS`→Sem DAS, `UNVERIFIED`→Não verificado. A coluna Situação MUST continuar refletindo só a entrega do PA.

#### Scenario: Ordem da spine com Pagamento
- **WHEN** o operador abre a carteira PGDAS-D
- **THEN** a ordem inclui Situação · Últ. Declaração · RBT12 · Pagamento · Cliente

#### Scenario: Badge Pendências só com evidência PAGTOWEB
- **WHEN** a linha tem `payment_state=UNPAID`
- **THEN** a badge Pagamento MUST exibir “Pendências”
- **AND** esse estado MUST ter sido derivado de cobertura PAGTOWEB negativa completa e fresca
