## Context

### Estado atual (levantamento 2026-07-14)

| Área | Situação |
|------|----------|
| Notas UI | `NotesWorkspace` + `NotesCatalog` em **inbox alargado** (40%/32%/55%); detalhe painel/slideover; filtros `q` + secundários; cursor 25 |
| Notas API | `NoteController@index` projeção sem XML; filtros `q`, cliente, est., papel, status, CNPJs, competência, datas |
| Clientes UI | `UTable` densa template `customers`; KPIs; chips A1/captura/sync; arquétipo a copiar |
| Export | `ExportController` + `BuildExportZipJob`; filtros escalares (uma `access_key`); **sem** `client_id`/`establishment_id`/`access_keys[]` |
| Specs | Main ainda descreve Notas como mestre–detalhe clássico; redesign anterior preferiu tabela mas entregou inbox |

### Restrições

- Template Nuxt UI Dashboard @ `0f30c09`; papéis ADMIN/OPERATOR/VIEWER; tenancy `office_id`.
- Multi-select só com ação real (spec proíbe cosmético).
- Export assíncrono auditado; sem vazar vault/XML em JSON.
- Volume piloto: 100+ notas — **não** carregar o catálogo inteiro no browser como Clientes.

## Goals / Non-Goals

**Goals:**

- Notas escaneáveis no mesmo “idioma visual” de Clientes (tabela densa).
- Tabs **Por documento** / **Por empresa** com drill-down.
- Filtrar → (opcional) selecionar → exportar ZIP reutilizando job existente.
- Paridade de filtros catálogo ↔ export.
- Manter deep-link, teclado e a11y do detalhe.

**Non-Goals:**

- Relatórios PDF/CSV gerenciais.
- Analytics / KPIs inventados.
- Select-all ilimitado sobre o escritório inteiro.
- Redesign completo de `/exports` além de filtros/seleção.
- DANFSe, emissão, portal do cliente.

## Decisions

### 1. Layout: tabela full-width + detalhe drawer/painel

**Decisão:** `UDashboardPanel` único (como Clientes) com `UTable` densa; detalhe em painel direito redimensionável **ou** `USlideover`/drawer em desktop se a tabela precisar de largura total; mobile sempre slideover. Rota `/notes/:accessKey` permanece.

**Por quê:** honra a preferência da redesign e unifica com Clientes; inbox fica legado.

**Alternativa rejeitada:** manter só inbox com tabs — não atende “mesma linha de listagem”.

**Colunas P0 (por documento):** checkbox | número | papel | contraparte (nome; CNPJ secundário) | competência | valor | status | ações.  
**P1 (hideable/desktop):** emissão, local, cliente do office se disponível.

### 2. Tabs de visualização (shell tipo Settings/Clientes)

**Decisão:** toolbar com `UNavigationMenu`:

| Tab | Conteúdo |
|-----|----------|
| Por documento | Tabela cursor de notas |
| Por empresa | Tabela agregada por **cliente do escritório** (não por CNPJ de contraparte solto) |

Query `view=document|client` na URL.

**Drill-down:** clicar empresa → `view=document` + `client_id=…` (filtros preservados).

### 3. Agregação “por empresa” no backend

**Decisão:** endpoint dedicado, ex. `GET /api/v1/notes/by-client` (ou `meta` + resource), retornando por cliente do office: `client_id`, `legal_name`/`display_name`, `cnpj`/`root_cnpj`, `notes_count`, opcional `service_amount_sum` se barato, respeitando os **mesmos filtros** aplicáveis (competência, status, datas, q se fizer sentido).

**Por quê:** agregar no client com cursor incompleto é incorreto.

**Alternativa rejeitada:** “por emitente/tomador” como default — confunde cliente do office com parte da nota; pode ser fase 2.

### 4. Paginação: cursor server-side na tabela

**Decisão:** manter cursor + “Carregar mais” (ou infinite) na tabela; **não** baixar todas as páginas no mount como Clientes.

**Por quê:** 100–1000+ notas; memória e latência.

### 5. Multi-select e export

**Decisão em duas ações (ambas reais):**

1. **Exportar filtro atual** — `POST /exports` com `filters` espelhando o catálogo (sem depender de checkboxes). Disponível mesmo com 0 selecionados.
2. **Exportar seleção** — checkboxes nas linhas **já carregadas**; `filters.access_keys: string[]` com teto **100** (configurável); rejeitar acima do teto com 422.

**Job `BuildExportZipJob`:** estender filtros:

| Filtro | Comportamento |
|--------|----------------|
| existentes | mantidos |
| `client_id` | whereHas interests.establishment |
| `establishment_id` | whereHas interests |
| `access_keys` | `whereIn('access_key', …)` se não vazio (prevalece sobre access_key escalar) |

Sem filtros e sem chaves = export de **todo o office** (comportamento atual) — UI deve **avisar** e preferir exigir confirmação ou desabilitar “exportar tudo” sem filtro (recomendado: exigir ao menos um filtro **ou** seleção, salvo ADMIN com confirmação explícita — documentar na implementação: **mínimo um critério** no atalho de Notas; página `/exports` pode manter comportamento legado com aviso).

**Permissão:** `canExport()` (ADMIN/OPERATOR); VIEWER só consulta.

### 6. “Relatórios”

**Decisão:** fora do MVP. UI pode rotular a ação como **Exportar XMLs** (não “Gerar relatório”) para não prometer PDF/planilha.

### 7. Filtros UI

**Decisão:** expor `issued_from` / `issued_to` no painel de filtros; manter `q` como busca principal; URL via `notes-filters`.

### 8. Fidelidade ao template

- Arquétipo lista: `.reference/.../customers.vue` (`:ui` table-fixed, toolbar, checkbox opcional).
- Tabs: padrão `settings.vue` / `clients.vue`.
- Divergências só por domínio (colunas fiscais, cursor em vez de client-side page).

## Risks / Trade-offs

| Risco | Mitigação |
|-------|-----------|
| Multi-select só no carregado vs “todos filtrados” | Duas ações distintas; texto claro na UI |
| Export all office pesado | Atalho Notas exige filtro ou seleção; job já é assíncrono |
| Agregação by-client N+1 | Group by / subquery única no office |
| Spec antiga “só mestre–detalhe” | Delta MODIFIED em frontend-dashboard-experience |
| Conflito com redesign não arquivada | Esta change assume código atual (inbox); supersede layout Notas |
| Seleção cosmetica | Checkbox só se export estiver habilitado para o papel |

## Migration Plan

1. Backend: filtros export + testes; endpoint by-client.
2. Frontend: shell tabs + UTable por documento + selection + atalho export.
3. Tab por empresa + drill-down.
4. E2e/unit; smoke com dados piloto.
5. Rollback = reverter frontend/API export; sem migration destrutiva.

## Open Questions

Nenhuma bloqueante. Se `service_amount_sum` na agregação for caro no primeiro PR, entregar só `notes_count` + identidade do cliente.
