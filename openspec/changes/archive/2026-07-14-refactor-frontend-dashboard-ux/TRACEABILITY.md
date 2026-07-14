# Rastreabilidade — refactor-frontend-dashboard-ux

Documento de apoio às tarefas 1.1–1.4. Não substitui `design.md` nem as specs.

## 1.1 Matriz de rotas

| Rota | Padrão | Ação primária | Filtros/contexto | Detalhe | Perfis | Viewports |
|------|--------|---------------|------------------|---------|--------|-----------|
| `/` | Dashboard analítico | Novo cliente (`ADMIN`/`OPERATOR` com mutação) | Sem período (sem série temporal) | Alertas → módulo | Todos autenticados | 360 / 390×844 / 1440×900 |
| `/clients` | Lista administrativa | Novo cliente | Busca no corpo; `q`/`page` na URL | Página `/clients/:id` | Todos (criar: mutação) | idem |
| `/clients/:id` | Settings/seções | Depende da seção | Toolbar: Resumo · Estabelecimentos · A1 · Sincronização; `section` na URL | Página dedicada | A1: `ADMIN`+2FA; sync: mutação | idem |
| `/notes` | Mestre–detalhe | — (navbar: atualizar) | Filtros no painel mestre; query na URL | Painel adjacente / slideover | Todos | idem |
| `/notes/:accessKey` | Mestre–detalhe (seleção) | Baixar XML (auditado) | Filtros do catálogo preservados no retorno | Canônica da seleção | Todos (escopo office) | idem |
| `/exports` | Lista administrativa | Nova exportação | Escopo no modal | Estado na linha | Criar: mutação | idem |
| `/syncs` | Lista administrativa | Atualizar | Cursor | Slideover | Todos | idem |
| `/admin` | Settings | — | Sem toolbar (uma seção real no MVP) | Conteúdo central restrito | `ADMIN`+2FA | idem |

### Hierarquia de ações

1. Navbar: título, collapse, no máx. 1 primária textual no desktop.
2. Compactas: ghost + tooltip + `aria-label`.
3. Toolbar: subnavegação ou filtros de painel inteiro.
4. Faixa utilitária no corpo: busca/filtros de tabela.
5. Ações por registro: fim da linha / menu.
6. Destrutivas: modal com alvo, consequência, confirmar `error`.

## 1.2 Mapa para `build-nfse-adn-capture-system` (9.2–9.10)

| Tarefa pai | Tarefas deste change | Critério de conclusão compartilhada |
|------------|----------------------|-------------------------------------|
| 9.2 Login/2FA e navegação por perfil | 2.1, 2.2, 2.4, 7.2, 8.1, 9.2–9.3 | Shell + palette + atalhos por papel; admin sem 2FA não renderiza conteúdo |
| 9.3 Dashboard métricas/falhas/certs | 3.1–3.5 | Indicadores reais, alertas, horário; sem gráfico artificial |
| 9.4 Fluxo cliente → estab → A1 → sync | 4.3–4.8 | Seções Settings + componentes extraídos + permissões |
| 9.5 Tabelas server-side / cursor | 2.6, 4.1, 5.2–5.3, 6.1, 6.5 | Preset `UTable`; clientes offset; notas/syncs cursor |
| 9.6 Detalhe nota + filtros + download | 5.1–5.9 | Mestre–detalhe; URL canônica; sem XML bruto |
| 9.7 Exportações | 6.1–6.4, 6.8 | Form tipado, polling condicional, expirado sem download |
| 9.8 Histórico/alertas/estados | 2.3, 6.6–6.7, 8.6 | Alertas vazio≠erro; slideover sync; sem material sensível |
| 9.9 (já [x]) Composables tipados | — | Preservar; não reintroduzir mocks |
| 9.10 Testes componentes + Playwright | 8.5–8.6, 9.1–9.4, 9.7 | Só marcar pai após evidência real |

**Regra:** não marcar 9.2–9.10 no change pai até 9.7 deste change reconciliar evidências. Este change pode completar UX; o pai só fecha com prova.

### 9.7 Reconciliação de evidências (apply)

| Tarefa pai | Evidência neste change | Marcar no pai? |
|------------|------------------------|----------------|
| 9.2 Navegação por perfil | `utils/navigation.ts` + `tests/unit/navigation.test.ts` + `permissions.test.ts` | Parcial — falta e2e autenticado multi-perfil |
| 9.3 Dashboard | `pages/index.vue` + `dashboard-metrics.test.ts` | Parcial — UI pronta; e2e autenticado pendente |
| 9.4 Fluxo cliente guiado | componentes em `components/clients/*` + seções URL | Parcial — implementação ok; e2e autenticado pendente |
| 9.5 Tabelas server-side | preset + listas; unit notes-filters | Parcial |
| 9.6 Detalhe nota mestre–detalhe | `NotesWorkspace` + `NotesDetail` | Parcial |
| 9.7 Exportações | `pages/exports/index.vue` | Parcial |
| 9.8 Histórico/alertas | `NotificationsSlideover` + sync slideover | Parcial |
| 9.9 Composables tipados | mantido | Já [x] |
| 9.10 Playwright desktop/mobile | smoke e2e 1440×900 e 390×844 (login/redirect/360px); unit 20 testes | **Não** marcar pai ainda — falta cobertura autenticada dos fluxos 9.2–9.8 |

**Conclusão:** este change completa a refatoração UX e a base de testes. As tarefas 9.2–9.8 e 9.10 do change pai permanecem abertas até existir suíte autenticada end-to-end.

## 1.3 Cursor de Notas na URL

**Contrato API** (`NoteController@index`):

- `cursor` é o `id` (inteiro serializado em string) do último item da página anterior.
- A consulta aplica `where('id', '<', (int) $cursor)` com `orderByDesc('id')`.
- `meta.next_cursor` = id do último item da página atual, se houver mais.

**Decisão:** o cursor **pode** ser serializado e retomado com segurança **desde que** os mesmos filtros estejam ativos (a ordenação é estável por `id` decrescente).

**URL de Notas:**

| Parâmetro | Persistido? | Notas |
|-----------|-------------|-------|
| Filtros (`access_key`, `client_id`, `establishment_id`, `issuer_cnpj`, `taker_cnpj`, `fiscal_role`, `competence`, `issued_from`, `issued_to`, `status`) | Sim | Sem valores vazios |
| `cursor` | Sim, opcional | Permite “carregar mais” restaurável após reload; ao **mudar** qualquer filtro, zera `cursor` e reinicia lista |
| `accessKey` (path) | Sim | Seleção canônica `/notes/:accessKey` |

Retorno do detalhe: `/notes` com query de filtros (+ `cursor` se ainda válido). Não converter cursor em paginação offset.

## 1.4 Inventário de artefatos atuais

### Preservar (adaptar)

| Artefato | Destino |
|----------|---------|
| `layouts/default.vue` | Shell; fechar mobile; permissões |
| `composables/useApi.ts` | Contratos tipados |
| `composables/useDashboard.ts` | Permissões + shortcuts + slideover |
| `utils/permissions.ts` | Fonte única de papéis |
| `utils/api-error.ts`, `utils/format.ts` | Mensagens e labels |
| `components/AppStatusBadge.vue` | Estados com texto+cor |
| `components/UserMenu.vue` | Tema claro/escuro; limitar paleta |
| `middleware/auth.global.ts` | Auth + admin gate |
| Páginas de domínio | Refatorar in-place / extrair componentes |
| Public Sans + tokens em `main.css` / `app.config.ts` | Manter |

### Substituir / extrair

| Artefato | Mudança |
|----------|---------|
| `components/TeamsMenu.vue` | Identidade não interativa do escritório (`OfficeIdentity`) |
| `components/NotificationsSlideover.vue` | Estados loading/lista/vazio/erro + retry |
| `pages/index.vue` | `UPageGrid` + ordem de severidade + stale-on-error |
| `pages/clients/index.vue` | Faixa utilitária no corpo; `UForm`+Zod; preset tabela |
| `pages/clients/[id].vue` | Seções Settings + componentes |
| `pages/notes/index.vue` + `[accessKey].vue` | Mestre–detalhe unificado |
| `pages/exports/index.vue` | Preset tabela; form tipado; expirado |
| `pages/syncs/index.vue` | Preset; slideover; bloqueio sem NSU jump |
| `pages/admin/index.vue` | Settings central; gate papel/2FA antes do conteúdo |

### Remover (após 9.5, se sem consumidores)

| Candidato | Condição |
|-----------|----------|
| `TeamsMenu.vue` | Após `OfficeIdentity` |
| Estilos ad hoc de tabela nas páginas | Após preset global |
| Seletores de paleta livres no UserMenu | Limitados ou removidos (2.5) |
| Detalhe de nota só em página full sem painel | Após mestre–detalhe |

### Testes atuais

Nenhum teste de componente ou Playwright no `frontend/` ainda. Seção 9 cria a suíte; evidências alimentam 9.10 do change pai.
