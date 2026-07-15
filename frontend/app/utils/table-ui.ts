/**
 * Presets canônicos de tabela do painel.
 *
 * Fontes fixadas:
 * - DASHBOARD_TABLE_UI: `.reference/nuxt-dashboard-template/app/pages/customers.vue`
 * - COMPACT_DASHBOARD_TABLE_UI: `.reference/nuxt-dashboard-template/app/components/home/HomeSales.vue`
 * - DENSE_DASHBOARD_TABLE_UI: mesma anatomia de customers, com densidade operacional.
 */
export const DASHBOARD_TABLE_UI = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
})

export const DENSE_DASHBOARD_TABLE_UI = Object.freeze({
  ...DASHBOARD_TABLE_UI,
  th: 'px-3 py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default px-3 py-2'
})

export const COMPACT_DASHBOARD_TABLE_UI = Object.freeze({
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default'
})

