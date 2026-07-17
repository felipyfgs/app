import type { TableColumn } from '@nuxt/ui'
import type {
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { formatDateTime } from '~/utils/format'
import {
  pgmeiDebtMeta,
  pgmeiDebtTooltip,
  pgmeiFreshnessMeta,
  pgmeiSummary,
  pgmeiTotalLabel
} from '~/utils/pgmei'
import { pgdasdTrackingMeta } from '~/utils/pgdasd'
import { sortHeader } from '~/utils/table-sort'

/** Renderer exclusivo da cápsula PGMEI; a seleção é inserida pelo shell autorizado. */
export function buildPgmeiColumns(options: {
  year: number
  canManage: boolean
  onHistory: (row: SimplesMeiClientRow) => void
  onPreview: (row: SimplesMeiClientRow) => void
  onTracking: (row: SimplesMeiClientRow) => void
  onConfigure: (row: SimplesMeiClientRow) => void
  onPreferenceSaved: (
    row: SimplesMeiClientRow,
    preference: PgdasdCommunicationPreference
  ) => void
}): TableColumn<SimplesMeiClientRow>[] {
  const UBadge = resolveComponent('UBadge')
  const UButton = resolveComponent('UButton')
  const UTooltip = resolveComponent('UTooltip')
  const FiscalClientCell = resolveComponent('FiscalClientCell')
  const AutomaticSwitch = resolveComponent('MonitoringPgmeiAutomaticSwitch')

  function iconAction(args: {
    label: string
    icon: string
    color?: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info'
    testId: string
    onClick: () => void
  }) {
    return h(UTooltip, { text: args.label }, {
      default: () => h(UButton, {
        'size': 'sm',
        'color': args.color || 'neutral',
        'variant': 'ghost',
        'icon': args.icon,
        'ariaLabel': args.label,
        'data-testid': args.testId,
        'onClick': args.onClick
      })
    })
  }

  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Razão social', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-64', td: 'min-w-64' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name,
        legalName: row.original.legal_name,
        cnpjMasked: undefined,
        rootCnpjMasked: undefined,
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'active_debt',
      header: 'Dívida ativa',
      enableSorting: false,
      meta: { class: { th: 'min-w-44', td: 'min-w-44' } },
      cell: ({ row }) => {
        const summary = pgmeiSummary(row.original, options.year)
        const debt = pgmeiDebtMeta(summary?.debt_state)
        const freshness = pgmeiFreshnessMeta(summary?.freshness_state)
        return h(UTooltip, { text: pgmeiDebtTooltip(summary) }, {
          default: () => h('span', { class: 'inline-flex flex-wrap items-center gap-1.5' }, [
            h(UBadge, {
              'label': debt.label,
              'color': debt.color,
              'icon': debt.icon,
              'variant': 'subtle',
              'aria-label': `${debt.label} no ano ${options.year}`,
              'data-testid': 'pgmei-debt-state'
            }),
            summary && freshness.label === 'Consulta desatualizada'
              ? h(UBadge, {
                  'label': 'Desatualizada',
                  'color': 'warning',
                  'icon': freshness.icon,
                  'variant': 'outline',
                  'aria-label': freshness.description,
                  'data-testid': 'pgmei-freshness-outdated'
                })
              : null
          ])
        })
      }
    },
    {
      id: 'total_debt',
      header: 'Total inscrito',
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => {
        const summary = pgmeiSummary(row.original, options.year)
        return h(UTooltip, {
          text: summary
            ? `${summary.debt_count} inscrição(ões) no ano ${options.year}.`
            : `Sem consulta válida para ${options.year}.`
        }, {
          default: () => h('span', {
            'class': 'font-medium tabular-nums text-highlighted',
            'data-testid': 'pgmei-total-debt'
          }, pgmeiTotalLabel(summary))
        })
      }
    },
    {
      id: 'send',
      header: 'Enviar',
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => iconAction({
        label: 'Abrir prévia de envio',
        icon: 'i-lucide-send',
        color: 'primary',
        testId: 'pgmei-send-preview',
        onClick: () => options.onPreview(row.original)
      })
    },
    {
      id: 'automatic',
      header: 'Automático',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-24', td: 'w-24 min-w-24' } },
      cell: ({ row }) => h(AutomaticSwitch, {
        clientId: row.original.client_id,
        preference: pgmeiSummary(row.original, options.year)?.communication,
        canManage: options.canManage,
        onConfigure: () => options.onConfigure(row.original),
        onSaved: (preference: PgdasdCommunicationPreference) =>
          options.onPreferenceSaved(row.original, preference)
      })
    },
    {
      id: 'tracking',
      header: 'Rastreio',
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => {
        const tracking = pgdasdTrackingMeta(
          pgmeiSummary(row.original, options.year)?.communication?.tracking_status
        )
        return iconAction({
          label: `Rastreio: ${tracking.label}`,
          icon: tracking.icon,
          color: tracking.color,
          testId: 'pgmei-tracking',
          onClick: () => options.onTracking(row.original)
        })
      }
    },
    {
      id: 'consulted',
      header: ({ column }) => sortHeader('Última consulta', column),
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => formatDateTime(
        pgmeiSummary(row.original, options.year)?.last_valid_query_at
      )
    },
    {
      id: 'details',
      header: 'Detalhes',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => iconAction({
        label: `Ver histórico PGMEI de ${options.year}`,
        icon: 'i-lucide-search',
        testId: 'pgmei-history',
        onClick: () => options.onHistory(row.original)
      })
    }
  ]
}
