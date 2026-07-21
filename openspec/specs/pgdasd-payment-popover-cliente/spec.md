## Purpose

Capability `pgdasd-payment-popover-cliente` — popover Pagamento no nível do cliente e competências em aberto no portfolio PGDAS-D.

## Requirements

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

### Requirement: Competências em aberto no detalhe do cliente

O resumo PGDAS da linha do portfolio (`detail.pgdasd`) SHALL incluir `payment_open_competencies`: lista de objetos `{ period_key, amount_cents }` para competências do histórico local do office em que o PA ainda não está quitado segundo a evidência PAGTOWEB local (nenhum DAS do PA com `pagtoweb_payment_status=PAID`, e cobertura negativa completa/fresca de todos os DAS do PA).

Regras de montagem:

1. Incluir só `period_key` cujo PA não está quitado (sem evidência PAGTOWEB `PAID` e com cobertura `NOT_FOUND` completa dentro do TTL).
2. Dentro do PA não quitado, a resolução de valor por DAS unpaid MUST usar a ordem local já estabelecida (`pagtoweb_amount_cents` → `tax_guides` → `pgdasd_operations.amount_cents` → fallback vault GERAR_DAS quando aplicável); MUST NOT inventar valor.
3. Se vários DAS unpaid do PA tiverem valores mistos (alguns null), `amount_cents` da competência MUST ser `null`.
4. Quando **todos** os DAS unpaid do PA tiverem `amount_cents` resolvido, o montante da competência MUST ser o **máximo** desses valores (débito representativo do PA). MUST NOT somar reemissões do mesmo PA (N guias com o mesmo ou maior facial).

A UI do popover Pendências MUST destacar valores monetários como indicativo de débito (cor semântica de erro / vermelho). Valores ausentes (`—`) MUST permanecer neutros.

#### Scenario: PA com cinco reemissões do mesmo facial
- **WHEN** o PA `2026-06` tem cinco DAS unpaid com `amount_cents=14125` cada e cobertura PAGTOWEB negativa completa
- **THEN** `payment_open_competencies` MUST incluir `{ period_key: "2026-06", amount_cents: 14125 }`
- **AND** MUST NOT expor `70625` (soma 5×)

#### Scenario: PA unpaid com valor parcial ausente
- **WHEN** o PA tem ao menos um DAS unpaid sem valor resolvido e outros com valor
- **THEN** `amount_cents` da competência MUST ser `null`

#### Scenario: Popover destaca débito em vermelho
- **WHEN** o operador abre o popover Pendências e a linha tem `amount_cents` numérico
- **THEN** o valor formatado MUST usar estilo de débito (token de erro / vermelho semântico)
- **AND** linhas com `—` MUST NOT usar o mesmo destaque de débito
