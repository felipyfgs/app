## MODIFIED Requirements

### Requirement: Ordenação RBT12 alinhada ao valor exibido

Na carteira `simples_mei` submodule PGDASD, ordenar por `rbt12` SHALL usar o `total_cents` do mesmo PARSED que a linha exibe: preferir PARSED cujo `projection.period_key` é o período de display da declaração (declaração do PA esperado se existir; senão a última declaração do cliente; senão o PA esperado) e, na ausência, qualquer PARSED — MUST NOT ordenar apenas pelo maior `id` entre PARSED de períodos irrelevantes nem preferir o PA esperado quando o valor exibido vem de outro período de display.

#### Scenario: Sort segue período de display da declaração

- **WHEN** um cliente não tem declaração no PA esperado, tem declaração e PARSED em outro período, e também tem PARSED no PA esperado
- **THEN** a ordenação por `rbt12` usa o `total_cents` do PARSED do período de display (o da declaração), alinhado ao valor da linha

#### Scenario: Sort com PARSED no PA esperado quando é o display

- **WHEN** um cliente tem PARSED no PA esperado (período de display) e outro PARSED mais recente por `id` em período diferente
- **THEN** a ordenação por `rbt12` usa o `total_cents` do PARSED do PA esperado
