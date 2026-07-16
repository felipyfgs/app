# Props/slots Nuxt UI usados na família `/work` (1.7)

Consultados via MCP Nuxt UI / docs canônicas (componentes `U*` do @nuxt/ui v4).

| Componente | Uso na família `/work` | Props/slots relevantes |
|------------|------------------------|-------------------------|
| `UDashboardPanel` | Shell de todas as rotas; split Inbox em `/work` | `id`, `default-size`, `min-size`, `max-size`, `resizable`, slots `#header` `#body` |
| `UDashboardNavbar` | Título, collapse, contagem, tabs | slots `#leading` `#trailing` `#right`, `title`, `toggle` |
| `UDashboardToolbar` | Filtros / seções Settings | default slot |
| `UCalendar` | Minicalendário do rail (não scheduler) | `v-model` CalendarDate; sem slots de hora |
| `UTabs` | Tabs da fila, view Mês/Semana/Dia, rail | `v-model`, `items`, `:content="false"`, `size` |
| `USlideover` | Detalhe mobile `/work`; rail mobile calendário | `v-model:open`, `title`, slot `#body` |
| `UProgress` | Progresso processo e KPI departamental | `model-value`, `size`, `aria-label` |
| `UStepper` | Geração por modelo | `items`, `model-value`, `disabled` (passos controlados pelo fluxo) |
| `UFileUpload` | Evidência na tarefa | `accept`, `label`, `description`, `@update:model-value` |
| `UTable` | Processos e modelos | `data`, `columns`, `ui`, slots `*-cell` |
| `UModal` | Criar modelo / geração | `v-model:open`, slots `#body` `#footer` |
| `UNavigationMenu` | Seções do detalhe de processo | `items`, `highlight` |

Divergências justificadas: sem grade horária no calendário (domínio só tem data civil); sem checkboxes decorativos de seleção de colunas na tabela de processos.
