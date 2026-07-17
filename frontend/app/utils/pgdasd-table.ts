import type { TableColumn } from '@nuxt/ui'
import type {
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { formatDateTime } from '~/utils/format'
import {
  pgdasdDeclarationPeriod,
  pgdasdSummary,
  pgdasdTrackingMeta
} from '~/utils/pgdasd'
import { sortHeader } from '~/utils/table-sort'

/**
 * Renderer exclusivo do PGDAS-D. A seleção é acrescentada pelo shell somente
 * para ADMIN/OPERATOR; PGMEI, DASN e Regime continuam com as colunas genéricas.
 */
export function buildPgdasdColumns(options: {
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
  const UButton = resolveComponent('UButton')
  const UTooltip = resolveComponent('UTooltip')
  const FiscalClientCell = resolveComponent('FiscalClientCell')
  const DeclarationIndicator = resolveComponent('MonitoringPgdasdDeclarationIndicator')
  const Rbt12Value = resolveComponent('MonitoringPgdasdRbt12Value')
  const AutomaticSwitch = resolveComponent('MonitoringPgdasdAutomaticSwitch')

  function iconAction(args: {
    label: string
    icon: string
    color?: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info'
    testId: string
    onClick: () => void
  }) {
    return h(UTooltip, { text: args.label }, {
      default: () => h(UButton, {
        size: 'sm',
        color: args.color || 'neutral',
        variant: 'ghost',
        icon: args.icon,
        ariaLabel: args.label,
        'data-testid': args.testId,
        onClick: args.onClick
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
      id: 'last_declaration',
      header: 'Última declaração',
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => {
        const summary = pgdasdSummary(row.original)
        return h(DeclarationIndicator, {
          period: pgdasdDeclarationPeriod(summary),
          state: summary?.declaration_state,
          reason: summary?.declaration_reason
        })
      }
    },
    {
      id: 'rbt12',
      header: () => h(UTooltip, {
        text: 'Receita bruta acumulada nos 12 meses anteriores ao período de apuração; não é o sublimite anual.'
      }, {
        default: () => h('span', { class: 'inline-flex items-center gap-1' }, [
          'RBT12',
          h('span', { class: 'sr-only' }, ' — receita bruta acumulada em doze meses')
        ])
      }),
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => h(Rbt12Value, {
        rbt12: pgdasdSummary(row.original)?.rbt12
      })
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
        testId: 'pgdasd-send-preview',
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
        preference: pgdasdSummary(row.original)?.communication,
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
        const meta = pgdasdTrackingMeta(
          pgdasdSummary(row.original)?.communication?.tracking_status
        )
        return iconAction({
          label: `Rastreio: ${meta.label}`,
          icon: meta.icon,
          color: meta.color,
          testId: 'pgdasd-tracking',
          onClick: () => options.onTracking(row.original)
        })
      }
    },
    {
      id: 'consulted',
      header: ({ column }) => sortHeader('Última consulta', column),
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => formatDateTime(
        pgdasdSummary(row.original)?.last_valid_query_at
      )
    },
    {
      id: 'details',
      header: 'Detalhes',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => iconAction({
        label: 'Ver histórico PGDAS-D',
        icon: 'i-lucide-search',
        testId: 'pgdasd-history',
        onClick: () => options.onHistory(row.original)
      })
    }
  ]
}
