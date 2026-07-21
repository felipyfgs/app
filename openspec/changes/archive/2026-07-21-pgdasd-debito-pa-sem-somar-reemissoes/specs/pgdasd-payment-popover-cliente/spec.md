## MODIFIED Requirements

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
