## Purpose

Capability `monitoring-guides-portfolio-consistency` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Lista unificada de guias sem materializar modelos office-wide

`GET /api/v1/fiscal/guides` (com ou sem `client_id`) SHALL continuar retornando a união deduplicada de `tax_guides`, DAS PGDAS-D e DARF DCTFWeb com `payment_counters` do universo filtrado. A implementação MUST NOT carregar o universo office-wide como coleções Eloquent completas com relações só para montar a página — MUST usar índice leve (chaves/sort/payment) e hidratar o shape público apenas para o slice paginado.

#### Scenario: Office-wide com DAS virtual

- **WHEN** o office tem operações DAS e zero `tax_guides` e a lista é pedida sem `client_id`
- **THEN** a resposta inclui linhas `source=PGDASD_CONSULT`, `total` correto e `payment_counters` coerentes

#### Scenario: Página hidratada

- **WHEN** a lista retorna uma página
- **THEN** cada item da página expõe o shape público completo (incl. `document` quando houver), sem exigir que todas as guias do office tenham sido hidratadas

### Requirement: Ordenação RBT12 alinhada ao valor exibido

Na carteira `simples_mei` submodule PGDASD, ordenar por `rbt12` SHALL usar o `total_cents` do mesmo PARSED que a linha exibe: preferir PARSED cujo `projection.period_key` é o período de display da declaração (declaração do PA esperado se existir; senão a última declaração do cliente; senão o PA esperado) e, na ausência, qualquer PARSED — MUST NOT ordenar apenas pelo maior `id` entre PARSED de períodos irrelevantes nem preferir o PA esperado quando o valor exibido vem de outro período de display.

#### Scenario: Sort segue período de display da declaração

- **WHEN** um cliente não tem declaração no PA esperado, tem declaração e PARSED em outro período, e também tem PARSED no PA esperado
- **THEN** a ordenação por `rbt12` usa o `total_cents` do PARSED do período de display (o da declaração), alinhado ao valor da linha

#### Scenario: Sort com PARSED no PA esperado quando é o display

- **WHEN** um cliente tem PARSED no PA esperado (período de display) e outro PARSED mais recente por `id` em período diferente
- **THEN** a ordenação por `rbt12` usa o `total_cents` do PARSED do PA esperado
