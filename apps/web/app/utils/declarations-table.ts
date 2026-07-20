import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
import {
  FiscalClientCell,
  FiscalStatusBadge,
  MonitoringPgdasdDeclarationIndicator
} from '#components'
import UButton from '@nuxt/ui/components/Button.vue'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type { DeclarationsClientRow } from '~/types/fiscal-modules'
import { formatDateTime } from '~/utils/format'
import { pgdasdDeclarationMeta, formatPgdasdPeriod } from '~/utils/pgdasd'
import { sortHeader } from '~/utils/table-sort'
import { tableCellBadgeProps } from '~/utils/table-ui'
import {
  MONITORING_SHARED_COLUMN_LABELS
} from '~/utils/monitoring-table-columns'

function detailOf(row: DeclarationsClientRow) {
  return row.detail || {}
}

function clientHref(id: number) {
  return `/monitoring/clients/${id}/declarations`
}

/**
 * Colunas PGDAS do hub Declarações (fidelidade MonitorHub):
 * Situação da declaração · Últ. Declaração · Cliente · Última Busca · Histórico de Busca
 */
export function buildDeclarationsPgdasColumns(options: {
  onHistory: (row: DeclarationsClientRow) => void
}): TableColumn<DeclarationsClientRow>[] {
  const DeclarationIndicator = MonitoringPgdasdDeclarationIndicator

  return [
    {
      id: 'situation',
      header: 'Situação da declaração',
      enableSorting: false,
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } },
      cell: ({ row }) => {
        const d = detailOf(row.original)
        const state = d.declaration_state || d.pgdasd?.declaration_state
        if (state) {
          const meta = pgdasdDeclarationMeta(String(state))
          return h(UTooltip, {
            text: d.declaration_state_reason || d.pgdasd?.declaration_state_reason || meta.description
          }, {
            default: () => h('div', { class: 'block w-full min-w-0' }, [
              h(UBadge, tableCellBadgeProps({
                'label': meta.label,
                'color': meta.color,
                'icon': meta.icon,
                'aria-label': `Situação da declaração: ${meta.label}`,
                'data-testid': 'declarations-pgdas-situation'
              }))
            ])
          })
        }
        return h(FiscalStatusBadge, {
          fill: true,
          status: String(d.next_situation || row.original.situation || 'UNKNOWN')
        })
      }
    },
    {
      id: 'last_declaration',
      header: 'Últ. Declaração',
      enableSorting: false,
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => {
        const d = detailOf(row.original)
        const last = d.last_declaration || d.pgdasd?.latest_declaration
        const period = formatPgdasdPeriod(last?.period_key || d.next_period_key)
        const state = d.declaration_state || d.pgdasd?.declaration_state
        return h(DeclarationIndicator, {
          period: period === '—' ? null : period,
          state,
          reason: d.declaration_state_reason || d.pgdasd?.declaration_state_reason
        })
      }
    },
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.name || row.original.display_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: clientHref(row.original.client_id)
      })
    },
    {
      id: 'last_search',
      header: 'Última Busca',
      enableSorting: false,
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } },
      cell: ({ row }) => {
        const d = detailOf(row.original)
        const at = d.last_valid_query_at
          || d.pgdasd?.last_valid_query_at
          || row.original.last_consulted_at
          || row.original.last_snapshot_at
        return h('span', {
          'class': 'text-xs whitespace-nowrap',
          'data-testid': 'declarations-pgdas-last-search'
        }, formatDateTime(at || null))
      }
    },
    {
      id: 'history',
      header: 'Histórico de Busca',
      enableSorting: false,
      enableHiding: false,
      meta: { class: { th: 'w-28', td: 'w-28' } },
      cell: ({ row }) => h(UButton, {
        'size': 'xs',
        'color': 'primary',
        'variant': 'ghost',
        'icon': 'i-lucide-history',
        'label': 'Histórico',
        'aria-label': `Histórico de busca do cliente ${row.original.client_id}`,
        'data-testid': 'declarations-pgdas-history',
        'onClick': () => options.onHistory(row.original)
      })
    }
  ]
}

/** Colunas genéricas filtradas por obrigação (DCTFWeb / DEFIS). */
export function buildDeclarationsObligationColumns(options: {
  onHistory?: (row: DeclarationsClientRow) => void
  historyLabel?: string
}): TableColumn<DeclarationsClientRow>[] {
  const columns: TableColumn<DeclarationsClientRow>[] = [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.name || row.original.display_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: clientHref(row.original.client_id)
      })
    },
    {
      id: 'obligation',
      header: 'Obrigação',
      enableSorting: false,
      cell: ({ row }) => String(detailOf(row.original).next_obligation_code || '—')
    },
    {
      id: 'competence',
      header: ({ column }) => sortHeader('Competência', column),
      cell: ({ row }) => String(
        row.original.competence
        || detailOf(row.original).next_period_key
        || '—'
      )
    },
    {
      id: 'situation',
      header: ({ column }) => sortHeader('Situação', column),
      cell: ({ row }) => h(FiscalStatusBadge, {
        fill: true,
        status: String(
          detailOf(row.original).next_situation || row.original.situation
        )
      })
    },
    {
      id: 'consulted',
      header: MONITORING_SHARED_COLUMN_LABELS.consulted || 'Última consulta',
      enableSorting: false,
      cell: ({ row }) => formatDateTime(
        row.original.last_consulted_at || row.original.last_snapshot_at || null
      )
    }
  ]

  if (options.onHistory) {
    columns.push({
      id: 'history',
      header: options.historyLabel || 'Histórico',
      enableSorting: false,
      enableHiding: false,
      meta: { class: { th: 'w-28', td: 'w-28' } },
      cell: ({ row }) => h(UButton, {
        'size': 'xs',
        'color': 'primary',
        'variant': 'ghost',
        'icon': 'i-lucide-history',
        'label': 'Histórico',
        'aria-label': `Histórico do cliente ${row.original.client_id}`,
        'data-testid': 'declarations-obligation-history',
        'onClick': () => options.onHistory?.(row.original)
      })
    })
  }

  return columns
}

/** Colunas FGTS parciais (sem inventar guia/pagamento). */
export function buildDeclarationsFgtsColumns(): TableColumn<DeclarationsClientRow>[] {
  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.name || row.original.display_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}/fgts`
      })
    },
    {
      id: 'competence',
      header: 'Competência',
      enableSorting: false,
      cell: ({ row }) => String(
        detailOf(row.original).next_period_key
        || detailOf(row.original).fgts?.competence_period_key
        || row.original.competence
        || '—'
      )
    },
    {
      id: 'closure',
      header: 'Fechamento',
      enableSorting: false,
      cell: ({ row }) => String(detailOf(row.original).fgts?.closure_status || '—')
    },
    {
      id: 'totalization',
      header: 'Totalização',
      enableSorting: false,
      cell: ({ row }) => String(detailOf(row.original).fgts?.totalization_status || '—')
    },
    {
      id: 'coverage',
      header: 'Cobertura',
      enableSorting: false,
      cell: ({ row }) => h(FiscalStatusBadge, {
        fill: true,
        status: String(
          detailOf(row.original).fgts?.coverage
          || row.original.coverage
          || 'PARTIAL'
        )
      })
    },
    {
      id: 'situation',
      header: ({ column }) => sortHeader('Situação', column),
      cell: ({ row }) => h(FiscalStatusBadge, {
        fill: true,
        status: String(row.original.situation || 'UNKNOWN')
      })
    }
  ]
}

export const DECLARATIONS_PGDAS_COLUMN_LABELS = {
  situation: 'Situação da declaração',
  last_declaration: 'Últ. Declaração',
  last_search: 'Última Busca',
  history: 'Histórico de Busca',
  client: 'Cliente'
} as const
