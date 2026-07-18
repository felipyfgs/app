## Contexto

As carteiras de Situação Fiscal renderizam desktop via
`MonitoringModuleTable` → `ModuleDataTable` → `UTable` (`table-fixed`).
Hoje o `:ui` do monitoring ainda usa padding generoso; SITFIS quase não
define larguras de coluna; Simples/MEI soma `min-w-*` altos com Cliente no
meio e `min-w-[1280px]`, cortando “Última Busca” em notebooks.

Já existe padrão mais apertado em `clients/index.vue` (`px-1 sm:px-4`) e
`COMPACT_DASHBOARD_TABLE_UI` em `table-ui.ts`, mas as grades fiscais não o
consomem.

Constraints: arquétipo dashboard `@ 0f30c09`; UI via panel-ui / ui-archetype;
sem mudança de API/tenancy; cards mobile (&lt; md) permanecem.

## Objetivos / Não objetivos

**Goals:**

- Densidade visual menor no shell monitoring (todas as carteiras herdam padding).
- SITFIS e Simples/MEI cabem em ~1280–1440px sem cortar coluna útil padrão.
- Cliente como âncora à esquerda no Simples/MEI; células multi-linha mais baixas.
- Scroll horizontal só como fallback; Exibir continua controlando colunas.

**Non-Goals:**

- Sticky de coluna Cliente; redesign de FGTS/DCTFWeb/guias além do shell.
- Alterar toolbar/KPI/filtros (`list-filters-ux` / `padronizar-filtros-listas`).
- Mudar contratos de domínio PGDAS-D/PGMEI/SITFIS ou habilitar SERPRO live.
- Inventar densidade fora do template (sem design system paralelo).

## Decisões

### 1. Densidade no shell, larguras nas páginas

Apertar `TABLE_UI` em `ModuleDataTable.vue` (th sem `py-2`; `th`/`td` com
`px-2 sm:px-3`). Larguras e ordem de colunas ficam nas páginas/builders
(SITFIS, `pgdasd-table`, `pgmei-table`).

Alternativa rejeitada: só baixar `min-w` da página — não corrige buracos de
`table-fixed` nem altura de `CommercialMetaCell`.

### 2. SITFIS — larguras explícitas + ações enxutas

Definir `meta.class` por coluna; reduzir Ações (`w-56` → ~`w-28`/`w-32`)
removendo o botão texto “Cliente” (redundante com a 1ª coluna). Achados em
texto curto (`text-xs`). `CommercialMetaCell` em até duas linhas densas
(labels + valores; “recente” via tooltip se necessário).

### 3. Simples/MEI — Cliente primeiro + Exibir para secundárias

Reordenar builders: após checkbox de seleção, `client` primeiro. Reduzir
`min-w-*` (cliente `min-w-48`); ações/rastreio/histórico em `size: 'xs'`
sem botão `block` full-width. `initialHiddenColumns`: `consulted` e
`history`. Baixar `table-class` de `min-w-[1280px]` para o mínimo real
(~1100px).

Alternativa rejeitada: sticky Cliente no meio — mais frágil com scroll e
fora do padrão SITFIS.

### 4. Coordenação com changes ativas (sem bloquear)

Ownership desta change: apresentação em
`ModuleDataTable`, `CommercialMetaCell`, `sitfis.vue`, builders de coluna
PGDAS/PGMEI e `simples-mei/index.vue`. Não editar services/adapters nem
toolbar de filtros. Changes `integrar-monitoramento-pgdasd` /
`integrar-monitoramento-pgmei` e `padronizar-filtros-listas` seguem em
paralelo com gate coordenado (evitar merge conflitante nos mesmos
builders).

## Riscos / Trade-offs

- [Colunas ocultas por padrão] → Mitigação: Exibir restaura; labels
  documentados em `columnLabels`.
- [Conflito de merge em `pgdasd-table.ts`] → Mitigação: diffs só de UI
  (ordem/`meta`/`size`); rebase cedo se a change de domínio avançar.
- [Overflow residual em &lt;1100px] → Mitigação: manter
  `horizontalScroll` + `min-w` mínimo; cards no mobile.

## Plano de migração

- Deploy só frontend estático; sem migration DB.
- Rollback: reverter commit/UI; sem estado persistido além de preferência
  local de Exibir (já existente).

## Questões em aberto

- Nenhuma bloqueante; calibração fina de `w-*`/`min-w-*` no browser durante
  o apply.

## Mapa de dependências

```text
C0 compactar-tabelas-situacao-fiscal (monitoring-table-density)
  │
  ├─ coordenada (não bloqueante) → integrar-monitoramento-pgdasd
  ├─ coordenada (não bloqueante) → integrar-monitoramento-pgmei
  └─ coordenada (não bloqueante) → padronizar-filtros-listas
```

- **Ownership:** densidade/apresentação das grades SITFIS e Simples/MEI.
- **Marcos:** `specs` desta change basta para apply; não espera archive das
  coordenados.
- **Rollout:** apply frontend → verify visual + unit → archive.
- **Rollback:** revert do frontend.
