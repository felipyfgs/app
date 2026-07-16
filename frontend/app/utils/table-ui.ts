/**
 * Presets canônicos de UTable do painel — **única fonte** de `:ui` para listas.
 *
 * ## Quando usar cada preset
 *
 * | Preset | Uso |
 * |--------|-----|
 * | `DASHBOARD_TABLE_UI` | Lista admin padrão (arquétipo customers.vue): syncs, health, exports, closing, work/processes|templates, docs/imports, FiscalModuleTable. |
 * | `DENSE_DASHBOARD_TABLE_UI` | Densidade operacional (lista de clientes `/clients`): mais padding compacto em th/td. |
 * | `COMPACT_DASHBOARD_TABLE_UI` | Tabelas embutidas (ex. HomeSales): sem py extra no th. |
 *
 * ## Regras
 *
 * 1. **Proibido** inventar `:ui="{ base: '…', th: '…' }"` solto em páginas de lista.
 * 2. Override pontual **só** via spread do preset, ex.:
 *    `:ui="{ ...DASHBOARD_TABLE_UI, td: \`${DASHBOARD_TABLE_UI.td} align-top\` }"`
 * 3. Casca de lista: `DashboardListShell` (`frontend/app/components/DashboardListShell.vue`).
 * 4. Header ordenável: `sortHeader` em `~/utils/table-sort`.
 *
 * ## Fontes fixadas
 *
 * - DASHBOARD_TABLE_UI: `.reference/nuxt-dashboard-template/app/pages/customers.vue`
 * - COMPACT_DASHBOARD_TABLE_UI: `.reference/nuxt-dashboard-template/app/components/home/HomeSales.vue`
 * - DENSE_DASHBOARD_TABLE_UI: mesma anatomia de customers, com densidade operacional (`clients/index.vue`)
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
