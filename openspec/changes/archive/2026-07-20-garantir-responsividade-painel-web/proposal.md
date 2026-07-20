## Why

O painel Nuxt tem shell, filtros e algumas listas fiscais já usáveis no telefone, mas a maior parte das páginas e do `ShellDataTable` ainda é desktop-first (scroll horizontal, colunas só com `hidden md|lg`, workspaces sem cards). Operadores acessam o hub no celular; falta um contrato único de responsividade cobrindo as ~85 pages e os componentes compartilhados, alinhado ao checklist ui-archetype e ao ouro de mobile cards de monitoring/`/clients`.

## What Changes

- Introduzir o contrato `painel-responsivo-mobile`: breakpoints Tailwind padrão, navbar com collapse, listas com cards `< md`, mestre–detalhe com slideover/stack em `< lg`, settings em stack, sem overflow horizontal grave.
- Generalizar mobile cards de `ModuleDataTable`/`ModuleMobileCards` para o kit shell (`ShellDataTable` + footer empilhável), e migrar consumidores que ainda só usam tabela larga.
- Auditar e ajustar mestre–detalhe (mailbox, work queue, calendar), docs workspace, detalhe de cliente, conta/settings, admin e superfícies auth/home conforme inventário do design.
- Estender requisitos de `panel-shell-kit` para exigir comportamento mobile normativo em `ShellDataTable` / chrome de lista (não só em monitoring).
- Gates Vitest de contrato UI + `pnpm run test:gate`; evidência visual em viewport ~390×844 nas superfícies tocadas.

## Capabilities

### New Capabilities

- `painel-responsivo-mobile`: requisitos de experiência mobile do painel por arquétipo (shell, lista, mestre–detalhe, settings, home, auth) — breakpoints, cards, slideover, collapse, ausência de overflow-x grave e critérios de verificação.

### Modified Capabilities

- `panel-shell-kit`: passar a exigir modo cards (ou equivalente sem overflow grave) em viewport `< md` para listas via `ShellDataTable`, e footer/toolbar compatíveis com phone; mobile cards deixam de ser exclusivos de `ModuleDataTable`.

## Impact

- **Código:** `apps/web/app/layouts/*`, `apps/web/app/pages/**` (~85 arquivos; redirects só se tocados indiretamente), `apps/web/app/components/shell/*`, `monitoring/Module{DataTable,MobileCards,Table}.vue`, `docs/Workspace.vue` (+ Catalog/ByClient/Detail), `work/WorkQueueWorkspace.vue`, `clients/Client*`, `navigation/SectionNavigation.vue`, utils `list-filter-layout.ts` / `table-ui.ts`, testes em `apps/web/tests/unit/`.
- **API / backend:** nenhuma.
- **Dependências:** Nuxt 4 SPA + Nuxt UI Dashboard; template `@ 0f30c09` via ui-archetype; kit `Shell*` de `shell-ui-kit`; padrão ouro de lista de `alinhar-listas-padrao-clients`.
- **Non-goals:** redesign visual / rebrand; PWA nativo; breakpoints custom fora do Tailwind/Nuxt UI; SERPRO live; mutações fiscais; outbound; parecer jurídico; unificação dos sistemas de KPI (`shell` / `monitoring` / `home`); infinite scroll genérico; rename em massa para `Panel*`.

### Dependências entre changes

- **Nível:** `C2`
- **Bases estáveis:** template `@ 0f30c09`; checklist responsivo ui-archetype; ouro mobile cards em monitoring + `/clients`
- **Depende de:**
  - `shell-ui-kit` — capability `panel-shell-kit` — marco `apply` — relação `bloqueante` (kit `Shell*` no working tree)
  - `alinhar-listas-padrao-clients` — capability `listas-padrao-clients` — marco `apply` — relação `coordenada` (não reabrir migração A/B de listas; esta change cobre contrato responsivo transversal e buckets fora do escopo de listas)
- **Desbloqueia:** polish futuro de split/settings/docs-workspace sem reinventar cards mobile
- **Paralelismo:** pode avançar em paralelo com `automatizar-servicos-publicos-mei` desde que não edite as mesmas superfícies MEI/modais; não editar artifacts das changes upstream; ownership de `panel-shell-kit` mobile nesta change após apply do kit
