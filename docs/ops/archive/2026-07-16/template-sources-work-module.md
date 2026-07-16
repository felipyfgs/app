# Arquivos-fonte do template `0f30c09` para o módulo Work

Registrados antes da adaptação das rotas (task 12.1).

| Superfície | Rota | Arquétipo no `.reference/nuxt-dashboard-template` @ `0f30c09` |
|------------|------|---------------------------------------------------------------|
| Home + KPIs | `/` | `app/pages/index.vue` + `components/home/*` |
| Minha fila | `/work` | `app/pages/inbox.vue` + `components/inbox/*` |
| Processos | `/work/processes` | `app/pages/customers.vue` |
| Detalhe processo | `/work/processes/[id]` | `app/pages/settings.vue` / `settings/*` |
| Modelos | `/work/templates` | `app/pages/customers.vue` + `components/customers/AddModal.vue` |
| Calendário | `/work/calendar` | shell de `index.vue` (navbar/toolbar); corpo de domínio |
| Departamentos | `/admin/departments` | `settings/members.vue` |

**Não copiados:** `TeamsMenu`, `server/api/*`, paginação client-side de demo, toast de marketing.
