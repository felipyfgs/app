## Por quê

As grades de Situação Fiscal (SITFIS e Simples/MEI) estão visualmente espaçadas e, em notebooks (~1280px), cortam colunas à direita. Operadores perdem densidade e precisam de scroll horizontal desnecessário; o shell `ModuleDataTable` ainda não aplica o padrão compacto já usado em outras listas do painel.

## O que muda

- Adensar o shell compartilhado das carteiras de monitoramento (`ModuleDataTable`: padding e cabeçalho mais compactos).
- Fechar larguras e células do SITFIS (incluindo `CommercialMetaCell` e coluna Ações) para caber no viewport sem “buracos” de `table-fixed`.
- Reorganizar e apertar colunas do Simples/MEI (PGDAS-D / PGMEI): Cliente à esquerda, `min-w` menores, ações `xs`, colunas secundárias ocultas por padrão via Exibir.
- Manter scroll horizontal apenas como fallback em viewports estreitos; cards mobile inalterados em comportamento.
- **Não** alterar APIs fiscais, schema, SERPRO live, mutações fiscais nem o contrato de filtros (`list-filters-ux`).

## Capacidades

### Novas capacidades

- `monitoring-table-density`: contrato de densidade e organização das grades desktop de Situação Fiscal (shell monitoring + SITFIS + Simples/MEI) para caber no viewport sem corte de colunas úteis.

### Capacidades modificadas

- (nenhuma — main specs ainda sem capability de densidade de tabela de monitoramento.)

## Impacto

- **Frontend:** `ModuleDataTable.vue`, `CommercialMetaCell.vue`, `pages/monitoring/sitfis.vue`, `utils/pgdasd-table.ts`, `utils/pgmei-table.ts`, `pages/monitoring/simples-mei/index.vue`; testes unitários que leem source dessas grades.
- **Backend / API:** nenhum.
- **Fora de escopo (non-goals):** redesign de FGTS/DCTFWeb/guias/parcelamentos além do padding herdado do shell; sticky de coluna Cliente; alteração de arquétipo do template além da densidade; SERPRO live; parecer jurídico; mutações fiscais; outbound.

### Dependências entre changes

- **Nível:** C0
- **Bases estáveis:** main specs (`schema-conventions` e demais); template UI `@ 0f30c09`; shell monitoring já em produção.
- **Depende de:** nenhuma
- **Capability/contrato:** `monitoring-table-density` (nova)
- **Marco exigido:** n/a
- **Relação:** n/a
- **Desbloqueia:** ajustes futuros de densidade em outras carteiras monitoring
- **Paralelismo:** pode avançar em paralelo com changes de domínio SERPRO/monitoramento; **coordenação leve** com `integrar-monitoramento-pgdasd` e `integrar-monitoramento-pgmei` (evita editar lógica de domínio nos builders de coluna — só apresentação) e com `padronizar-filtros-listas` (não alterar toolbar/KPI; só `ModuleDataTable` / colunas)
