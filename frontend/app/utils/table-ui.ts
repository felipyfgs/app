/**
 * Presets de UTable — cópia literal do template @ 0f30c09.
 *
 * - DASHBOARD_TABLE_UI ← pages/customers.vue `:ui`
 * - COMPACT_DASHBOARD_TABLE_UI ← components/home/HomeSales.vue `:ui`
 *
 * Sem `root`/`max-h`/`100dvh`. Sem densidades inventadas.
 */

const customersTableUi = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
})

export const DASHBOARD_TABLE_UI = customersTableUi

/** Alias histórico — mesma anatomia de customers.vue (não reexporta o mesmo nome). */
export const DENSE_DASHBOARD_TABLE_UI = customersTableUi

/** HomeSales.vue — th sem py-2. */
export const COMPACT_DASHBOARD_TABLE_UI = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default'
})
