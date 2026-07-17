import type { TableColumn } from '@nuxt/ui'
import type {
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { documentActionVisible } from '~/types/fiscal-modules'
import { formatDate, formatDateTime } from '~/utils/format'
import {
  pgdasdDeclarationMeta,
  pgdasdDeclarationPeriod,
  pgdasdSummary,
  pgdasdTrackingMeta
} from '~/utils/pgdasd'
import { sortHeader } from '~/utils/table-sort'

/**
 * Renderer PGDAS-D — nove colunas de negócio na ordem da referência visual:
 * Situação · Últ. Declaração · Sublimite (RBT12) · Ações · Enviar · Cliente ·
 * Rastreio de envio · Última Busca · Histórico de Busca.
 * Seleção é acrescentada pelo shell autorizado antes de Situação.
 */
export function buildPgdasdColumns(options: {
  canManage: boolean
  /** Lidos no render do header (não no build) para não recriar colunas a cada seleção. */
  getSelectedClientIds: () => number[]
  getSelectedCount: () => number
  getSelectedAutomaticRequested: () => boolean
  onBulkClear: () => void
  onBulkRefresh: () => void
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
  const UDropdownMenu = resolveComponent('UDropdownMenu')
  const UTooltip = resolveComponent('UTooltip')
  const FiscalClientCell = resolveComponent('FiscalClientCell')
  const DeclarationIndicator = resolveComponent('MonitoringPgdasdDeclarationIndicator')
  const Rbt12Value = resolveComponent('MonitoringPgdasdRbt12Value')
  const AutomaticSwitch = resolveComponent('MonitoringPgdasdAutomaticSwitch')
  const BulkAutomaticSwitch = resolveComponent('MonitoringPgdasdBulkAutomaticActions')

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
        'aria-label': args.label,
        'data-testid': args.testId,
        'onClick': args.onClick
      })
    })
  }

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
    return h('div', { class: 'flex items-center gap-0.5' }, [
      iconAction({
        label: 'Abrir prévia de envio',
        icon: 'i-lucide-send',
        color: 'primary',
        testId: 'pgdasd-send-preview',
        onClick: () => options.onPreview(row)
      }),
      h(UDropdownMenu, {
        items: [
          [
            {
              label: 'Configurar comunicação',
              icon: 'i-lucide-settings-2',
              disabled: !options.canManage,
              onSelect: () => options.onConfigure(row)
            }
          ],
          [
            {
              label: 'Abrir cliente',
              icon: 'i-lucide-user-round',
              to: `/monitoring/clients/${row.client_id}`
            }
          ]
        ],
        content: { align: 'end' }
      }, {
        default: () => h(UButton, {
          'size': 'sm',
          'color': 'neutral',
          'variant': 'ghost',
          'icon': 'i-lucide-ellipsis',
          'aria-label': 'Mais ações PGDAS-D',
          'data-testid': 'pgdasd-actions-menu'
        })
      })
    ])
  }

  function trackingCell(row: SimplesMeiClientRow) {
    const summary = pgdasdSummary(row)
    const meta = pgdasdTrackingMeta(summary?.communication?.tracking_status)
    const artifactHref = documentActionVisible(row.document)
      ? row.document?.href?.trim() || null
      : null

    return h('div', { class: 'flex items-center gap-0.5' }, [
      iconAction({
        label: `Status do envio: ${meta.label}`,
        icon: meta.icon,
        color: meta.color,
        testId: 'pgdasd-tracking-status',
        onClick: () => options.onTracking(row)
      }),
      artifactHref
        ? h(UTooltip, { text: row.document?.label || 'Baixar anexo local' }, {
            default: () => h(UButton, {
              'size': 'sm',
              'color': 'primary',
              'variant': 'ghost',
              'icon': 'i-lucide-download',
              'href': artifactHref,
              'target': '_blank',
              'rel': 'noopener',
              'aria-label': row.document?.label || 'Baixar anexo local',
              'data-testid': 'pgdasd-tracking-attachment'
            })
          })
        : h(UTooltip, { text: 'Nenhum anexo local disponível' }, {
            default: () => h(UButton, {
              'size': 'sm',
              'color': 'neutral',
              'variant': 'ghost',
              'icon': 'i-lucide-download',
              'disabled': true,
              'aria-label': 'Nenhum anexo local disponível',
              'data-testid': 'pgdasd-tracking-attachment'
            })
          }),
      iconAction({
        label: 'Abrir rastreio de envio',
        icon: 'i-lucide-search',
        testId: 'pgdasd-tracking',
        onClick: () => options.onTracking(row)
      })
    ])
  }

  return [
    {
      id: 'situation',
      header: 'Situação',
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => {
        const summary = pgdasdSummary(row.original)
        const meta = pgdasdDeclarationMeta(summary?.declaration_state)
        return h(UTooltip, { text: situationTooltip(row.original) }, {
          default: () => h(UBadge, {
            'label': meta.label,
            'color': meta.color,
            'icon': meta.icon,
            'variant': 'subtle',
            'class': 'min-w-24 justify-center',
            'aria-label': `Situação PGDAS-D: ${meta.label}`,
            'data-testid': 'pgdasd-situation'
          })
        })
      }
    },
    {
      id: 'last_declaration',
      header: 'Últ. Declaração',
      enableSorting: false,
      meta: { class: { th: 'min-w-28', td: 'min-w-28' } },
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
        text: 'Receita bruta acumulada nos 12 meses anteriores ao período de apuração; não é o sublimite anual legal.'
      }, {
        default: () => h('span', { class: 'inline-flex items-center gap-1' }, [
          'Sublimite (RBT12)',
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
      id: 'actions',
      header: 'Ações',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-24', td: 'w-24 min-w-24' } },
      cell: ({ row }) => rowActions(row.original)
    },
    {
      id: 'send',
      header: () => h('div', { class: 'flex items-center gap-2' }, [
        h('span', 'Enviar'),
        options.canManage
          ? h(BulkAutomaticSwitch, {
              selectedClientIds: options.getSelectedClientIds(),
              selectedCount: options.getSelectedCount(),
              modelValue: options.getSelectedAutomaticRequested(),
              onClear: options.onBulkClear,
              onRefresh: options.onBulkRefresh
            })
          : null
      ]),
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-28', td: 'w-28 min-w-28' } },
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
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-64', td: 'min-w-64' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'tracking',
      header: 'Rastreio de envio',
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => trackingCell(row.original)
    },
    {
      id: 'consulted',
      header: ({ column }) => sortHeader('Última Busca', column),
      meta: { class: { th: 'min-w-32', td: 'min-w-32' } },
      cell: ({ row }) => {
        const lastQuery = pgdasdSummary(row.original)?.last_valid_query_at
        return h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : 'Nenhuma consulta produtiva válida'
        }, {
          default: () => h('span', {
            class: 'whitespace-nowrap tabular-nums text-sm',
            'data-testid': 'pgdasd-last-query'
          }, formatDate(lastQuery))
        })
      }
    },
    {
      id: 'history',
      header: 'Histórico de Busca',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'min-w-40', td: 'min-w-40' } },
      cell: ({ row }) => h(UTooltip, {
        text: 'Ver histórico fiscal PGDAS-D'
      }, {
        default: () => h(UButton, {
          'size': 'sm',
          'color': 'neutral',
          'variant': 'outline',
          'icon': 'i-lucide-search',
          'block': true,
          'class': 'w-full justify-center',
          'aria-label': 'Ver histórico fiscal PGDAS-D',
          'data-testid': 'pgdasd-history',
          'onClick': () => options.onHistory(row.original)
        })
      })
    }
  ]
}
