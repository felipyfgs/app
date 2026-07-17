import type {
  PgmeiClientSummary,
  PgmeiDebtObservation,
  PgmeiDebtState,
  PgmeiFreshnessState,
  PgmeiHistoryPayload,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { formatAmountCents, formatDateTime } from '~/utils/format'

export interface PgmeiStateMeta {
  label: string
  color: 'success' | 'error' | 'neutral' | 'warning'
  icon: string
  description: string
}

const DEBT_META: Record<PgmeiDebtState, PgmeiStateMeta> = {
  HAS_ACTIVE_DEBT: {
    label: 'Possui dívida ativa',
    color: 'error',
    icon: 'i-lucide-circle-alert',
    description: 'A última consulta válida encontrou inscrições em dívida ativa no ano selecionado.'
  },
  NO_ACTIVE_DEBT: {
    label: 'Sem dívida localizada',
    color: 'success',
    icon: 'i-lucide-circle-check',
    description: 'Nenhuma inscrição foi localizada na última consulta válida, somente para o ano selecionado.'
  },
  UNVERIFIED: {
    label: 'Não verificável',
    color: 'neutral',
    icon: 'i-lucide-circle-help',
    description: 'Não existe consulta produtiva válida para confirmar a situação neste ano.'
  }
}

export function pgmeiSummary(
  row?: SimplesMeiClientRow | null,
  expectedYear?: number | null
): PgmeiClientSummary | null {
  const summary = row?.detail?.pgmei
  if (!summary) return null
  if (expectedYear && Number(summary.year) !== expectedYear) return null
  return summary
}

export function pgmeiDebtState(value?: string | null): PgmeiDebtState {
  const normalized = String(value || '').trim().toUpperCase()
  return normalized === 'HAS_ACTIVE_DEBT' || normalized === 'NO_ACTIVE_DEBT'
    ? normalized
    : 'UNVERIFIED'
}

export function pgmeiDebtMeta(value?: string | null): PgmeiStateMeta {
  return DEBT_META[pgmeiDebtState(value)]
}

export function pgmeiFreshnessState(value?: string | null): PgmeiFreshnessState {
  return String(value || '').trim().toUpperCase() === 'CURRENT' ? 'CURRENT' : 'OUTDATED'
}

export function pgmeiFreshnessMeta(value?: string | null): PgmeiStateMeta {
  if (pgmeiFreshnessState(value) === 'CURRENT') {
    return {
      label: 'Consulta atual',
      color: 'success',
      icon: 'i-lucide-clock-check',
      description: 'Consulta válida realizada há no máximo sete dias.'
    }
  }
  return {
    label: 'Consulta desatualizada',
    color: 'warning',
    icon: 'i-lucide-clock-alert',
    description: 'A última consulta válida tem mais de sete dias. Dívidas encontradas continuam visíveis.'
  }
}

export function pgmeiDebtTooltip(summary?: PgmeiClientSummary | null): string {
  if (!summary) return DEBT_META.UNVERIFIED.description
  const debt = pgmeiDebtMeta(summary.debt_state)
  const freshness = pgmeiFreshnessMeta(summary.freshness_state)
  const observed = summary.last_valid_query_at
    ? ` Última consulta: ${formatDateTime(summary.last_valid_query_at)}.`
    : ''
  return `${debt.description} ${freshness.description}${observed}`
}

/** Valor em centavos inteiro; nunca estima nem arredonda payload inválido. */
export function pgmeiTotalLabel(summary?: PgmeiClientSummary | null): string {
  const cents = Number(summary?.total_cents)
  if (!Number.isSafeInteger(cents) || cents < 0) return '—'
  return formatAmountCents(cents)
}

export function pgmeiHistoryObservations(
  payload?: PgmeiHistoryPayload | PgmeiDebtObservation[] | null
): PgmeiDebtObservation[] {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.observations)) return payload.observations
  if (Array.isArray(payload?.history)) return payload.history
  return []
}
