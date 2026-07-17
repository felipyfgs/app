import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type {
  DctfwebClientRow,
  PgdasdCommunicationPreference
} from '~/types/fiscal-modules'
import {
  dctfwebDeclarationMeta,
  dctfwebLastDeclarationLabel,
  dctfwebSummary,
  dctfwebTrackingMeta,
  formatDctfwebDate
} from '~/utils/dctfweb'
import { sortHeader } from '~/utils/table-sort'

/**
 * Renderer exclusivo da cápsula DCTFWeb.
 * Oito colunas fixas, nesta ordem: Situação, Últ. Declaração, Ações, Enviar,
 * Cliente, Rastreio de envio, Última Busca e Histórico de Busca.
 * Sem seleção e sem colunas adicionais.
 */
export function buildDctfwebColumns(options: {
  canManage: boolean
  onHistory: (row: DctfwebClientRow) => void
  onPreview: (row: DctfwebClientRow) => void
  onTracking: (row: DctfwebClientRow) => void
  onConfigure: (row: DctfwebClientRow) => void
  onPreferenceSaved: (
    row: DctfwebClientRow,
    preference: PgdasdCommunicationPreference
  ) => void
}): TableColumn<DctfwebClientRow>[] {
  const UBadge = resolveComponent('UBadge')
  const UButton = resolveComponent('UButton')
  const UDropdownMenu = resolveComponent('UDropdownMenu')
  const USwitch = resolveComponent('USwitch')
  const UTooltip = resolveComponent('UTooltip')
  const FiscalClientCell = resolveComponent('FiscalClientCell')

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
        'size': 'sm',
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
        label: 'Preferências de envio',
        icon: 'i-lucide-settings-2',
        disabled: !options.canManage,
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
    return [items.map(item => ({
      ...item,
      // manter aria implícito pelo label
    })), [{
      label: `Documentos locais de ${name}`,
      icon: 'i-lucide-file-text',
      disabled: !summary?.has_history,
      onSelect: () => options.onHistory(row)
    }]]
  }

  return [
    {
      id: 'situation',
      header: ({ column }) => sortHeader('Situação', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-36', td: 'min-w-36' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const state = summary?.declaration_state
        if (!state) {
          return h(UBadge, {
            'label': '–',
            'color': 'neutral',
            'variant': 'subtle',
            'class': 'rounded-sm',
            'data-testid': 'dctfweb-situation-empty'
          })
        }
        const meta = dctfwebDeclarationMeta(state)
        return h(UTooltip, { text: meta.description }, {
          default: () => h(UBadge, {
            'label': meta.label,
            'color': meta.color,
            'variant': 'subtle',
            'icon': meta.icon,
            'class': 'rounded-sm',
            'data-testid': 'dctfweb-situation'
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
        const summary = dctfwebSummary(row.original)
        const label = dctfwebLastDeclarationLabel(summary)
        if (label === '—') {
          return h(UBadge, {
            'label': '–',
            'color': 'neutral',
            'variant': 'subtle',
            'class': 'rounded-sm',
            'data-testid': 'dctfweb-last-declaration-empty'
          })
        }
        return h(UBadge, {
          'label': label,
          'color': 'primary',
          'variant': 'subtle',
          'class': 'rounded-sm tabular-nums',
          'data-testid': 'dctfweb-last-declaration'
        })
      }
    },
    {
      id: 'actions',
      header: 'Ações',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-16 min-w-16', td: 'w-16 min-w-16' } },
      cell: ({ row }) => {
        const name = row.original.name || row.original.legal_name || `cliente ${row.original.client_id}`
        return h(UDropdownMenu, {
          items: actionItems(row.original),
          content: { align: 'start' }
        }, () => h(UButton, {
          'icon': 'i-lucide-ellipsis-vertical',
          'color': 'neutral',
          'variant': 'ghost',
          'size': 'sm',
          'aria-label': `Ações de ${name}`,
          'data-testid': 'dctfweb-row-actions'
        }))
      }
    },
    {
      id: 'send',
      header: 'Enviar',
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const preference = summary?.communication
        // Switch Enviar: intenção template; VIEWER desabilitado.
        return h(UTooltip, {
          text: options.canManage
            ? 'Registra intenção de envio (template). Nenhum envio real.'
            : 'Somente ADMIN ou OPERATOR pode alterar.'
        }, {
          default: () => h(USwitch, {
            'modelValue': preference?.automatic_requested === true,
            'disabled': !options.canManage,
            'size': 'sm',
            'ariaLabel': preference?.automatic_requested
              ? 'Desativar envio automático'
              : 'Ativar envio automático',
            'data-testid': 'dctfweb-send-switch',
            'onUpdate:modelValue': (value: boolean) => {
              if (!options.canManage) return
              if (value && !preference) {
                options.onConfigure(row.original)
                return
              }
              if (!preference) {
                options.onConfigure(row.original)
                return
              }
              // Abre preferências para confirmar canais / lock_version.
              options.onConfigure(row.original)
            }
          })
        })
      }
    },
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
        // Duas linhas: razão social + CNPJ mascarado (padrão do FiscalClientCell).
        to: `/monitoring/clients/${row.original.client_id}`
      })
    },
    {
      id: 'tracking',
      header: 'Rastreio de envio',
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-28', td: 'w-28 min-w-28' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const hasTracking = summary?.has_tracking === true
          || Boolean(summary?.communication?.tracking_status
            && summary.communication.tracking_status !== 'NO_HISTORY'
            && summary.communication.tracking_status !== 'NOT_CONFIGURED')
        const tracking = dctfwebTrackingMeta(summary?.communication?.tracking_status)
        return iconAction({
          label: hasTracking ? `Rastreio: ${tracking.label}` : 'Sem rastreio local',
          icon: tracking.icon,
          color: hasTracking ? tracking.color : 'neutral',
          testId: 'dctfweb-tracking',
          disabled: !hasTracking && !summary?.communication,
          onClick: () => options.onTracking(row.original)
        })
      }
    },
    {
      id: 'last_search',
      header: 'Última Busca',
      enableSorting: false,
      meta: { class: { th: 'min-w-28', td: 'min-w-28' } },
      cell: ({ row }) => {
        const summary = dctfwebSummary(row.original)
        const label = formatDctfwebDate(
          summary?.last_search_at || summary?.last_valid_query_at
        )
        return h('span', {
          'class': 'tabular-nums text-sm text-muted',
          'data-testid': 'dctfweb-last-search'
        }, label)
      }
    },
    {
      id: 'history',
      header: 'Histórico de Busca',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-28', td: 'w-28 min-w-28' } },
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
}): TableColumn<DctfwebClientRow>[] {
  const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
  const FiscalClientCell = resolveComponent('FiscalClientCell')

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
      cell: ({ row }) => h(FiscalStatusBadge, {
        status: row.original.detail?.mit?.situation || row.original.situation
      })
    },
    {
      id: 'closure',
      header: 'Encerramento',
      enableSorting: false,
      cell: ({ row }) => {
        const status = row.original.detail?.mit?.encerramento_status
        if (!status) return '—'
        return h(FiscalStatusBadge, { status, showHint: true })
      }
    }
  ]
}
