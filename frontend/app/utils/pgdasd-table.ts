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
  MonitoringPgdasdRbt12Value
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UButton from '@nuxt/ui/components/Button.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type {
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { documentActionVisible } from '~/types/fiscal-modules'
import { resolveApiUrl } from '~/utils/api-url'
import { formatDate, formatDateTime } from '~/utils/format'
import {
  pgdasdDeclarationMeta,
  pgdasdDeclarationPeriod,
  pgdasdSummary,
  pgdasdTrackingMeta
} from '~/utils/pgdasd'
import { tableIconButton, tableIconGroup, tableIconMenu } from '~/utils/table-icon-slots'
import { sortHeader } from '~/utils/table-sort'
import { tableCellBadgeProps } from '~/utils/table-ui'

/** Handlers fiscais ficam na toolbar (SelectionActions); a linha só mantém atalho de prévia. */

/**
 * Renderer PGDAS-D — nove colunas de negócio (densidade compacta):
 * Cliente · Situação · Últ. Declaração · Sublimite (RBT12) · Ações · Enviar ·
 * Rastreio de envio · Última Busca · Histórico de Busca.
 * Seleção é acrescentada pelo shell autorizado antes de Cliente.
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
  const DeclarationIndicator = MonitoringPgdasdDeclarationIndicator
  const Rbt12Value = MonitoringPgdasdRbt12Value
  const AutomaticSwitch = MonitoringPgdasdAutomaticSwitch
  const BulkAutomaticSwitch = MonitoringPgdasdBulkAutomaticActions

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
    // Menu completo (regimes/DEFIS) vive na toolbar ao selecionar a linha.
    return tableIconGroup([
      tableIconButton({
        label: 'Abrir prévia de envio',
        icon: 'i-lucide-send',
        color: 'primary',
        testId: 'pgdasd-send-preview',
        onClick: () => options.onPreview(row)
      })
    ], 'pgdasd-actions-group')
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

    const artifactsSlot = documents.length
      ? tableIconMenu({
          label: `${documents.length} documento(s) PGDAS-D disponível(is)`,
          icon: 'i-lucide-files',
          color: 'primary',
          testId: 'pgdasd-artifacts-menu',
          items: [documents.map(document => ({
            label: documentLabel(document.kind),
            icon: 'i-lucide-download',
            to: document.download_path
              ? resolveApiUrl(
                  document.download_path,
                  String(useRuntimeConfig().public.apiBase || '')
                )
              : undefined,
            external: true,
            target: '_blank'
          }))] as DropdownMenuItem[][]
        })
      : tableIconButton({
          label: artifactHref
            ? (row.document?.label || 'Baixar anexo local')
            : 'Nenhum anexo local disponível',
          icon: 'i-lucide-download',
          color: artifactHref ? 'primary' : 'neutral',
          testId: 'pgdasd-tracking-attachment',
          href: artifactHref,
          disabled: !artifactHref
        })

    return tableIconGroup([
      tableIconButton({
        label: `Status do envio: ${meta.label}`,
        icon: meta.icon,
        color: meta.color,
        testId: 'pgdasd-tracking-status',
        onClick: () => options.onTracking(row)
      }),
      artifactsSlot,
      tableIconButton({
        label: 'Abrir rastreio de envio',
        icon: 'i-lucide-search',
        testId: 'pgdasd-tracking',
        onClick: () => options.onTracking(row)
      })
    ], 'pgdasd-tracking-group')
  }

  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-[22%]', td: 'min-w-48 w-[22%]' } },
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
        text: 'Receita bruta acumulada nos 12 meses anteriores ao período de apuração; não é o sublimite anual legal.'
      }, {
        default: () => h('span', { class: 'inline-flex items-center gap-1' }, [
          'Sublimite (RBT12)',
          h('span', { class: 'sr-only' }, ' — receita bruta acumulada em doze meses')
        ])
      }),
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => h(Rbt12Value, {
        rbt12: pgdasdSummary(row.original)?.rbt12
      })
    },
    {
      id: 'actions',
      header: 'Ações',
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => rowActions(row.original)
    },
    {
      id: 'send',
      header: () => h('div', { class: 'flex items-center gap-1' }, [
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
      meta: { class: { th: 'w-16 min-w-14', td: 'w-16 min-w-14' } },
      cell: ({ row }) => h('div', {
        'class': 'inline-flex h-8 w-10 shrink-0 items-center justify-center',
        'data-testid': 'pgdasd-send-slot'
      }, [
        h(AutomaticSwitch, {
          clientId: row.original.client_id,
          preference: pgdasdSummary(row.original)?.communication,
          canManage: options.canManage,
          onConfigure: () => options.onConfigure(row.original),
          onSaved: (preference: PgdasdCommunicationPreference) =>
            options.onPreferenceSaved(row.original, preference)
        })
      ])
    },
    {
      id: 'tracking',
      header: 'Rastreio de envio',
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-28', td: 'w-28 min-w-28' } },
      cell: ({ row }) => trackingCell(row.original)
    },
    {
      id: 'consulted',
      header: ({ column }) => sortHeader('Última Busca', column),
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => {
        const lastQuery = pgdasdSummary(row.original)?.last_valid_query_at
        return h(UTooltip, {
          text: lastQuery
            ? `Última consulta válida: ${formatDateTime(lastQuery)}`
            : 'Nenhuma consulta produtiva válida'
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-xs',
            'data-testid': 'pgdasd-last-query'
          }, formatDate(lastQuery))
        })
      }
    },
    {
      id: 'history',
      header: 'Histórico de Busca',
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
