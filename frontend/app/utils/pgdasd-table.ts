import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Estes builders rodam em computed durante o render da UTable; getCurrentInstance()
 * não é a página e resolveComponent falha → células vazias com 1 registro no rodapé.
 * @see table-sort.ts (mesmo padrão com #components)
 */
import {
  FiscalClientCell,
  MonitoringPgdasdAutomaticSwitch,
  MonitoringPgdasdBulkAutomaticActions,
  MonitoringPgdasdDeclarationIndicator,
  MonitoringPgdasdRbt12Value,
  UBadge,
  UButton,
  UDropdownMenu,
  UTooltip
} from '#components'
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
  onRegimeHistory: (row: SimplesMeiClientRow) => void
  onRegimeConsult: (row: SimplesMeiClientRow) => void
  canQueryRegime: boolean
  onRegimeOptionHistory: (row: SimplesMeiClientRow) => void
  onRegimeOptionConsult: (row: SimplesMeiClientRow) => void
  canQueryRegimeOption: boolean
  onRegimeResolutionHistory: (row: SimplesMeiClientRow) => void
  onRegimeResolutionConsult: (row: SimplesMeiClientRow) => void
  canQueryRegimeResolution: boolean
  onDefisHistory: (row: SimplesMeiClientRow) => void
  onDefisConsult: (row: SimplesMeiClientRow) => void
  canQueryDefis: boolean
  onDefisLatestHistory: (row: SimplesMeiClientRow) => void
  onDefisLatestConsult: (row: SimplesMeiClientRow) => void
  canQueryDefisLatest: boolean
  onDefisSpecificHistory: (row: SimplesMeiClientRow) => void
  onPreview: (row: SimplesMeiClientRow) => void
  onTracking: (row: SimplesMeiClientRow) => void
  onConfigure: (row: SimplesMeiClientRow) => void
  onPreferenceSaved: (
    row: SimplesMeiClientRow,
    preference: PgdasdCommunicationPreference
  ) => void
}): TableColumn<SimplesMeiClientRow>[] {
  const DeclarationIndicator = MonitoringPgdasdDeclarationIndicator
  const Rbt12Value = MonitoringPgdasdRbt12Value
  const AutomaticSwitch = MonitoringPgdasdAutomaticSwitch
  const BulkAutomaticSwitch = MonitoringPgdasdBulkAutomaticActions

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
              label: 'Histórico de regimes',
              icon: 'i-lucide-calendar-range',
              onSelect: () => options.onRegimeHistory(row)
            },
            {
              label: 'Atualizar regimes',
              icon: 'i-lucide-refresh-cw',
              disabled: !options.canQueryRegime,
              onSelect: () => options.onRegimeConsult(row)
            },
            {
              label: 'Opção anual de regime',
              icon: 'i-lucide-calendar-check-2',
              onSelect: () => options.onRegimeOptionHistory(row)
            },
            {
              label: 'Atualizar opção anual (ano atual)',
              icon: 'i-lucide-calendar-sync',
              disabled: !options.canQueryRegimeOption,
              onSelect: () => options.onRegimeOptionConsult(row)
            },
            {
              label: 'Resoluções do Regime de Caixa',
              icon: 'i-lucide-file-text',
              onSelect: () => options.onRegimeResolutionHistory(row)
            },
            {
              label: 'Atualizar resolução (ano atual)',
              icon: 'i-lucide-file-down',
              disabled: !options.canQueryRegimeResolution,
              onSelect: () => options.onRegimeResolutionConsult(row)
            },
            {
              label: 'Declarações DEFIS',
              icon: 'i-lucide-files',
              onSelect: () => options.onDefisHistory(row)
            },
            {
              label: 'Atualizar declarações DEFIS',
              icon: 'i-lucide-refresh-cw',
              disabled: !options.canQueryDefis,
              onSelect: () => options.onDefisConsult(row)
            },
            {
              label: 'Última DEFIS e recibo',
              icon: 'i-lucide-file-check-2',
              onSelect: () => options.onDefisLatestHistory(row)
            },
            {
              label: 'Atualizar última DEFIS (ano atual)',
              icon: 'i-lucide-file-down',
              disabled: !options.canQueryDefisLatest,
              onSelect: () => options.onDefisLatestConsult(row)
            },
            {
              label: 'Declaração DEFIS e recibo',
              icon: 'i-lucide-files',
              onSelect: () => options.onDefisSpecificHistory(row)
            },
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
    const documents = (summary?.documents || []).filter(document =>
      Boolean(document.download_path?.trim())
    )
    const artifactHref = documentActionVisible(row.document)
      ? row.document?.href?.trim() || null
      : null

    const documentLabel = (kind?: string | null): string => {
      switch (String(kind || '').toUpperCase()) {
        case 'DECLARACAO': return 'Declaração'
        case 'RECIBO': return 'Recibo'
        case 'NOTIFICACAO_MAED': return 'Notificação MAED'
        case 'DARF_MAED': return 'DARF MAED'
        case 'EXTRATO': return 'Extrato'
        default: return 'Documento PGDAS-D'
      }
    }

    return h('div', { class: 'flex items-center gap-0.5' }, [
      iconAction({
        label: `Status do envio: ${meta.label}`,
        icon: meta.icon,
        color: meta.color,
        testId: 'pgdasd-tracking-status',
        onClick: () => options.onTracking(row)
      }),
      documents.length
        ? h(UDropdownMenu, {
            items: [documents.map(document => ({
              label: documentLabel(document.kind),
              icon: 'i-lucide-download',
              to: document.download_path || undefined,
              external: true,
              target: '_blank'
            }))] as DropdownMenuItem[][],
            content: { align: 'start' }
          }, {
            default: () => h(UButton, {
              'size': 'sm',
              'color': 'primary',
              'variant': 'ghost',
              'icon': 'i-lucide-files',
              'label': `${documents.length} documento${documents.length === 1 ? '' : 's'}`,
              'aria-label': `${documents.length} documento(s) PGDAS-D disponível(is) para download`,
              'data-testid': 'pgdasd-artifacts-menu'
            })
          })
        : artifactHref
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
            'class': 'whitespace-nowrap tabular-nums text-sm',
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
