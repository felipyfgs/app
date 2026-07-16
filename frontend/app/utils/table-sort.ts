import { h } from 'vue'
import { UButton } from '#components'

/**
 * Header ordenável canônico para colunas de UTable (TanStack).
 *
 * Fonte: frontend/app/pages/clients/index.vue (produto) +
 * `.reference/nuxt-dashboard-template/app/pages/customers.vue` (template).
 *
 * Uso:
 *   import { sortHeader } from '~/utils/table-sort'
 *   { header: ({ column }) => sortHeader('Razão social', column) }
 */
export type SortableColumn = {
  getIsSorted: () => false | 'asc' | 'desc'
  toggleSorting: (desc?: boolean) => void
}

export function sortHeader(label: string, column: SortableColumn) {
  const isSorted = column.getIsSorted()

  return h(UButton, {
    color: 'neutral',
    variant: 'ghost',
    label,
    icon: isSorted
      ? (isSorted === 'asc' ? 'i-lucide-arrow-up-narrow-wide' : 'i-lucide-arrow-down-wide-narrow')
      : 'i-lucide-arrow-up-down',
    class: '-mx-2.5',
    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc')
  })
}
