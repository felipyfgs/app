## Context

Hoje `/monitoring/simples-mei` hospeda duas cápsulas locais (`PGDASD` | `PGMEI`) via `SIMPLES_MEI_TABS`. A API já escopa por `tax_regime` (`applySimplesMeiSubmoduleScope`); o front envia `module=simples_mei` + `submodule`. Pós-create usa sessionStorage de cápsula porque a URL não distingue MEI. O usuário pediu página MEI independente, similar à carteira atual, para desacoplar a UX.

## Goals / Non-Goals

**Goals:**

- Rota própria `/monitoring/mei` com carteira PGMEI (KPIs, tabela, associate MEI, bulk/ações PGMEI).
- Item de nav “MEI” ao lado de “Simples Nacional” (rótulo sem “| MEI”).
- `/monitoring/simples-mei` só PGDAS-D (sem tabs de submodule).
- Pós-create MEI → `/monitoring/mei`; SN → `/monitoring/simples-mei`.
- Redirect legado de deep-links PGMEI sob `simples-mei` para a nova rota.

**Non-Goals:**

- Novo `FiscalModuleKey` / segmento API `mei`.
- Alterar scheduler, office monitor schedules (`simples_mei`), ou projeções backend.
- SERPRO live, flags ON, mei no Compose, redesign do shell.

## Decisions

### 1. Split só de superfície UI; API permanece `simples_mei`

- **Decisão:** página MEI chama `useFiscalModulePortfolio('simples_mei', { submodule: ref('PGMEI'), year })`. Simples fixa `PGDASD`.
- **Por quê:** escopo de regime e endpoints já existem; evita migration de module_key.
- **Alternativa rejeitada:** novo módulo API `mei` — escopo grande, fora do pedido.

### 2. Chave de nav `mei` (extra), não `FiscalModuleKey`

- **Decisão:** estender `MonitoringModuleKey` com `'mei'` (padrão `registrations` / `tax_processes`); `to: '/monitoring/mei'`.
- **Por quê:** `monitoringNavItemForModule('simples_mei')` permanece 1:1 com Simples.
- **Alternativa rejeitada:** dois itens com o mesmo `moduleKey` — quebra helpers de nav ativa.

### 3. Página MEI por extração, não fork cego

- **Decisão:** nova `pages/monitoring/mei/index.vue` com o ramo PGMEI atual (colunas `buildPgmeiColumns`, `MonitoringPgmeiBulkActions`, histórico/serviços públicos, ano corrente). Remover de `simples-mei` tabs, ramo PGMEI e preferência de cápsula.
- **Por quê:** reduz `v-if` dual; mantém comportamento PGMEI já testado.
- **Alternativa rejeitada:** manter uma página com query `?surface=mei` — não resolve o item de menu nem o Total confuso.

### 4. Remover sessionStorage de cápsula

- **Decisão:** `monitoringDestinationAfterClientCreate` retorna path direto; apagar (ou deixar no-op) `set/consumeSimplesMeiCapsulePreference`.
- **Por quê:** rota já distingue o destino.

### 5. Legacy path

- **Decisão:** middleware/redirect: `/monitoring/simples-mei/pgmei` (e slug variants) → `/monitoring/mei`; demais submodules → `/monitoring/simples-mei`.
- **Por quê:** deep-links e bookmarks antigos.

### 6. Labels de produto

- **Decisão:** nav/título Simples = “Simples Nacional”; MEI = “MEI”. Settings/scheduler que usam chave `simples_mei` podem manter label agregada nesta change (non-goal de split de agenda).
- **Por quê:** UI operacional clara sem mexer em monitores SERPRO.

## Risks / Trade-offs

- **[Dois itens de menu para o mesmo module_key API]** → Mitigação: documentar no código; helpers de nav usam chave UI `mei` vs `simples_mei`.
- **[Merge conflict com changes ativas em simples-mei]** → Mitigação: extrair MEI primeiro; slim SN depois; gates web locais.
- **[Bookmark / teste E2E apontando tabs]** → Mitigação: redirect legado + atualizar unit tests de navigation.
- **[Associate/membership ainda tipado em simples_mei]** → Mitigação: passar `submodule: 'PGMEI'` na página MEI; filtros de regime já cobrem.
- **[mei no Compose / SERPRO]** → Fora de escopo; não alterar flags.

## Migration Plan

1. Ship front: nav + página MEI + slim Simples + redirects + testes.
2. Rollback: reverter commit front; API intacta.
3. Follow-up opcional (outra change): split de module_key API / agenda `simples_mei`.

## Open Questions

- Nenhuma bloqueante. Opcional: ícone distinto para MEI no rail (default: reutilizar `badge-percent` ou `badge-check`).
