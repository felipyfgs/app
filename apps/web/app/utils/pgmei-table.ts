import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Builders em computed durante o render da UTable perdem o instance da página
 * e as células ficam vazias. @see table-sort.ts / pgdasd-table.ts
 */
import {
  FiscalClientCell
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UButton from '@nuxt/ui/components/Button.vue'
import UIcon from '@nuxt/ui/components/Icon.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'
import { formatDate, formatDateTime } from '~/utils/format'
import { pgdasdTrackingMeta } from '~/utils/pgdasd'
import {
  pgmeiDebtMeta,
  pgmeiDebtTooltip,
  pgmeiFreshnessState,
  pgmeiSummary
} from '~/utils/pgmei'
import { tableIconButton, tableIconGroup } from '~/utils/table-icon-slots'
import { tableCellBadgeProps } from '~/utils/table-ui'
import { sortHeader } from '~/utils/table-sort'
import {
  MONITORING_ACTIONS_LABEL,
  MONITORING_ACTIONS_META,
  MONITORING_CONSULTED_ID,
  MONITORING_CONSULTED_LABEL,
  MONITORING_CONSULTED_META,
  MONITORING_HISTORY_LABEL,
  MONITORING_TRACKING_LABEL
} from '~/utils/monitoring-table-columns'

/**
 * Renderer PGMEI — densidade minimalista:
 * Cliente · Situação · Ações · Hist. comunicação · Consulta · Histórico.
 */
export function buildPgmeiColumns(options: {
  year: number
  onHistory: (row: SimplesMeiClientRow) => void
  onConsult: (row: SimplesMeiClientRow) => void
  onPreview: (row: SimplesMeiClientRow) => void
  onTracking: (row: SimplesMeiClientRow) => void
  onConfigure: (row: SimplesMeiClientRow) => void
  onPublicServices: (row: SimplesMeiClientRow) => void
  canConsult?: boolean
}): TableColumn<SimplesMeiClientRow>[] {
  function rowActions(row: SimplesMeiClientRow) {
    return tableIconGroup([
      tableIconButton({
        label: 'Ver destinatários cadastrados',
        icon: 'i-lucide-message-square-text',
        testId: 'pgmei-communication-info',
        onClick: () => options.onPreview(row)
      }),
      tableIconButton({
        label: 'Ver preferências registradas',
        icon: 'i-lucide-info',
        testId: 'pgmei-communication-preferences',
        onClick: () => options.onConfigure(row)
      }),
      tableIconButton({
        label: 'Abrir consultas CCMEI e DASN-SIMEI',
        icon: 'i-lucide-badge-check',
        testId: 'pgmei-public-consults',
        onClick: () => options.onPublicServices(row)
      })
    ], 'pgmei-actions-group')
  }

  function trackingCell(row: SimplesMeiClientRow) {
    const summary = pgmeiSummary(row, options.year)
    const meta = pgdasdTrackingMeta(summary?.communication?.tracking_status)
    return tableIconButton({
      label: `Histórico local de comunicação: ${meta.label}`,
      icon: meta.icon,
      color: meta.color,
      testId: 'pgmei-tracking',
      onClick: () => options.onTracking(row)
    })
  }

  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'situation',
      header: 'Situação',
      enableSorting: false,
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } },
      cell: ({ row }) => {
        const summary = pgmeiSummary(row.original, options.year)
        const debt = pgmeiDebtMeta(summary?.debt_state)
        const outdated = summary?.debt_state !== 'UNVERIFIED'
          && Boolean(summary?.last_valid_query_at)
          && pgmeiFreshnessState(summary?.freshness_state) === 'OUTDATED'
        return h(UTooltip, { text: pgmeiDebtTooltip(summary, options.year) }, {
          default: () => h('div', {
            'class': 'flex w-full min-w-0 items-center gap-1',
            'aria-label': `Situação PGMEI: ${debt.label}`
          }, [
            h(UBadge, tableCellBadgeProps({
              'label': debt.label,
              'color': debt.color,
              'icon': debt.icon,
              'class': 'min-w-0 flex-1',
              'data-testid': 'pgmei-situation'
            })),
            outdated
              ? h(UIcon, {
                  'name': 'i-lucide-clock-alert',
                  'class': 'size-3.5 shrink-0 text-warning',
                  'aria-label': 'Consulta desatualizada'
                })
              : null
          ])
        })
      }
    },
    {
      id: 'actions',
      header: MONITORING_ACTIONS_LABEL,
      enableSorting: false,
      meta: { ...MONITORING_ACTIONS_META },
      cell: ({ row }) => rowActions(row.original)
    },
    {
      id: 'tracking',
      header: MONITORING_TRACKING_LABEL,
      enableSorting: false,
      meta: { class: { th: 'w-16 min-w-14', td: 'w-16 min-w-14' } },
      cell: ({ row }) => trackingCell(row.original)
    },
    {
      id: MONITORING_CONSULTED_ID,
      header: ({ column }) => sortHeader(MONITORING_CONSULTED_LABEL, column),
      meta: { ...MONITORING_CONSULTED_META },
      cell: ({ row }) => {
        const lastQuery = pgmeiSummary(row.original, options.year)?.last_valid_query_at
        const dateNode = h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : `Nenhuma consulta produtiva válida para ${options.year}`
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-xs',
            'data-testid': 'pgmei-last-query'
          }, formatDate(lastQuery))
        })
        if (options.canConsult === false) {
          return dateNode
        }
        return h('div', { class: 'flex items-center gap-0.5' }, [
          dateNode,
          h(UTooltip, { text: 'Consultar dívida PGMEI deste cliente' }, {
            default: () => h(UButton, {
              'size': 'xs',
              'color': 'primary',
              'variant': 'ghost',
              'icon': 'i-lucide-refresh-cw',
              'aria-label': 'Consultar dívida PGMEI',
              'data-testid': 'pgmei-row-consult',
              'onClick': () => options.onConsult(row.original)
            })
          })
        ])
      }
    },
    {
      id: 'history',
      header: MONITORING_HISTORY_LABEL,
      enableSorting: false,
      meta: { class: { th: 'w-16 min-w-14', td: 'w-16 min-w-14' } },
      cell: ({ row }) => h(UTooltip, {
        text: `Ver histórico fiscal PGMEI de ${options.year}`
      }, {
        default: () => h(UButton, {
          'size': 'xs',
          'color': 'neutral',
          'variant': 'ghost',
          'icon': 'i-lucide-search',
          'aria-label': `Ver histórico fiscal PGMEI de ${options.year}`,
          'data-testid': 'pgmei-history',
          'onClick': () => options.onHistory(row.original)
        })
      })
    }
  ]
}
