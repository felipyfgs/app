# Checklist nuxt-dashboard-template — rotas `/work`

**Change:** `complete-operational-workspace-ui-and-demo-fixtures`  
**Template:** `.reference/nuxt-dashboard-template` @ `0f30c09`  
**Data:** 2026-07-16

Legenda: ✅ ok · ⚠️ parcial/justificado · ❌ pendente

## Matriz por rota

| Rota | Arquétipo | Origem | Cópia vs adaptação | Dados/estados | Auth | Responsivo/a11y | Nav | Stack | Final |
|------|-----------|--------|--------------------|---------------|------|-----------------|-----|-------|-------|
| `/work` | mestre–detalhe | `inbox.vue` + `InboxList`/`InboxMail` | ✅ | ✅ API real | ✅ papéis | ✅ split lg + slideover mobile | ✅ | ✅ | ✅ |
| `/work/calendar` | shell home + corpo domínio | `index.vue` navbar/toolbar | ✅ (corpo novo justificado) | ✅ agregados server-side | ✅ | ✅ rail/drawer | ✅ | ✅ | ✅ |
| `/work/processes` | lista admin | `customers.vue` | ✅ | ✅ paginação SS | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/work/processes/[id]` | settings/seções | `settings.vue` | ✅ | ✅ seções + 404 | ✅ | ✅ | ✅ | ✅ | ✅ |
| `/work/templates` | lista + modal | `customers.vue` + `AddModal` | ✅ | ✅ preview/batch real | ✅ ADMIN | ✅ | ✅ | ✅ | ✅ |
| `/admin/departments` | members/settings | `settings/members` | ✅ | ✅ | ✅ ADMIN+2FA | ✅ | ✅ | ✅ | ✅ |
| Home Work KPIs | home cards | `components/home/*` | ✅ | ✅ bloco separado fiscal | ✅ | ✅ | deep-links | ✅ | ✅ |

## Itens transversais do checklist

- [x] Arquétipos identificados e registrados (`docs/ops/complete-operational-workspace-ui-and-demo-fixtures/03-template-matrix.md`)
- [x] Slots `#header` / `#body` / leading / right preservados
- [x] Zero `server/api` mock do template
- [x] Sem `TeamsMenu` / office_id livre
- [x] Loading / vazio / erro nas superfícies principais
- [x] Paginação server-side
- [x] VIEWER somente leitura; ADMIN catálogo
- [x] Sem PFX/PEM/vault em UI
- [x] `UDashboardSidebarCollapse` nos navbars Work
- [x] Sidebar + command palette “Trabalho”
- [x] Títulos pt-BR

## Evidências de teste

| Item | Evidência |
|------|-----------|
| Unit labels/URL/calendário | `frontend/tests/unit/work-*.test.ts` |
| E2E workspace | `frontend/tests/e2e/work-workspace.spec.ts`, `work-module.spec.ts` |
| Scanner artefatos | `frontend/tests/security/scan-artifacts.mjs` (vault/storage_path) |
| PHPUnit Work | `backend/tests/Feature/Work/*` |
