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
  MonitoringPgmeiBulkAutomaticActions
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UButton from '@nuxt/ui/components/Button.vue'
import UIcon from '@nuxt/ui/components/Icon.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
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
  MONITORING_TRACKING_LABEL,
  MONITORING_TRACKING_META
} from '~/utils/monitoring-table-columns'

/**
 * Renderer PGMEI — colunas de negócio (densidade compacta):
 * Cliente · Situação · Ações (prévia+auto) · Rastreio de envio · Última consulta ·
 * Histórico. Sem colunas mensais do PGDAS-D.
 * Seleção é acrescentada pelo shell autorizado antes de Cliente.
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

  function rowActions(row: SimplesMeiClientRow) {
    // Prévia + automático na mesma coluna; regimes/bulk ficam na toolbar.
    return tableIconGroup([
      tableIconButton({
        label: 'Abrir prévia de envio',
        icon: 'i-lucide-send',
        color: 'primary',
        testId: 'pgmei-send-preview',
        onClick: () => options.onPreview(row)
      }),
      h('div', {
        'class': 'inline-flex size-8 items-center justify-center',
        'data-testid': 'pgmei-send-slot'
      }, [
        h(AutomaticSwitch, {
          clientId: row.client_id,
          preference: pgmeiSummary(row, options.year)?.communication,
          canManage: options.canManage,
          onConfigure: () => options.onConfigure(row),
          onSaved: (preference: PgdasdCommunicationPreference) =>
            options.onPreferenceSaved(row, preference)
        })
      ])
    ], 'pgmei-actions-group')
  }

  function trackingCell(row: SimplesMeiClientRow) {
    const summary = pgmeiSummary(row, options.year)
    const meta = pgdasdTrackingMeta(summary?.communication?.tracking_status)
    const artifactHref = documentActionVisible(row.document)
      ? row.document?.href?.trim() || null
      : null

    return tableIconGroup([
      tableIconButton({
        label: `Status do envio: ${meta.label}`,
        icon: meta.icon,
        color: meta.color,
        testId: 'pgmei-tracking-status',
        onClick: () => options.onTracking(row)
      }),
      tableIconButton({
        label: artifactHref
          ? (row.document?.label || 'Baixar anexo local')
          : 'Nenhum anexo local disponível',
        icon: 'i-lucide-download',
        color: artifactHref ? 'primary' : 'neutral',
        testId: 'pgmei-tracking-attachment',
        href: artifactHref,
        disabled: !artifactHref
      }),
      tableIconButton({
        label: 'Abrir rastreio de envio',
        icon: 'i-lucide-search',
        testId: 'pgmei-tracking',
        onClick: () => options.onTracking(row)
      })
    ], 'pgmei-tracking-group')
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
      header: () => h('div', { class: 'flex items-center gap-1' }, [
        h('span', MONITORING_ACTIONS_LABEL),
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
      meta: { ...MONITORING_ACTIONS_META },
      cell: ({ row }) => rowActions(row.original)
    },
    {
      id: 'tracking',
      header: MONITORING_TRACKING_LABEL,
      enableSorting: false,
      meta: { ...MONITORING_TRACKING_META },
      cell: ({ row }) => trackingCell(row.original)
    },
    {
      id: MONITORING_CONSULTED_ID,
      header: ({ column }) => sortHeader(MONITORING_CONSULTED_LABEL, column),
      meta: { ...MONITORING_CONSULTED_META },
      cell: ({ row }) => {
        const lastQuery = pgmeiSummary(row.original, options.year)?.last_valid_query_at
        return h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : `Nenhuma consulta produtiva válida para ${options.year}`
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-xs',
            'data-testid': 'pgmei-last-query'
          }, formatDate(lastQuery))
        })
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
