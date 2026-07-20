import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Estes builders rodam em computed durante o render da UTable; getCurrentInstance()
 * não é a página e resolveComponent falha → células vazias com 1 registro no rodapé.
 * @see table-sort.ts (mesmo padrão com #components)
 */
import {
  FiscalClientCell,
  MonitoringPgdasdDeclarationIndicator,
  MonitoringPgdasdRbt12Value
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UButton from '@nuxt/ui/components/Button.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'
import { formatDate, formatDateTime } from '~/utils/format'
import {
  pgdasdDeclarationMeta,
  pgdasdDeclarationPeriod,
  pgdasdSummary,
  pgdasdTrackingMeta
} from '~/utils/pgdasd'
import { tableIconButton, tableIconGroup } from '~/utils/table-icon-slots'
import { sortHeader } from '~/utils/table-sort'
import { tableCellBadgeProps } from '~/utils/table-ui'
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
 * Renderer PGDAS-D — densidade minimalista:
 * Cliente · Situação · Últ. Declaração · RBT12 · Ações · Hist. comunicação · Consulta · Histórico.
 * Seleção é acrescentada pelo shell autorizado antes de Cliente.
 */
export function buildPgdasdColumns(options: {
  onHistory: (row: SimplesMeiClientRow) => void
  onPreview: (row: SimplesMeiClientRow) => void
  onTracking: (row: SimplesMeiClientRow) => void
  onConfigure: (row: SimplesMeiClientRow) => void
  onConsult?: (row: SimplesMeiClientRow) => void
  canConsult?: boolean
}): TableColumn<SimplesMeiClientRow>[] {
  const DeclarationIndicator = MonitoringPgdasdDeclarationIndicator
  const Rbt12Value = MonitoringPgdasdRbt12Value

  function situationTooltip(row: SimplesMeiClientRow): string {
    const summary = pgdasdSummary(row)
    const meta = pgdasdDeclarationMeta(summary?.declaration_state)
    const period = pgdasdDeclarationPeriod(summary)
    const reason = summary?.declaration_state_reason || summary?.declaration_reason
    const lastQuery = summary?.last_valid_query_at
    return [
      meta.label,
      period !== '—' ? `PA: ${period}.` : null,
      reason?.trim() || meta.description,
      lastQuery
        ? `Última consulta válida: ${formatDateTime(lastQuery)}.`
        : 'Nenhuma consulta produtiva válida.'
    ].filter(Boolean).join(' ')
  }

  function rowActions(row: SimplesMeiClientRow) {
    return tableIconGroup([
      tableIconButton({
        label: 'Ver destinatários e documentos locais',
        icon: 'i-lucide-message-square-text',
        testId: 'pgdasd-communication-info',
        onClick: () => options.onPreview(row)
      }),
      tableIconButton({
        label: 'Ver preferências registradas',
        icon: 'i-lucide-info',
        testId: 'pgdasd-communication-preferences',
        onClick: () => options.onConfigure(row)
      })
    ], 'pgdasd-actions-group')
  }

  function trackingCell(row: SimplesMeiClientRow) {
    const summary = pgdasdSummary(row)
    const meta = pgdasdTrackingMeta(summary?.communication?.tracking_status)
    return tableIconButton({
      label: `Histórico local de comunicação: ${meta.label}`,
      icon: meta.icon,
      color: meta.color,
      testId: 'pgdasd-tracking',
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
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => {
        const summary = pgdasdSummary(row.original)
        const meta = pgdasdDeclarationMeta(summary?.declaration_state)
        return h(UTooltip, { text: situationTooltip(row.original) }, {
          default: () => h('div', { class: 'block w-full min-w-0' }, [
            h(UBadge, tableCellBadgeProps({
              'label': meta.label,
              'color': meta.color,
              'icon': meta.icon,
              'aria-label': `Situação PGDAS-D: ${meta.label}`,
              'data-testid': 'pgdasd-situation'
            }))
          ])
        })
      }
    },
    {
      id: 'last_declaration',
      header: 'Últ. Declaração',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-20', td: 'w-24 min-w-20' } },
      cell: ({ row }) => {
        const summary = pgdasdSummary(row.original)
        return h(DeclarationIndicator, {
          period: pgdasdDeclarationPeriod(summary),
          state: summary?.declaration_state,
          reason: summary?.declaration_state_reason || summary?.declaration_reason
        })
      }
    },
    {
      id: 'rbt12',
      header: () => h(UTooltip, {
        text: 'RBT12 (RB12): receita bruta acumulada nos 12 meses anteriores ao período de apuração. Não é RPA (receita do mês) nem o sublimite anual.'
      }, {
        default: () => h('span', { class: 'whitespace-nowrap' }, [
          'RBT12',
          h('span', { class: 'sr-only' }, ' — receita bruta acumulada em doze meses')
        ])
      }),
      enableSorting: false,
      meta: { class: { th: 'w-0 whitespace-nowrap', td: 'w-0 whitespace-nowrap' } },
      cell: ({ row }) => h(Rbt12Value, {
        rbt12: pgdasdSummary(row.original)?.rbt12
      })
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
        const lastQuery = pgdasdSummary(row.original)?.last_valid_query_at
        const dateNode = h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : 'Nenhuma consulta produtiva válida'
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-xs',
            'data-testid': 'pgdasd-last-query'
          }, formatDate(lastQuery))
        })
        if (!options.canConsult || !options.onConsult) {
          return dateNode
        }
        return h('div', { class: 'flex items-center gap-0.5' }, [
          dateNode,
          h(UTooltip, { text: 'Consultar PGDAS-D deste cliente' }, {
            default: () => h(UButton, {
              'size': 'xs',
              'color': 'primary',
              'variant': 'ghost',
              'icon': 'i-lucide-refresh-cw',
              'aria-label': 'Consultar PGDAS-D',
              'data-testid': 'pgdasd-row-consult',
              'onClick': () => options.onConsult?.(row.original)
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
        text: 'Ver histórico fiscal PGDAS-D'
      }, {
        default: () => h(UButton, {
          'size': 'xs',
          'color': 'neutral',
          'variant': 'ghost',
          'icon': 'i-lucide-search',
          'aria-label': 'Ver histórico fiscal PGDAS-D',
          'data-testid': 'pgdasd-history',
          'onClick': () => options.onHistory(row.original)
        })
      })
    }
  ]
}
