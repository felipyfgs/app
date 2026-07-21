## ADDED Requirements

### Requirement: Lista unificada de guias sem materializar modelos office-wide

`GET /api/v1/fiscal/guides` (com ou sem `client_id`) SHALL continuar retornando a união deduplicada de `tax_guides`, DAS PGDAS-D e DARF DCTFWeb com `payment_counters` do universo filtrado. A implementação MUST NOT carregar o universo office-wide como coleções Eloquent completas com relações só para montar a página — MUST usar índice leve (chaves/sort/payment) e hidratar o shape público apenas para o slice paginado.

#### Scenario: Office-wide com DAS virtual

- **WHEN** o office tem operações DAS e zero `tax_guides` e a lista é pedida sem `client_id`
- **THEN** a resposta inclui linhas `source=PGDASD_CONSULT`, `total` correto e `payment_counters` coerentes

#### Scenario: Página hidratada

- **WHEN** a lista retorna uma página
- **THEN** cada item da página expõe o shape público completo (incl. `document` quando houver), sem exigir que todas as guias do office tenham sido hidratadas

### Requirement: Ordenação RBT12 alinhada ao valor exibido

Na carteira `simples_mei` submodule PGDASD, ordenar por `rbt12` SHALL preferir o `total_cents` do PARSED cujo `projection.period_key` é o PA esperado da carteira e, na ausência, o de qualquer PARSED — MUST NOT ordenar apenas pelo maior `id` entre PARSED de períodos irrelevantes.

#### Scenario: Sort segue PA esperado

- **WHEN** um cliente tem PARSED no PA esperado e outro PARSED mais recente por `id` em período diferente
- **THEN** a ordenação por `rbt12` usa o `total_cents` do PARSED do PA esperado
