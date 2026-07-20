import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Builders em computed durante o render da UTable perdem o instance da página
 * e as células ficam vazias. @see table-sort.ts / pgdasd-table.ts
 */
import {
  FiscalClientCell,
  FiscalStatusBadge
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UButton from '@nuxt/ui/components/Button.vue'
import UDropdownMenu from '@nuxt/ui/components/DropdownMenu.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type {
  DctfwebClientRow
} from '~/types/fiscal-modules'
import {
  dctfwebDeclarationMeta,
  dctfwebLastDeclarationLabel,
  dctfwebSummary,
  dctfwebTrackingMeta,
  formatDctfwebDate
} from '~/utils/dctfweb'
import { sortHeader } from '~/utils/table-sort'
import { tableIconButton, tableIconGroup } from '~/utils/table-icon-slots'
import { tableCellBadgeProps } from '~/utils/table-ui'
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
 * Renderer DCTFWeb — alinhado ao padrão PGDAS-D:
 * Cliente · Situação · Últ. Declaração · Ações informativas · Histórico local ·
 * Última consulta · Histórico.
 */
export function buildDctfwebColumns(options: {
  onHistory: (row: DctfwebClientRow) => void
  onPreview: (row: DctfwebClientRow) => void
  onTracking: (row: DctfwebClientRow) => void
  onConfigure: (row: DctfwebClientRow) => void
}): TableColumn<DctfwebClientRow>[] {
  function iconAction(args: {
    label: string
    icon: string
    color?: 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'info'
    testId: string
    disabled?: boolean
    onClick: () => void
  }) {
    return h(UTooltip, { text: args.label }, {
      default: () => h(UButton, {
        'size': 'xs',
        'color': args.color || 'neutral',
        'variant': 'ghost',
        'icon': args.icon,
        'ariaLabel': args.label,
        'disabled': args.disabled === true,
        'data-testid': args.testId,
        'onClick': args.disabled ? undefined : args.onClick
      })
    })
  }

  function actionItems(row: DctfwebClientRow): DropdownMenuItem[][] {
    const summary = dctfwebSummary(row)
    const name = row.name || row.legal_name || `cliente ${row.client_id}`
    const items: DropdownMenuItem[] = [
      {
        label: 'Abrir cliente',
        icon: 'i-lucide-building-2',
        to: `/monitoring/clients/${row.client_id}`
      },
      {
        label: 'Preferências registradas',
        icon: 'i-lucide-info',
        onSelect: () => options.onConfigure(row)
      }
    ]
    if (summary?.has_history) {
      items.push({
        label: 'Histórico de busca',
        icon: 'i-lucide-history',
        onSelect: () => options.onHistory(row)
      })
    }
    // Sem mutações fiscais (transmitir / DARF / encerrar).
    return [items, [{
      label: `Documentos locais de ${name}`,
      icon: 'i-lucide-file-text',
      disabled: !summary?.has_history,
      onSelect: () => options.onHistory(row)
    }]]
  }

  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name || row.original.name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'situation',
      header: ({ column }) => sortHeader('Situação', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const state = summary?.declaration_state
        if (!state) {
          return h('div', { class: 'block w-full min-w-0' }, [
            h(UBadge, tableCellBadgeProps({
              'label': '–',
              'color': 'neutral',
              'data-testid': 'dctfweb-situation-empty'
            }))
          ])
        }
        const meta = dctfwebDeclarationMeta(state)
        return h(UTooltip, { text: meta.description }, {
          default: () => h('div', { class: 'block w-full min-w-0' }, [
            h(UBadge, tableCellBadgeProps({
              'label': meta.label,
              'color': meta.color,
              'icon': meta.icon,
              'data-testid': 'dctfweb-situation'
            }))
          ])
        })
      }
    },
    {
      id: 'last_declaration',
      header: 'Últ. Declaração',
      enableSorting: false,
      meta: { class: { th: 'min-w-28', td: 'min-w-28' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const label = dctfwebLastDeclarationLabel(summary)
        if (label === '—') {
          return h('div', { class: 'block w-full min-w-0' }, [
            h(UBadge, tableCellBadgeProps({
              'label': '–',
              'color': 'neutral',
              'data-testid': 'dctfweb-last-declaration-empty'
            }))
          ])
        }
        return h('div', { class: 'block w-full min-w-0' }, [
          h(UBadge, tableCellBadgeProps({
            'label': label,
            'color': 'primary',
            'data-testid': 'dctfweb-last-declaration'
          }))
        ])
      }
    },
    {
      id: 'actions',
      header: MONITORING_ACTIONS_LABEL,
      enableHiding: false,
      enableSorting: false,
      meta: { ...MONITORING_ACTIONS_META },
      cell: ({ row }) => {
        const name = row.original.name || row.original.legal_name || `cliente ${row.original.client_id}`
        return tableIconGroup([
          tableIconButton({
            label: 'Ver destinatários e documentos locais',
            icon: 'i-lucide-message-square-text',
            testId: 'dctfweb-communication-info',
            onClick: () => options.onPreview(row.original)
          }),
          tableIconButton({
            label: 'Ver preferências registradas',
            icon: 'i-lucide-info',
            testId: 'dctfweb-communication-preferences',
            onClick: () => options.onConfigure(row.original)
          }),
          h(UDropdownMenu, {
            items: actionItems(row.original),
            content: { align: 'end' }
          }, () => h(UButton, {
            'icon': 'i-lucide-ellipsis-vertical',
            'color': 'neutral',
            'variant': 'ghost',
            'size': 'sm',
            'square': true,
            'class': 'size-8 justify-center',
            'aria-label': `Mais ações de ${name}`,
            'data-testid': 'dctfweb-row-actions'
          }))
        ], 'dctfweb-actions-group')
      }
    },
    {
      id: 'tracking',
      header: MONITORING_TRACKING_LABEL,
      enableSorting: false,
      meta: { ...MONITORING_TRACKING_META },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const hasTracking = summary?.has_tracking === true
          || Boolean(summary?.communication?.tracking_status
            && summary.communication.tracking_status !== 'NO_HISTORY'
            && summary.communication.tracking_status !== 'NOT_CONFIGURED')
        const tracking = dctfwebTrackingMeta(summary?.communication?.tracking_status)
        return iconAction({
          label: hasTracking ? `Histórico local: ${tracking.label}` : 'Sem histórico local',
          icon: tracking.icon,
          color: hasTracking ? tracking.color : 'neutral',
          testId: 'dctfweb-tracking',
          disabled: !hasTracking && !summary?.communication,
          onClick: () => options.onTracking(row.original)
        })
      }
    },
    {
      id: MONITORING_CONSULTED_ID,
      header: ({ column }) => sortHeader(MONITORING_CONSULTED_LABEL, column),
      meta: { ...MONITORING_CONSULTED_META },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const label = formatDctfwebDate(
          summary?.last_search_at || summary?.last_valid_query_at
        )
        return h('span', {
          'class': 'whitespace-nowrap tabular-nums text-xs text-muted',
          'data-testid': 'dctfweb-last-search'
        }, label)
      }
    },
    {
      id: 'history',
      header: MONITORING_HISTORY_LABEL,
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-16 min-w-14', td: 'w-16 min-w-14' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const hasHistory = summary?.has_history === true
        return iconAction({
          label: hasHistory
            ? 'Abrir histórico de busca local'
            : 'Sem histórico local',
          icon: 'i-lucide-history',
          testId: 'dctfweb-history',
          disabled: !hasHistory,
          onClick: () => options.onHistory(row.original)
        })
      }
    }
  ]
}

/**
 * Renderer MIT independente — não reutiliza colunas DCTFWeb.
 */
export function buildMitColumns(options: {
  onOpenClient: (row: DctfwebClientRow) => void
  onListApuracoes: (row: DctfwebClientRow) => void
}): TableColumn<DctfwebClientRow>[] {
  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-56', td: 'min-w-56' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name || row.original.name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'period',
      header: 'Competência',
      enableSorting: false,
      cell: ({ row }) => String(row.original.detail?.mit?.period_key || row.original.competence || '—')
    },
    {
      id: 'situation',
      header: 'Situação',
      cell: ({ row }) => h(FiscalStatusBadge, { fill: true, status: row.original.detail?.mit?.situation || row.original.situation })
    },
    {
      id: 'closure',
      header: 'Encerramento',
      enableSorting: false,
      cell: ({ row }) => {
        const status = row.original.detail?.mit?.encerramento_status
        if (!status) return '—'
        return h(FiscalStatusBadge, { fill: true, status })
      }
    },
    {
      id: 'lista_apuracoes_317',
      header: 'Apurações 317',
      enableSorting: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => h(UButton, {
        'label': 'Ver locais',
        'icon': 'i-lucide-list-filter',
        'size': 'xs',
        'color': 'neutral',
        'variant': 'ghost',
        'aria-label': 'Ver apurações MIT 317 locais',
        'data-testid': 'mit-lista-apuracoes-317',
        'onClick': () => options.onListApuracoes(row.original)
      })
    }
  ]
}
