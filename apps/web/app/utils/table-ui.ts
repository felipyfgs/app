/**
 * Presets de UTable — cópia do template @ 0f30c09 + densidade do painel.
 *
 * Tema Nuxt UI (`.nuxt/ui/table.ts`) default:
 *   th → `px-4 py-3.5` · td → `p-4`
 * Os presets abaixo sobrescrevem padding via `ui` (tailwind-merge) sem reduzir
 * `text-sm` do tema — só apertam ar vertical/horizontal.
 *
 * - DASHBOARD_TABLE_UI ← customers.vue (lista admin)
 * - COMPACT_DASHBOARD_TABLE_UI ← HomeSales.vue + py/px densos
 * - MONITORING_COMPACT_TABLE_UI ← carteiras fiscais (ModuleDataTable)
 *
 * Contrato de célula:
 * - UBadge de status/chip: size="md" + TABLE_CELL_BADGE_* (preenche a célula)
 * - UButton ícone: size="xs", variant="ghost"
 * - Larguras só em column.meta.class
 */

/** Classes do badge que preenche a célula (padrão clients/index). */
export const TABLE_CELL_BADGE_CLASS = 'h-8 w-full min-w-0 justify-center tabular-nums font-normal'

/** `:ui` do UBadge em célula — ocupa a largura útil sem mexer no padding do td. */
export const TABLE_CELL_BADGE_UI = Object.freeze({
  base: 'h-8 w-full min-w-0 justify-center rounded-md',
  label: 'truncate text-center'
})

/**
 * Props canônicas para UBadge em célula de UTable (`h()` / template).
 * Mantém variant subtle por padrão; overrides mesclam `class`/`ui`.
 */
export function tableCellBadgeProps(overrides: Record<string, unknown> = {}) {
  const extraClass = typeof overrides.class === 'string' ? overrides.class : undefined
  const extraUi = overrides.ui && typeof overrides.ui === 'object'
    ? overrides.ui as Record<string, string>
    : undefined
  const { class: _c, ui: _u, ...rest } = overrides
  return {
    size: 'md' as const,
    variant: 'subtle' as const,
    class: [TABLE_CELL_BADGE_CLASS, extraClass].filter(Boolean).join(' '),
    ui: { ...TABLE_CELL_BADGE_UI, ...extraUi },
    ...rest
  }
}

/**
 * Anatomia customers.vue + padding denso (anula py-3.5 / p-4 do tema).
 * Fonte permanece text-sm do UTable.
 */
const customersTableUi = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'px-3 py-1.5 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'px-3 py-1 border-b border-default',
  separator: 'h-0'
})

export const DASHBOARD_TABLE_UI = customersTableUi

/** Alias histórico — mesma anatomia de customers.vue (não reexporta o mesmo nome). */
export const DENSE_DASHBOARD_TABLE_UI = customersTableUi

/**
 * HomeSales.vue — header/linhas mais apertados que a lista admin.
 * Anula explicitamente px-4/py-3.5/p-4 do tema Nuxt UI.
 */
export const COMPACT_DASHBOARD_TABLE_UI = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'px-2 py-1 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'px-2 py-0.5 border-b border-default',
  separator: 'h-0'
})

/**
 * Carteiras de monitoramento — Compact + padding horizontal responsivo.
 * Usado por ModuleDataTable (todas as grades fiscais desktop).
 */
export const MONITORING_COMPACT_TABLE_UI = Object.freeze({
  root: 'overflow-visible',
  base: COMPACT_DASHBOARD_TABLE_UI.base,
  thead: `bg-default ${COMPACT_DASHBOARD_TABLE_UI.thead}`,
  tbody: COMPACT_DASHBOARD_TABLE_UI.tbody,
  th: 'px-2 sm:px-2.5 py-1 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'px-2 sm:px-2.5 py-0.5 border-b border-default',
  separator: 'h-0'
})
