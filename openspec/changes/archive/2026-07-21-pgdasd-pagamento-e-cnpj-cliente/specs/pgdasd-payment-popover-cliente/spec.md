## ADDED Requirements

### Requirement: Popover Pagamento no nível do cliente

O popover da coluna **Pagamento** na carteira PGDAS-D SHALL apresentar o sinal operacional no nível do cliente, sem contagens de DAS e sem exibir reason codes máquina (`DAS_PAYMENT_NOT_LOCATED`, `DAS_PAYMENT_LOCATED`, etc.).

Quando `payment_state` for `PAID`, o popover MUST mostrar o estado “Em dia” e uma descrição humana curta.

Quando `payment_state` for `UNPAID`, o popover MUST listar as competências em aberto do cliente (ver requirement de competências).

Quando `payment_state` for `NO_DAS` ou `UNVERIFIED`, o popover MUST mostrar o label do estado e a descrição humana correspondente (sem lista de competências).

A badge da coluna Pagamento MUST continuar derivada só do PA esperado (`PgdasdDasPaymentState` inalterado nesta change).

#### Scenario: Popover pago sem contagens
- **WHEN** a linha tem `payment_state=PAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir “Em dia” (ou equivalente humano) e MUST NOT exibir “DAS no PA”, “Com pagamento”, “Sem pagamento” nem reason code cru

#### Scenario: Popover unpaid com lista
- **WHEN** a linha tem `payment_state=UNPAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST listar competências em aberto e MUST NOT exibir contagens de DAS nem reason code cru

#### Scenario: Popover sem DAS
- **WHEN** a linha tem `payment_state=NO_DAS` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir o estado “Sem DAS” com descrição humana e MUST NOT exigir lista de competências

### Requirement: Competências em aberto no resumo PGDAS do portfolio

O resumo PGDAS da linha do portfolio (`detail.pgdasd`) SHALL incluir `payment_open_competencies`: lista de objetos `{ period_key, amount_cents }` agregados a partir das operações locais kind DAS do cliente com `payment_located=false` (histórico local conhecido do office, não só o PA esperado).

Regras MUST ser:

1. Uma entrada por `period_key` distinto, ordenada por `period_key` descendente.
2. `amount_cents` preenchido só quando houver valor em guia materializada (`tax_guides`) associável aos DAS da competência; caso contrário `null` ou omitido.
3. Se vários DAS unpaid no mesmo PA tiverem valores mistos (alguns null), `amount_cents` da competência MUST ser `null`.
4. A lista MAY estar vazia quando não houver DAS unpaid locais.

O cálculo MUST respeitar tenancy do office autenticado e MUST NOT disparar live SERPRO.

#### Scenario: Lista com competências unpaid do histórico
- **WHEN** o cliente tem DAS com `payment_located=false` em mais de um `period_key` local
- **THEN** `payment_open_competencies` SHALL conter uma entrada por competência unpaid, ordenada do mais recente para o mais antigo

#### Scenario: Valor opcional ausente
- **WHEN** há competência unpaid sem `amount_cents` em `tax_guides`
- **THEN** a entrada MUST incluir o `period_key` e MUST NÃO inventar valor monetário

#### Scenario: UI formata competência e valor
- **WHEN** o popover renderiza competências unpaid
- **THEN** cada linha MUST ser `MM/YYYY` à esquerda e valor à direita (moeda pt-BR quando houver `amount_cents`; "—" quando null)
- **AND** o popover MUST NOT usar a linha Situação|Pendências como se fosse cabeçalho de colunas acima da lista
