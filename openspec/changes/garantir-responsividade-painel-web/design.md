## Context

O painel (`apps/web`) é SPA Nuxt 4 + Nuxt UI Dashboard. Shell autenticado (`layouts/default.vue`), auth (`layouts/auth.vue`), kit `Shell*` (`shell-ui-kit` / `panel-shell-kit`) e padrão ouro de listas (`alinhar-listas-padrao-clients` / `listas-padrao-clients`) já existem. Mobile cards existem em `ModuleDataTable` + `ModuleMobileCards` e em `/clients` (`ClientCatalogList`); a maior parte das demais listas e workspaces ainda é desktop-first.

**Justificativa da exceção transversal (2 capabilities):** o contrato de experiência mobile (`painel-responsivo-mobile`) altera requisitos do kit de lista (`panel-shell-kit` — `ShellDataTable`/footer). Separar em duas changes criaria ownership concorrente do mesmo componente; portanto esta change declara NEW + MODIFIED no limite de 2.

Inventário: **85** pages `.vue` em `apps/web/app/pages`; ~138 componentes em `apps/web/app/components`. Template de referência `@ 0f30c09` (skill ui-archetype); checklist § Responsivo.

## Goals / Non-Goals

**Goals:**

- Contrato único de responsividade por arquétipo (shell, lista, mestre–detalhe, settings, home, auth).
- Fundação: mobile cards (ou equivalente sem overflow-x grave) em `< md` via shell, reutilizando o ouro de monitoring/clients.
- Cobertura integral das superfícies com UI real (não só pages de lista A/B).
- Gates Vitest + `pnpm run test:gate`; evidência em viewport ~390×844.

**Non-Goals:**

- Redesign visual / rebrand; PWA nativo; breakpoints custom.
- SERPRO live, mutações fiscais, outbound, parecer jurídico.
- Unificação dos três sistemas de KPI; infinite scroll genérico; rename `Shell*` → `Panel*`.
- Reabrir migração anatomia de listas A/B de `alinhar-listas-padrao-clients` (só garantir contrato mobile onde ainda faltar).

## Decisions

### D1 — Breakpoints canônicos (Tailwind / Nuxt UI)

| Threshold | Uso |
|-----------|-----|
| `< sm` (640) | Labels compactos, paginação simplificada, tabs `size="sm"` |
| `< md` (768) | **Cards mobile** em listas densas |
| `< lg` (1024) | Mestre–detalhe → slideover/stack; `SectionNavigation` → select; filtros → modal fullscreen |
| `lg+` | Split painéis; nav horizontal de seção |
| `xl+` | Aside de detalhe cliente (grid main+aside) |

Alternativa rejeitada: breakpoints custom no tema — desalinha template e Nuxt UI.

### D2 — Fundação = generalizar cards para `ShellDataTable`

Extrair/generalizar `ModuleMobileCards` → componente shell (ex. `ShellMobileCards`) e integrar em `ShellDataTable` com colunas/slots primários configuráveis. `ModuleDataTable` passa a compor o mesmo caminho (sem duplicar markup).

Alternativa rejeitada: manter cards só em monitoring e forçar `min-w-*` + scroll em todo o resto — viola checklist «sem overflow horizontal grave».

### D3 — Split = `lg` + `USlideover` (já parcialmente feito)

Mailbox, work queue e calendar já usam slideover em `< lg`. Esta change audita tablet (`md`–`lg`), `resizable` apertado e docs workspace (hoje tabela/modal sem cards).

### D4 — Tasks por arquétipo, não por page

85 pages viram buckets N0–N4; redirects/stubs não ganham task própria. Evita >20 tasks sem valor.

### D5 — Ownership vs upstream

- Não editar `openspec/changes/shell-ui-kit/**` nem `openspec/changes/alinhar-listas-padrao-clients/**`.
- Código `components/shell/*` e pages de lista: ownership mobile nesta change; anatomia já migrada permanece.
- `automatizar-servicos-publicos-mei`: paralelo se não tocar as mesmas pages/modais MEI.

### D6 — Composable de breakpoints

Se `useBreakpoints(breakpointsTailwind)` se repetir em ≥3 novos pontos, extrair `usePanelBreakpoints` (sm/md/lg). Caso contrário, manter local — sem over-engineering.

## Risks / Trade-offs

- **[Risk] Tabelas admin SERPRO com dezenas de colunas** → Mitigation: cards com campos primários + expand/collapsible; desktop mantém tabela.
- **[Risk] Regressão desktop em ModuleTable** → Mitigation: ModuleDataTable compõe shell; testes de contrato + smoke carteiras.
- **[Risk] Conflito com change MEI em pages monitoring** → Mitigation: paralelismo condicional; coordenar arquivos tocados.
- **[Trade-off] Uma change transversal** → Aceito (D1 justificada); rollout por N-levels permite apply parcial com gates.
- **[Trade-off] Aside cliente só empilha abaixo de `xl`** → Pode colapsar em accordion/`lg` se UX pedir; default = stack abaixo de `xl` (já parcial).

## Migration Plan

1. **N0** — ShellDataTable + MobileCards + TableFooter; testes de contrato.
2. **N1** — Migrar consumidores lista sem cards (ops, docs, work listas, admin).
3. **N2** — Split/workspaces (mailbox, work queue, calendar, docs).
4. **N3** — Settings/detalhe cliente/admin forms.
5. **N4** — Gate integrado (collapse, auth/home polish, `test:gate`).
6. **Rollback:** revert de commits por nível; sem migração de API/DB.

## Mapa de dependências

```text
C0 shell-ui-kit (panel-shell-kit) --bloqueante:apply--> C1 alinhar-listas-padrao-clients
                                                              |
                                                              v (coordenada:apply)
                                                    C2 garantir-responsividade-painel-web
                                                       capabilities:
                                                         - painel-responsivo-mobile (NEW)
                                                         - panel-shell-kit (MODIFIED)
```

**Rollout:** N0→N4 no working tree; archive só após N4 PASS.  
**Paralelo interno:** tasks do mesmo N* não dependem entre si.  
**Rollback:** reverter nível; consumers N1+ degradam para comportamento anterior do shell.

### Compatibilidade upstream

| Change | Arquivos compartilhados | Gate |
|--------|-------------------------|------|
| `shell-ui-kit` | `components/shell/*`, `ModuleDataTable` | bloqueante apply — kit presente |
| `alinhar-listas-padrao-clients` | pages lista A/B, gate listas | coordenada apply — não re-migrar anatomia |
| `automatizar-servicos-publicos-mei` | modais/pages MEI | paralelo se paths disjuntos |

## Inventário — pages (`apps/web/app/pages`, 85)

### Auth / onboarding (6)

| Page | Papel | Gap mobile |
|------|-------|------------|
| `login.vue` | Auth form | Baixo (layout auth OK) |
| `first-access.vue` | Senha provisória | Baixo |
| `activate.vue` | Convite | Baixo |
| `onboarding.vue` | Setup | Revisar densidade |
| `two-factor/setup.vue` | Redirect | n/a |
| `two-factor-challenge.vue` | Redirect | n/a |

### Home (1)

| Page | Gap |
|------|-----|
| `index.vue` | Toolbar/KPIs wrap; polish overflow |

### Clientes (17)

| Page | Papel | Gap |
|------|-------|-----|
| `clients.vue` | Shell lista | Collapse/nav |
| `clients/index.vue` | Placeholder | n/a |
| `clients/dashboard.vue` | Dashboard | KPI/toolbar |
| `clients/[id].vue` | Mestre abas + aside `xl` | Aside/stack; SectionNav OK |
| `clients/[id]/*` (11 wrappers) | `Client*` panels | Forms/tabelas densas nos componentes |
| `clients/[id]/sincronizacao.vue` | Sync + NFC-e | Densidade |

### Conta / settings (12 + redirects)

`conta.vue` shell; `conta/*` reexporta `settings/*`. Gaps: grids forms, `settings/usage` tabelas, team grid. Redirects: `settings.vue`, `settings/proxies.vue`, `settings/cte.vue`.

### Monitoramento (18)

Carteiras via `MonitoringModuleTable` (cards OK). Gaps: `min-w-[720–1120px]` ainda no desktop path; mailbox split; `monitoring/clients/[clientId]` seções; hub `monitoring/index.vue` acordeões.

### Work (6)

`WorkQueueWorkspace` (slideover parcial); `work/processes/*`, `work/templates`; `work/calendar.vue` (rail `lg` + slideover).

### Docs / notes (8)

`DocsWorkspace` (gap cards); `docs/imports/*`; redirects notes/import-batches.

### Operações (4)

`exports`, `syncs`, `closing`, `health` — `ShellDataTable` sem cards; colunas `hidden md|lg`.

### Admin (15)

`admin/offices/*`; `admin/serpro/*` (tabelas muito largas); redirects owner/departments/index.

## Inventário — componentes (prioridade)

### Tier 1 — fundação

- `components/shell/DataTable.vue`
- `components/shell/TableFooter.vue`
- `components/monitoring/ModuleMobileCards.vue` → generalizar
- `components/monitoring/ModuleDataTable.vue` → compor shell
- `utils/list-filter-layout.ts`, `utils/table-ui.ts`

### Tier 2 — operacionais

- `components/docs/{Workspace,Catalog,ByClient,Detail}.vue`
- Pages ops/admin/work listas (acima)
- `components/clients/Client*` (painéis de subrota)

### Tier 3 — split / detalhe

- `pages/monitoring/mailbox.vue`, `components/monitoring/Mailbox*.vue`
- `components/work/WorkQueueWorkspace.vue`
- `pages/work/calendar.vue`
- `pages/clients/[id].vue` + `ClientDetailAside` / header
- `components/navigation/SectionNavigation.vue` (uso consistente)

### Tier 4 — polish

- `shell/KpiStrip.vue`, `shell/BulkActionBar.vue`
- `home/*`, `layouts/default.vue`, `OfficeIdentity.vue`
- Modais densos (viewport estreito via `overlay-ui` / Nuxt UI)

### Já adequados (manter / só gate)

- `layouts/auth.vue`, `ShellPageNavbar` + collapse, `ListFilterToolbar`, `data-table-filter/Root` (modal `< lg`), monitoring cards path

## Open Questions

- Nenhuma bloqueante. Campos primários por lista SERPRO definidos na implementação N1 com defaults sensatos (id, status, datas).
