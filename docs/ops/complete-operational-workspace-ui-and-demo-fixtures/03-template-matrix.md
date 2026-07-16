# Matriz `/work` → template fixado (`0f30c09`)

Referência: `.reference/nuxt-dashboard-template` @ `0f30c09d697160ef5dd0aaaec27fae8d7195d930`.

| Rota produto | Arquivo(s) do template | Uso |
|--------------|------------------------|-----|
| `/work` lista | `pages/inbox.vue` + `components/inbox/InboxList.vue` | Painel resizable (`default-size=25`, `min=20`, `max=30`), tabs na navbar, linhas densas, ArrowUp/Down |
| `/work` detalhe | `components/inbox/InboxMail.vue` | Segundo `UDashboardPanel`, header com fechar, ações, corpo |
| `/work` neutro desktop | `pages/inbox.vue` (ícone vazio) | `UIcon` grande quando sem seleção |
| `/work` mobile | Inbox + `USlideover` | Detalhe em overlay &lt; `lg` |
| `/work/calendar` shell | `pages/index.vue` (Home) | Navbar + toolbar + body; collapse sidebar |
| `/work/calendar` mini | MCP `UCalendar` | Seletor de data (não scheduler) |
| `/work/processes` | `pages/customers.vue` | Lista admin, `UTable`, filtros, paginação, ações de linha |
| `/work/processes/{id}` | `pages/settings.vue` | Shell com seções em toolbar/`UNavigationMenu` |
| `/work/templates` lista | `pages/customers.vue` | Lista admin |
| `/work/templates` create/edit | `components/customers/AddModal.vue` | Modal `UForm` |
| `/work/templates` geração | MCP `UStepper` | Selecionar → Configurar → Pré-visualizar → Confirmar → Acompanhar |
| Home Work KPIs | `components/home/HomeStats.vue` | Cards compactos + progress |

## Divergências estruturais registradas (1.4 / 1.5)

### `/work` vs Inbox (pré-refactor)

- Um único `UDashboardPanel` com lista+detalhe no body (sem split resizable irmão).
- Tabs na toolbar, não na navbar trailing.
- Sem `defineShortcuts` ArrowUp/Down / refs de scroll.
- Detalhe sem segundo painel; empty state textual, não ícone.
- Upload via `<input type="file">` nativo, não `UFileUpload`.

### Processos/modelos vs Customers / Settings / AddModal

- Processos: tabela mínima sem badges de risco/progresso, poucas colunas, ações = click na linha.
- Detalhe processo: cards soltos, sem seções Settings com deep-link de seção.
- Modelos: modal ad-hoc, sem stepper de geração completo nem editor de tarefas ordenado.

## Agenda Makro — aproveitar vs recusar (1.6)

**Aproveitar (densidade/UX, sem copiar marca):**

- Alternância Dia / Semana / Mês
- Contagens e severidade por dia
- Rail lateral com minicalendário + listas da data
- Navegação anterior / hoje / próximo

**Recusar explicitamente:**

- Grade horária 05h–19h (domínio só tem datas civis de prazo)
- Cores/tipografia/sidebar Makro
- Compromissos, recorrências, feriados, sync externo
