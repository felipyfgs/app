import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
/**
 * Imports estáticos — NÃO usar resolveComponent aqui.
 * Builders em computed durante o render da UTable perdem o instance da página
 * e as células ficam vazias. @see table-sort.ts / pgdasd-table.ts
 */
import {
  ClientsClientProcuracaoBadge,
  FiscalClientCell,
  FiscalCoverageBadge,
  FiscalStatusBadge,
  MonitoringCommercialMetaCell
} from '#components'
import UBadge from '@nuxt/ui/components/Badge.vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import type { SitfisClientDetail, SitfisClientRow } from '~/types/fiscal-modules'
import { documentActionVisible } from '~/types/fiscal-modules'
import { formatDate, formatDateTime } from '~/utils/format'
import { tableIconButton, tableIconGroup } from '~/utils/table-icon-slots'
import { sortHeader } from '~/utils/table-sort'

/**
 * Renderer SITFIS — alinhado à densidade Simples/MEI:
 * Cliente · Situação · Achados · Cobertura · Ações ·
 * Procuração · Franquia / agenda · Idade / TTL · Observado.
 * Secundárias (procuração/franquia/idade/observado) começam ocultas via Exibir.
 * Seleção é acrescentada pelo shell autorizado antes de Cliente.
 */
export function sitfisDetailOf(row: SitfisClientRow): SitfisClientDetail {
  return row.detail || {}
}

export function sitfisAgeLabel(seconds?: number | null) {
  if (seconds == null || !Number.isFinite(Number(seconds))) return '—'
  const s = Number(seconds)
  if (s < 60) return `${s}s`
  if (s < 3600) return `${Math.floor(s / 60)} min`
  if (s < 86400) return `${Math.floor(s / 3600)} h`
  return `${Math.floor(s / 86400)} d`
}

export function buildSitfisColumns(options: {
  allowsDocument: boolean
  onFindings: (row: SitfisClientRow) => void
}): TableColumn<SitfisClientRow>[] {
  function rowActions(row: SitfisClientRow) {
    const href = documentActionVisible(row.document)
      ? row.document?.href?.trim() || null
      : null
    const docEnabled = options.allowsDocument && Boolean(href)

    return tableIconGroup([
      tableIconButton({
        label: docEnabled
          ? (row.document?.label || 'Baixar documento oficial')
          : 'Nenhum documento oficial disponível',
        icon: 'i-lucide-file-down',
        color: docEnabled ? 'primary' : 'neutral',
        testId: 'sitfis-document-action',
        href: docEnabled ? href : null,
        disabled: !docEnabled
      }),
      tableIconButton({
        label: 'Ver achados e pendências',
        icon: 'i-lucide-list-checks',
        color: 'primary',
        testId: 'sitfis-findings',
        onClick: () => options.onFindings(row)
      })
    ], 'sitfis-actions-group')
  }

  return [
    {
      id: 'client',
      header: ({ column }) => sortHeader('Cliente', column),
      enableHiding: false,
      meta: { class: { th: 'min-w-48 w-[26%]', td: 'min-w-48 w-[26%]' } },
      cell: ({ row }) => h(FiscalClientCell, {
        clientId: row.original.client_id,
        name: row.original.legal_name || row.original.name || row.original.display_name,
        legalName: row.original.legal_name,
        cnpjMasked: row.original.cnpj_masked,
        to: `/monitoring/clients/${row.original.client_id}/sitfis`
      })
    },
    {
      id: 'situation',
      header: ({ column }) => sortHeader('Situação', column),
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => h(FiscalStatusBadge, { fill: true, status: row.original.situation })
    },
    {
      id: 'findings',
      header: 'Achados',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-20', td: 'w-24 min-w-20' } },
      cell: ({ row }) => {
        const d = sitfisDetailOf(row.original)
        const findings = d.findings_count ?? 0
        const pending = d.pending_count ?? 0
        return h(UTooltip, {
          text: `${findings} achados · ${pending} pendências`
        }, {
          default: () => h('span', {
            'class': 'text-xs tabular-nums whitespace-nowrap',
            'data-testid': 'sitfis-findings-count'
          }, `${findings} · ${pending} pend.`)
        })
      }
    },
    {
      id: 'coverage',
      header: 'Cobertura',
      enableSorting: false,
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } },
      cell: ({ row }) => h(FiscalCoverageBadge, { fill: true, coverage: row.original.coverage })
    },
    {
      id: 'actions',
      header: 'Ações',
      enableHiding: false,
      enableSorting: false,
      meta: { class: { th: 'w-20 min-w-20', td: 'w-20 min-w-20' } },
      cell: ({ row }) => rowActions(row.original)
    },
    {
      id: 'procuracao',
      header: 'Procuração',
      enableSorting: false,
      meta: { class: { th: 'w-16 min-w-14', td: 'w-16 min-w-14' } },
      cell: ({ row }) => h(ClientsClientProcuracaoBadge, {
        status: row.original.procuracao_status,
        showHint: false,
        compact: true
      })
    },
    {
      id: 'franchise',
      header: 'Franquia / agenda',
      enableSorting: false,
      meta: { class: { th: 'w-40 min-w-36', td: 'w-40 min-w-36' } },
      cell: ({ row }) => h(MonitoringCommercialMetaCell, {
        remaining: row.original.commercial_quota?.remaining,
        limit: row.original.commercial_quota?.limit,
        used: row.original.commercial_quota?.used,
        blockReason: row.original.block_reason || row.original.commercial_quota?.block_reason,
        blockMessage: row.original.block_message,
        lastSnapshotAt: row.original.last_snapshot_at || row.original.last_consulted_at,
        nextScheduledAt: row.original.next_scheduled_at,
        isRecent: row.original.is_recent_snapshot
      })
    },
    {
      id: 'age',
      header: 'Idade / TTL',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-20', td: 'w-24 min-w-20' } },
      cell: ({ row }) => {
        const d = sitfisDetailOf(row.original)
        const age = sitfisAgeLabel(d.age_seconds)
        const ttl = d.ttl_seconds != null ? sitfisAgeLabel(d.ttl_seconds) : '—'
        return h('span', { class: 'inline-flex items-center gap-1 text-xs tabular-nums' }, [
          age,
          h('span', { class: 'text-muted' }, `/ ${ttl}`),
          d.is_expired === true
            ? h(UBadge, { color: 'warning', variant: 'subtle', size: 'xs' }, () => 'Expirado')
            : null
        ])
      }
    },
    {
      id: 'observed',
      header: ({ column }) => sortHeader('Observado', column),
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } },
      cell: ({ row }) => {
        const raw = String(
          sitfisDetailOf(row.original).observed_at
          || row.original.last_snapshot_at
          || row.original.last_consulted_at
          || ''
        ) || null
        return h(UTooltip, {
          text: raw
            ? `Observado: ${formatDateTime(raw)}`
            : 'Sem observação registrada'
        }, {
          default: () => h('span', {
            'class': 'whitespace-nowrap tabular-nums text-xs',
            'data-testid': 'sitfis-observed'
          }, formatDate(raw))
        })
      }
    }
  ]
}
