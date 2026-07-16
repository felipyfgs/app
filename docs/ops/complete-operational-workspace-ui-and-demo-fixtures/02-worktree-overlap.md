# Worktree e sobreposição com changes ativas

## Changes ativas relevantes

| Change | Sobreposição | Estratégia |
|--------|--------------|------------|
| `add-operational-process-management` | Domínio, APIs `/api/v1/work/*`, páginas `/work/**`, types, policies | Esta change **especializa** UI + fixture; não reabre enums/transições/concorrência |
| `refactor-complete-dashboard-ui-ux` | Shell, navegação, Home, estados assíncronos, fidelidade template | Família `/work` + WorkKpisBlock; evidência compartilhada |
| `complete-monitoring-visual-fixtures` | Padrão de seeder demo fail-closed + âncora | Reutilizar padrão, namespace `work_demo` separado |

## Arquivos já presentes no worktree (preservar e evoluir)

- `backend/database/seeders/OperationalWorkDemoSeeder.php` (untracked) — reescrever para office `demo`
- `frontend/app/pages/work/**` — refatorar, não recriar rotas
- `frontend/app/types/work.ts`, `composables/useApi.ts` work client
- `frontend/app/components/home/WorkKpisBlock.vue`
- Controllers/Services Work em `backend/app/**/Work/**`
- Testes `backend/tests/Feature/Work/*`, `frontend/tests/e2e/work-module.spec.ts`

## Regra

Não reverter mudanças locais de outras changes. Diffs desta change ficam restritos a workspace operacional, fixture e contratos read-only necessários.
