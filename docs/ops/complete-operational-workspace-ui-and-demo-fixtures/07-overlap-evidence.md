# Evidência de sobreposição com changes ativas (13.9)

## `add-operational-process-management`

- Domínio, policies, transições, endpoints e páginas base `/work/**` permanecem autoridade.
- Esta change **não** reabre enums, concorrência otimista nem geração batch.
- Incrementos: seeder office `demo`, DTOs read-only (calendário/KPI/processo), UI template-fidelity.

## `refactor-complete-dashboard-ui-ux`

- Família `/work` e `WorkKpisBlock` evoluem aqui; shell global/navegação geral continua na change guarda-chuva.
- Composables de labels/filtros/calendário são específicos de Work — não substituem utilitários transversais.

## `complete-monitoring-visual-fixtures`

- Padrão fail-closed + âncora reutilizado via `work_demo` (config/guard próprios).
- Namespaces e markers distintos (`[demo-work-fixture]` vs fiscal).

## Regra de handoff

Não marcar tasks E2E/visuais/axe desta change como concluídas sem artefato Playwright/scanner executado.
Tarefas de implementação de UI/API/fixture listadas em `tasks.md` com `- [x]` possuem código ou doc correspondente no repositório.
