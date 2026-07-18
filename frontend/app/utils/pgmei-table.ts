import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Builders em computed durante o render da UTable perdem o instance da página
 * e as células ficam vazias. @see table-sort.ts / pgdasd-table.ts
 */
import {
  FiscalClientCell,
  MonitoringPgmeiAutomaticSwitch,
  MonitoringPgmeiBulkAutomaticActions,
  UBadge,
  UButton,
  UDropdownMenu,
  UIcon,
  UTooltip
} from '#components'
import type {
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { documentActionVisible } from '~/types/fiscal-modules'
import { formatDate, formatDateTime } from '~/utils/format'
import { pgdasdTrackingMeta } from '~/utils/pgdasd'
import {
  pgmeiDebtMeta,
  pgmeiDebtTooltip,
  pgmeiFreshnessState,
  pgmeiSummary
} from '~/utils/pgmei'
import { sortHeader } from '~/utils/table-sort'

/**
 * Renderer PGMEI — sete colunas de negócio na ordem da referência visual:
 * Situação · Ações · Enviar · Cliente · Rastreio de envio · Última Busca ·
 * Histórico de Busca. Sem colunas mensais do PGDAS-D.
 * Seleção é acrescentada pelo shell autorizado antes de Situação.
 */
export function buildPgmeiColumns(options: {
  year: number
  canManage: boolean
  canQueryDebt: boolean
  /** Lidos no render do header (não no build) para não recriar colunas a cada seleção. */
  getSelectedClientIds: () => number[]
  getSelectedCount: () => number
  getSelectedAutomaticRequested: () => boolean
  onBulkClear: () => void
  onBulkRefresh: () => void
  onHistory: (row: SimplesMeiClientRow) => void
  onConsult: (row: SimplesMeiClientRow) => void
  onPreview: (row: SimplesMeiClientRow) => void
  onTracking: (row: SimplesMeiClientRow) => void
  onConfigure: (row: SimplesMeiClientRow) => void
  onPreferenceSaved: (
    row: SimplesMeiClientRow,
    preference: PgdasdCommunicationPreference
  ) => void
}): TableColumn<SimplesMeiClientRow>[] {
  const AutomaticSwitch = MonitoringPgmeiAutomaticSwitch
  const BulkAutomaticSwitch = MonitoringPgmeiBulkAutomaticActions

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

  function rowActions(row: SimplesMeiClientRow) {
    return h('div', { class: 'flex items-center gap-0.5' }, [
      iconAction({
        label: 'Abrir prévia de envio',
        icon: 'i-lucide-send',
        color: 'primary',
        testId: 'pgmei-send-preview',
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
            },
            {
              label: 'Consultar dívida ativa',
              icon: 'i-lucide-refresh-cw',
              description: 'Abre a confirmação; nenhuma consulta ocorre pelo menu.',
              disabled: !options.canQueryDebt,
              onSelect: () => options.onConsult(row)
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
          'aria-label': 'Mais ações PGMEI',
          'data-testid': 'pgmei-actions-menu'
        })
      })
    ])
  }

  function trackingCell(row: SimplesMeiClientRow) {
    const summary = pgmeiSummary(row, options.year)
    const meta = pgdasdTrackingMeta(summary?.communication?.tracking_status)
    const artifactHref = documentActionVisible(row.document)
      ? row.document?.href?.trim() || null
      : null

    return h('div', { class: 'flex items-center gap-0.5' }, [
      iconAction({
        label: `Status do envio: ${meta.label}`,
        icon: meta.icon,
        color: meta.color,
        testId: 'pgmei-tracking-status',
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
              'data-testid': 'pgmei-tracking-attachment'
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
              'data-testid': 'pgmei-tracking-attachment'
            })
          }),
      iconAction({
        label: 'Abrir rastreio de envio',
        icon: 'i-lucide-search',
        testId: 'pgmei-tracking',
        onClick: () => options.onTracking(row)
      })
    ])
  }

  return [
    {
      id: 'situation',
      header: 'Situação',
      enableSorting: false,
      meta: { class: { th: 'min-w-48', td: 'min-w-48' } },
      cell: ({ row }) => {
        const summary = pgmeiSummary(row.original, options.year)
        const debt = pgmeiDebtMeta(summary?.debt_state)
        const outdated = summary?.debt_state !== 'UNVERIFIED'
          && Boolean(summary?.last_valid_query_at)
          && pgmeiFreshnessState(summary?.freshness_state) === 'OUTDATED'
        return h(UTooltip, { text: pgmeiDebtTooltip(summary, options.year) }, {
          default: () => h('span', {
            'class': 'inline-flex items-center gap-1.5',
            'aria-label': `Situação PGMEI: ${debt.label}`
          }, [
            h(UBadge, {
              'label': debt.label,
              'color': debt.color,
              'icon': debt.icon,
              'variant': 'subtle',
              'class': 'min-w-24 justify-center',
              'data-testid': 'pgmei-situation'
            }),
            outdated
              ? h(UIcon, {
                  'name': 'i-lucide-clock-alert',
                  'class': 'size-4 text-warning',
                  'aria-label': 'Consulta desatualizada'
                })
              : null
          ])
        })
      }
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
        preference: pgmeiSummary(row.original, options.year)?.communication,
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
        const lastQuery = pgmeiSummary(row.original, options.year)?.last_valid_query_at
        return h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : `Nenhuma consulta produtiva válida para ${options.year}`
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-sm',
            'data-testid': 'pgmei-last-query'
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
        text: `Ver histórico fiscal PGMEI de ${options.year}`
      }, {
        default: () => h(UButton, {
          'size': 'sm',
          'color': 'neutral',
          'variant': 'outline',
          'icon': 'i-lucide-search',
          'block': true,
          'class': 'w-full justify-center',
          'aria-label': `Ver histórico fiscal PGMEI de ${options.year}`,
          'data-testid': 'pgmei-history',
          'onClick': () => options.onHistory(row.original)
        })
      })
    }
  ]
}
