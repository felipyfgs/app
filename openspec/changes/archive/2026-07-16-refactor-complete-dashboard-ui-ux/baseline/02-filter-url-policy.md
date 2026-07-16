# 1.2 Política de filtros: URL vs estado local

**Registrado em:** 2026-07-15  
**Conflito analisado entre:** `standardize-dashboard-tables`, `complete-monitoring-visual-fixtures`, `add-operational-process-management`

## Decisões-fonte

| Change | Status | Decisão |
|--------|--------|---------|
| `standardize-dashboard-tables` | complete | Listas tipo Customers: `page`, `per_page`, `q`, `status`, ordenação e overlay em **estado local**; URL do navegador permanece no **path canônico** limpo (ex.: `/clients`). Query HTTP à API Laravel é independente da URL visível. |
| `complete-monitoring-visual-fixtures` | complete | Módulos de monitoramento: filtros e seção **reproduzíveis na URL** (competência, situação, page, submodule, client_id, triage, etc.). Destinos navegáveis em path; `office_id` **nunca** na URL. |
| `add-operational-process-management` | in-progress | Fila/calendário/processos: filtros e deep-links de risco/prazo **refletidos na URL** (tab, page, q, competence, department, status, risk). |

## Decisão vigente desta change (por família de rota)

Regra geral:

1. **Paths** = destinos e seções navegáveis (shell Settings, detalhe por id, módulos).
2. **Query na URL** = contexto operacional **compartilhável/deep-link** quando a capability da rota exige reproduzir filtro ao colar o link (monitoramento, trabalho, fechamento, mailbox).
3. **Estado local** = UI efêmera de tabela admin genérica no estilo template `customers.vue` (busca/página/ordenação/seleção/overlay) quando **não** há deep-link de produto.
4. **Sempre proibido** na URL: `office_id`, tokens, segredos, conteúdo fiscal bruto.
5. Query enviada à **API** pode conter paginação/filtros independentemente da URL visível.

### Por rota

| Família / rota | Política de filtros na URL do navegador | Justificativa |
|----------------|-------------------------------------------|---------------|
| `/clients` (lista) | **Local** (path limpo) | `standardize-dashboard-tables` + arquétipo Customers |
| `/clients/dashboard` | **Local** (ou path-only) | Visão KPI; sem deep-link de recorte de tabela |
| `/clients/:id/*` | **Path** para seção; sem estado tabular na query | Settings aninhado (template) |
| `/docs`, `/docs/catalog` | **Híbrido:** path canônico; filtros de catálogo **URL** se deep-link operacional já existir, senão local | Preferir URL se export/seleção for compartilhável |
| `/docs/:accessKey` | Path only | Detalhe canônico |
| `/docs/imports*` | Path + page local ou URL page se histórico paginado for deep-link | Preferir local salvo se lista simples |
| `/notes*`, `/docs/import-batches` | Redirect only | Sem estado |
| `/monitoring/**` | **URL** (filtros + page + submodule/tab) | Spec fixtures + deep-link de carteira |
| `/monitoring/mailbox*` | **URL** (triage + seleção via path do id) | Inbox com deep-link |
| `/work`, `/work/calendar`, `/work/processes*` | **URL** | Spec operational + deep-links de risco |
| `/work/templates` | **Local** (lista admin) + modal | Customers/AddModal |
| `/closing` | **URL** (competence, band, model, root) | Já implementado; deep-link de competência |
| `/exports`, `/syncs`, `/health` | **Local** (Customers) | Listas operacionais sem share de filtro no MVP |
| `/settings/**`, `/admin/**` | Path only / forms | Settings |
| Auth (`/login`, 2FA) | Query só para `redirect` sanitizado | Segurança |

## Como aplicar na refatoração

- Ao copiar `customers.vue`: manter estado local **somente** nas rotas marcadas Local.
- Ao copiar Home/Inbox de monitoramento/trabalho: sincronizar filtros com `route.query` + `router.replace`, descartando `office_id` e chaves desconhecidas.
- Deep-links da Home (departamento, risco, competência) apontam para paths/queries das famílias URL.
- Troca de escritório: limpar **ambos** (query e estado local) dos caches tenant-scoped.
