## Context

O Monitoramento padronizou busca dedicada + chips (`data-table-filter` + `ModuleToolbar`) com estado aplicado separado do rascunho. O backend da carteira (`ModulePortfolioFilters`) aceita poucos eixos (`q`, `situation`, `competence`, `client_id`, `delivery_status`, sort). Guias, vínculos, processos e mailbox têm endpoints próprios. Documentos já têm catálogo rico; work/closing usam query string.

O usuário pediu, na mesma change: **salvar**, **compartilhar com a equipe se permitido**, **todas as colunas filtráveis** e **todas as tabelas de dados relacionadas**.

## Goals / Non-Goals

**Goals:**

- Presets nomeados **pessoais** e **compartilhados no Office**, com permissão explícita ao publicar.
- Aplicar/renomear/excluir com UX Nuxt UI na toolbar, sem quebrar a transação única de carga.
- Cada lista de dados declara `fields` alinhados às **colunas de negócio** filtráveis; a UI não oferece filtro que a API ignore.
- Estender contratos de listagem e o núcleo de chips (kinds/operadores mínimos) o necessário para cobrir essas colunas.
- Superfícies: 9 listas monitoring + mailbox + clients + docs + work (queue/processes) + closing, com adapter de payload por `surface`.
- Isolamento por Office; limpeza na troca de tenant; sem `office_id` do client.

**Non-Goals:**

- PLATFORM_ADMIN como consumidor de presets fiscais de Office.
- Faceting, filtros salvos “globais de plataforma”, URL sync no monitoring.
- Operadores complexos (AND/OR arbitrário, regex) além de `eq` / contains / range simples quando a coluna exigir.
- Mutações fiscais, flags ON, deps novas, live SERPRO.

## Decisions

### 1. Uma change, duas capabilities, implementação em camadas

Ordem de entrega **dentro** da change (tasks sequenciadas):

1. Persistência + API de presets (personal + office share)  
2. UI salvar/aplicar nas 9 listas com payload **atual**  
3. Expansão de colunas filtráveis + backend por módulo monitoring  
4. Rollout adapters para mailbox, clients, docs, work, closing  
5. Gates e archive  

Assim o produto fica num único change OpenSpec (pedido do usuário), sem misturar commits monólito no apply.

Alternativa rejeitada: três changes separadas — usuário pediu incluir tudo na mesma.

### 2. Modelo de dados de preset

Tabela `saved_list_filters` (nome final alinhado à convenção Laravel do repo):

| Coluna | Uso |
|--------|-----|
| `office_id` | tenant (preenchido só server-side) |
| `user_id` | autor |
| `surface` | string estável, ex. `monitoring.installments`, `docs.catalog`, `work.queue` |
| `name` | rótulo humano, único por (office, user, surface) em personal; em office único por (office, surface, name) |
| `visibility` | `personal` \| `office` |
| `schema_version` | int, default 1 |
| `payload` | JSONB: estado normalizado da superfície |
| timestamps | |

Índices: `(office_id, surface, user_id)`, `(office_id, surface, visibility)`.

**Listagem:** presets `personal` do user + `office` do Office atual.  
**Criar personal:** qualquer membership.  
**Publicar/alterar para `office`:** ADMIN ou OPERATOR (VIEWER só aplica compartilhados).  
**Editar/excluir:** autor **ou** ADMIN do Office (para presets `office`).  
**Nunca** `office_id` no body como autoridade.

Payload monitoring v1 (extensível):

```json
{
  "schema_version": 1,
  "q": "",
  "filters": [
    { "key": "situation", "operator": "eq", "value": "BLOCKED", "label": "Bloqueado" }
  ]
}
```

Chips + `q`; omitir defaults vazios. Rótulo de cliente opcional no chip para reexibir.

Alternativa rejeitada: reusar `exports.filters` — é job assíncrono, não view.

### 3. Superfícies e adapters

Cada superfície implementa:

- `surfaceId: string`  
- `toPayload(applied): json` / `fromPayload(json): applied`  
- `listFields(): DataTableFilterDefinition[]` (colunas filtráveis)  
- `apply(applied)` → carga existente  

| Surface | Adapter base |
|---------|----------------|
| `monitoring.*` (9) | `MonitoringFilterValue` + fields da página |
| `monitoring.mailbox` | triage + client (+ eixos que a API passar a aceitar) |
| `clients.index` | q + is_active + operational_filter |
| `docs.catalog` | `NotesFilterState` |
| `work.queue` | estado de `useWorkQueueFilters` |
| `work.processes` | query de processos |
| `closing.list` | filtros URL de closing |

Monitoring continua **sem** query de filtros na URL; work/docs podem manter URL se já usam, mas o preset é a fonte nomeada.

### 4. “Todas as colunas” = colunas de negócio filtráveis

- Colunas `actions`, select, menu **não** viram filtro.  
- Toda coluna de negócio listada na tabela **SHALL** ter definição de filtro **se e somente se** a API a aplicar; caso a coluna seja só projeção, a change **adiciona** o eixo no backend ou remove a pretensão de filtrá-la (não mentir na UI).  
- Inventário por módulo (diretriz de implementação):

| Módulo | Além do atual (q/situation/client/competence/…) | Backend |
|--------|--------------------------------------------------|---------|
| Portfolio comum | coverage, last_consulted (range ou “desde”), next_deadline se modelado | estender `ModulePortfolioFilters` + query |
| DCTFWeb | closure, transmission, payment (eixos no detail/read model) | subqueries/JSON paths se já indexáveis; senão materializar |
| FGTS | closure, totalization, guide/payment flags | idem |
| Parcelamentos | modality (já UI), overdue, order status | wire modality + campos |
| SITFIS | procuracao, coverage, findings (threshold) | eixos existentes no row |
| Declarações | applicability, obligation, open_count | hub/portfolio |
| Guias | emission, competence (se endpoint passar a aplicar), due window | guides list |
| Vínculos/Processos | client, source, texto process_number/link_key | já há client_id; adicionar q/source |
| Mailbox | subject q, triage, due, severity se existir | mailbox API |
| Clients | credential/procuracao se houver flag no list | ClientController |
| Docs | já rico — alinhar UI a chips reutilizáveis sem perder campos |
| Work | expor na UI os params que a API já aceita |

### 5. Expansão do núcleo de chips

Estender `DataTableFilterDefinition` com kinds mínimos:

- `option` (já)  
- `month` (já)  
- `client` (já)  
- `text` (contains/eq)  
- `boolean`  
- `date` ou `date_range` quando coluna for data  

Operador default `eq`; `contains` só em text; range só em date_range. Confirmação explícita e rascunho intactos.

### 6. UI Nuxt UI (sem deps novas)

- Salvar: `UModal` + `UForm` + `UInput` nome + `USwitch` “Compartilhar com o escritório”  
- Lista: `UDropdownMenu` grupos Meus / Equipe + item Gerenciar  
- Gerenciar: **`UModal`** com lista, `UBadge` Pessoal/Equipe, renomear/excluir/compartilhar  
- Chips: manter `UFieldGroup` + `outline` alinhado à toolbar  

### 7. Segurança e tenancy

- `EnsureOfficeContext` + membership real.  
- Payload não contém segredos/XML/PFX.  
- Ao aplicar preset `office` com `clientId` inexistente no Office: omitir chip de cliente e seguir demais eixos (ou toast de aviso).  
- Troca de Office: invalidar cache de lista de presets e rascunhos.

## Risks / Trade-offs

- [Escopo largo numa change] → tasks em camadas; cada camada com testes; não marcar done sem evidência.  
- [Coluna sem índice vira full scan] → só expor filtro com query razoável; preferir flags materializadas no read model.  
- [Preset `office` vaza recorte sensível] → só membership do Office; VIEWER não publica.  
- [Docs/work divergem do chip stack] → adapters; não forçar reescrita total de docs se o payload for o `NotesFilterState` serializado.  
- [Schema payload evolui] → `schema_version` + migrate soft (ignorar chaves desconhecidas).  
- [Emissões duplicadas ao aplicar] → um único `apply-filters` / path de carga.

## Migration Plan

1. Migration + API + testes de isolamento.  
2. UI presets nas 9 listas (payload atual).  
3. Expandir fields + backend por módulo.  
4. Adapters mailbox/clients/docs/work/closing.  
5. Gates frontend/backend + `openspec validate` + archive/commit.

Rollback: reverter migration (drop table) e UI; listagens voltam ao estado anterior sem dados críticos.

## Open Questions

Nenhuma bloqueante para design: share = Office inteiro (não lista de users); publicar = ADMIN|OPERATOR; colunas de ação excluídas. Detalhes de quais subcampos de “detail” materializar por módulo ficam nas tasks de implementação com teste de query.
