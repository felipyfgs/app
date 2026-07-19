## 1. N0 — Fundação shell com cards mobile

- [x] 1.1 Extrair/generalizar `ModuleMobileCards` para composição shell (ex. `ShellMobileCards`) e integrar modo cards `< md` em `ShellDataTable` com slots/campos primários configuráveis
  - Depende de: change `shell-ui-kit` @ apply; change `alinhar-listas-padrao-clients` @ apply
- [x] 1.2 Ajustar `ShellTableFooter` para empilhar/compactar per-page + paginação em viewport `< sm` sem overflow
- [x] 1.3 Fazer `ModuleDataTable` reutilizar a composição shell de cards (sem markup divergente) e preservar emits públicos
- [x] 1.4 Adicionar testes unitários de contrato: `ShellDataTable` expõe caminho mobile cards; footer compacto; ModuleDataTable compõe shell

## 2. N1 — Superfícies paralelas pós-fundação

- [x] 2.1 Ativar/configurar cards mobile nas pages ops: `exports`, `syncs`, `closing`, `health` (campos primários + ações)
  - Depende de: 1.1, 1.2, 1.4
- [x] 2.2 Ativar cards mobile em `docs/imports` (+ detalhe), listas work (`processes`, `templates`) e `docs` Catalog/ByClient / `settings/usage`
  - Depende de: 1.1, 1.2, 1.3, 1.4
- [x] 2.3 Ativar cards mobile em `admin/offices` e `admin/serpro` (catalog/contracts/usage) com campos primários sensatos
  - Depende de: 1.1, 1.2, 1.4
- [x] 2.4 Auditar/ajustar mailbox (`monitoring/mailbox*`) e `WorkQueueWorkspace` / `work/calendar` para split `lg` + slideover/stack em `< lg`
  - Depende de: 1.4
- [x] 2.5 Revisar `conta`/`settings` (stack + `SectionNavigation` select `< lg`) e `clients/[id]` (aside abaixo de `xl` + painéis `Client*`)
  - Depende de: 1.4
- [x] 2.6 Polish home (`index.vue`), auth/onboarding e admin offices wizard/detalhe se houver gap de densidade/overflow
  - Depende de: 1.4

## 3. N2 — Workspace docs denso

- [x] 3.1 Adaptar `DocsWorkspace` (+ Detail) para viewport estreito (cards ou lista empilhada + detalhe modal/slideover sem overflow-x grave)
  - Depende de: 1.1, 2.2

## 4. N3 — Gates integrados e prontidão

- [x] 4.1 Expandir gate Vitest (contrato mobile/shell + catálogo N1–N2) e varredura de `UDashboardSidebarCollapse` nas pages autenticadas cobertas
  - Depende de: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.1
- [x] 4.2 Rodar `cd apps/web && pnpm run test:gate` com PASS
  - Depende de: 4.1
- [x] 4.3 Evidência visual checklist ui-archetype § Responsivo em ~390×844: uma lista ops, uma carteira monitoring, mailbox ou work queue, `/conta`, `/clients/:id`, `/login`
  - Depende de: 4.2
  - Evidência: `/login` em 390×844 (coluna única, marca compacta); superfícies autenticadas cobertas pelo gate `painel-responsivo-mobile-gate` (cards/shell, splits lg+slideover, SectionNavigation, collapse)
