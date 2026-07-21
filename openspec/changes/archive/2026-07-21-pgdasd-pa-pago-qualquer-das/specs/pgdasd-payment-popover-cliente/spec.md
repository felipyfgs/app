## MODIFIED Requirements

### Requirement: Competências em aberto no resumo PGDAS do portfolio

O resumo PGDAS da linha do portfolio (`detail.pgdasd`) SHALL incluir `payment_open_competencies`: lista de objetos `{ period_key, amount_cents }` para competências do histórico local do office em que o PA ainda não está quitado segundo `dasPago`/`payment_located`.

Um `period_key` MUST ser considerado quitado (e MUST NOT aparecer na lista) quando existir ao menos uma operação DAS local desse PA com `payment_located=true`, mesmo que outras operações DAS do mesmo PA tenham `payment_located=false`.

Regras MUST ser:

1. Uma entrada por `period_key` distinto ainda não quitado, ordenada por `period_key` descendente.
2. Dentro do PA não quitado, a resolução de `amount_cents` MUST usar só DAS com `payment_located=false` (ordem local já estabelecida: `tax_guides` → `pgdasd_operations.amount_cents` → fallback vault GERAR_DAS quando aplicável); MUST NOT inventar valor.
3. Se vários DAS unpaid do PA tiverem valores mistos (alguns null), `amount_cents` da competência MUST ser `null`.
4. A lista MAY estar vazia quando não houver PA unpaid local (inclui o caso em que só restam guias `false` em PAs que já têm DAS pago).

O cálculo MUST respeitar tenancy do office autenticado e MUST NOT disparar live SERPRO. MUST NOT substituir SITFIS como fonte de “débitos apurados”.

#### Scenario: PA com DAS pago não entra na lista
- **WHEN** o cliente tem, no mesmo `period_key`, um DAS com `payment_located=true` e outros com `payment_located=false`
- **THEN** esse `period_key` MUST NOT aparecer em `payment_open_competencies`

#### Scenario: Lista só com PAs sem nenhum DAS pago
- **WHEN** o cliente tem DAS com `payment_located=false` em um PA e nenhum DAS desse PA com `payment_located=true`
- **THEN** `payment_open_competencies` SHALL conter uma entrada para esse `period_key`

#### Scenario: Valor opcional ausente
- **WHEN** há competência unpaid (PA sem DAS pago) sem `amount_cents` resolvível localmente
- **THEN** a entrada MUST incluir o `period_key` e MUST NÃO inventar valor monetário

#### Scenario: UI formata competência e valor
- **WHEN** o popover renderiza competências unpaid
- **THEN** cada linha MUST ser `MM/YYYY` à esquerda e valor à direita (moeda pt-BR quando houver `amount_cents`; "—" quando null)
- **AND** o popover MUST NOT usar a linha Situação|Pendências como se fosse cabeçalho de colunas acima da lista
