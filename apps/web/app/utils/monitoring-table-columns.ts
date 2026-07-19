/**
 * Contrato transversal das colunas de carteira monitoring.
 *
 * Ordem canônica (quando o domínio tem comunicação):
 *   Cliente · Situação · [domínio] · Ações (prévia+envio) · Rastreio · Última consulta · Histórico
 *
 * Módulos sem pipeline de comunicação (FGTS, declarações, …) usam só
 * `consulted` com o mesmo id/label — sem inventar switch/rastreio vazio.
 */
import type { TableColumn } from '@nuxt/ui'
import { h } from 'vue'
import UTooltip from '@nuxt/ui/components/Tooltip.vue'
import { formatDate, formatDateTime } from '~/utils/format'
import { sortHeader } from '~/utils/table-sort'

export const MONITORING_CONSULTED_ID = 'consulted'
/** Cabeçalho curto (tabela); tooltip/célula mantêm o sentido completo. */
export const MONITORING_CONSULTED_LABEL = 'Consulta'
export const MONITORING_TRACKING_ID = 'tracking'
export const MONITORING_TRACKING_LABEL = 'Rastreio de envio'
export const MONITORING_ACTIONS_ID = 'actions'
export const MONITORING_ACTIONS_LABEL = 'Ações'
export const MONITORING_HISTORY_ID = 'history'
export const MONITORING_HISTORY_LABEL = 'Histórico'

/**
 * Auto-ajuste em `table-fixed`: `w-0` + nowrap encolhe ao rótulo/data
 * (evita a coluna «Última consulta» absorver faixa vazia).
 */
export const MONITORING_CONSULTED_META = {
  class: { th: 'w-0 whitespace-nowrap', td: 'w-0 whitespace-nowrap' }
} as const

export const MONITORING_TRACKING_META = {
  class: { th: 'w-28 min-w-28', td: 'w-28 min-w-28' }
} as const

export const MONITORING_ACTIONS_META = {
  class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' }
} as const

/** Labels padrão para `column-labels` / mobile cards. */
export const MONITORING_SHARED_COLUMN_LABELS: Record<string, string> = {
  [MONITORING_ACTIONS_ID]: MONITORING_ACTIONS_LABEL,
  [MONITORING_TRACKING_ID]: MONITORING_TRACKING_LABEL,
  [MONITORING_CONSULTED_ID]: MONITORING_CONSULTED_LABEL,
  [MONITORING_HISTORY_ID]: MONITORING_HISTORY_LABEL
}

/**
 * Coluna canônica «Última consulta» — timestamp da última busca/sync válida.
 */
export function buildMonitoringConsultedColumn<T>(options: {
  getAt: (row: T) => string | null | undefined
  /** date = só data; datetime = data+hora. */
  format?: 'date' | 'datetime'
  sortable?: boolean
  testId?: string
}): TableColumn<T> {
  const format = options.format ?? 'date'
  const testId = options.testId ?? 'monitoring-last-consulted'
  return {
    id: MONITORING_CONSULTED_ID,
    header: options.sortable === false
      ? MONITORING_CONSULTED_LABEL
      : ({ column }) => sortHeader(MONITORING_CONSULTED_LABEL, column),
    enableSorting: options.sortable !== false,
    meta: { ...MONITORING_CONSULTED_META },
    cell: ({ row }) => {
      const raw = options.getAt(row.original)
      const label = format === 'datetime' ? formatDateTime(raw) : formatDate(raw)
      const full = raw ? formatDateTime(raw) : 'Nenhuma consulta registrada'
      return h(UTooltip, { text: full }, {
        default: () => h('span', {
          'class': 'whitespace-nowrap tabular-nums text-xs text-muted',
          'data-testid': testId
        }, label)
      })
    }
  }
}
