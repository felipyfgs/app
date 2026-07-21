## MODIFIED Requirements

### Requirement: Popover Pagamento no nível do cliente

O popover da coluna **Pagamento** na carteira PGDAS-D SHALL apresentar o sinal operacional no nível do cliente, sem contagens de DAS e sem exibir reason codes máquina.

Quando `payment_state` for `PAID`, o popover MUST mostrar “Em dia” (ou equivalente) e MAY indicar confirmação via PAGTOWEB em texto humano curto.

Quando `payment_state` for `UNPAID`, o popover MUST listar as competências em aberto do cliente (ver requirement de competências) e MAY indicar que a pendência foi confirmada via PAGTOWEB.

Quando `payment_state` for `NO_DAS` ou `UNVERIFIED`, o popover MUST mostrar o label do estado e a descrição humana correspondente (sem lista de competências). `UNVERIFIED` MUST NOT ser apresentado como “Pendências”.

A badge da coluna Pagamento MUST continuar derivada do PA esperado (`PgdasdDasPaymentState`).

#### Scenario: Popover pago sem contagens
- **WHEN** a linha tem `payment_state=PAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir “Em dia” (ou equivalente humano) e MUST NOT exibir contagens de DAS nem reason code cru

#### Scenario: Popover unpaid com lista
- **WHEN** a linha tem `payment_state=UNPAID` e o operador abre o popover Pagamento
- **THEN** o popover MUST listar competências em aberto e MUST NOT exibir contagens de DAS nem reason code cru

#### Scenario: Popover não verificado
- **WHEN** a linha tem `payment_state=UNVERIFIED` e o operador abre o popover Pagamento
- **THEN** o popover MUST exibir “Não verificado” com descrição humana
- **AND** MUST NOT listar competências como se fossem pendências confirmadas

### Requirement: Competências em aberto no resumo PGDAS do portfolio

O resumo PGDAS da linha do portfolio (`detail.pgdasd`) SHALL incluir `payment_open_competencies`: lista de objetos `{ period_key, amount_cents }` para competências do histórico local em que o PA está em pendência **confirmada** por evidência PAGTOWEB local.

Um `period_key` MUST aparecer na lista somente quando:

1. nenhum DAS desse PA tem evidência PAGTOWEB `PAID`; e
2. todos os DAS desse PA têm evidência PAGTOWEB `NOT_FOUND` com verificação dentro do TTL.

Regras MUST ser:

1. Uma entrada por `period_key` distinto, ordenada por `period_key` descendente.
2. Resolução de `amount_cents` (ordem): soma fail-closed dos `pagtoweb_amount_cents` dos DAS `NOT_FOUND` do PA → `tax_guides` → `pgdasd_operations.amount_cents` → fallback vault GERAR_DAS quando aplicável; MUST NOT inventar valor.
3. Se valores mistos (alguns null) entre as fontes usadas no PA, `amount_cents` da competência MUST ser `null`.
4. A lista MAY estar vazia quando não houver PA com cobertura negativa fresca.

O cálculo MUST respeitar tenancy do office autenticado e MUST NOT disparar live SERPRO. MUST NOT usar SITFIS nem `dasPago` sozinho para incluir um PA na lista.

#### Scenario: PA pago no PAGTOWEB não entra na lista
- **WHEN** o cliente tem, no mesmo `period_key`, ao menos um DAS com evidência PAGTOWEB `PAID`
- **THEN** esse `period_key` MUST NOT aparecer em `payment_open_competencies`

#### Scenario: Lista só com cobertura negativa fresca
- **WHEN** um PA tem todos os DAS com `NOT_FOUND` PAGTOWEB dentro do TTL
- **AND** nenhum DAS do PA tem `PAID`
- **THEN** `payment_open_competencies` SHALL conter uma entrada para esse `period_key`

#### Scenario: Sem cobertura não lista pendência
- **WHEN** um PA tem DAS locais sem cobertura PAGTOWEB completa/fresca
- **THEN** esse `period_key` MUST NOT aparecer em `payment_open_competencies`

#### Scenario: Portfolio não chama SERPRO
- **WHEN** o portfolio monta `payment_open_competencies`
- **THEN** o sistema MUST NOT emitir HTTP/Integra Contador
- **AND** MUST ler apenas evidência local do office

#### Scenario: UI formata competência e valor
- **WHEN** o popover renderiza competências unpaid
- **THEN** cada linha MUST ser `MM/YYYY` à esquerda e valor à direita (moeda pt-BR quando houver `amount_cents`; "—" quando null)
