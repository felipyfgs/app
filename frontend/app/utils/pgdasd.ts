import type {
  PgdasdClientSummary,
  PgdasdCommunicationPreference,
  PgdasdDeclarationState,
  PgdasdHistoryPayload,
  PgdasdHistoryPeriod,
  PgdasdRbt12Summary,
  PgdasdTrackingStatus,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { formatAmountCents, formatCurrency, formatDateTime } from '~/utils/format'

export interface PgdasdStateMeta {
  label: string
  description: string
  color: 'success' | 'warning' | 'error' | 'neutral' | 'info'
  icon: string
}

const DECLARATION_META: Record<PgdasdDeclarationState, PgdasdStateMeta> = {
  CURRENT: {
    label: 'Em dia',
    description: 'A declaração do período esperado foi localizada.',
    color: 'success',
    icon: 'i-lucide-circle-check'
  },
  DUE_WITHIN_DEADLINE: {
    label: 'Pendências',
    description: 'A declaração ainda não foi localizada, mas o prazo confiável não terminou.',
    color: 'warning',
    icon: 'i-lucide-clock-3'
  },
  OVERDUE_NOT_FOUND: {
    label: 'Atrasado',
    description: 'Uma consulta válida posterior ao prazo confirmou a ausência da declaração.',
    color: 'error',
    icon: 'i-lucide-circle-alert'
  },
  UNVERIFIED: {
    label: 'Não verificado',
    description: 'Ainda não existe evidência produtiva suficiente para classificar o período.',
    color: 'neutral',
    icon: 'i-lucide-circle-help'
  }
}

const TRACKING_META: Record<PgdasdTrackingStatus, PgdasdStateMeta> = {
  NOT_CONFIGURED: {
    label: 'Envio não configurado',
    description: 'Configure um canal e um contato elegível para preparar o envio automático.',
    color: 'warning',
    icon: 'i-lucide-message-square-warning'
  },
  NO_HISTORY: {
    label: 'Sem histórico de envio',
    description: 'Nenhum envio foi registrado para este cliente.',
    color: 'neutral',
    icon: 'i-lucide-message-square-dashed'
  },
  QUEUED: {
    label: 'Na fila',
    description: 'O envio está aguardando processamento.',
    color: 'warning',
    icon: 'i-lucide-clock-3'
  },
  SENT: {
    label: 'Enviado',
    description: 'O provedor aceitou o envio.',
    color: 'info',
    icon: 'i-lucide-send'
  },
  DELIVERED: {
    label: 'Entregue',
    description: 'A entrega foi confirmada.',
    color: 'success',
    icon: 'i-lucide-message-square-check'
  },
  READ: {
    label: 'Lido',
    description: 'A leitura foi confirmada pelo canal.',
    color: 'success',
    icon: 'i-lucide-message-circle-check'
  },
  PARTIAL: {
    label: 'Entrega parcial',
    description: 'Os canais ou destinatários possuem resultados diferentes.',
    color: 'warning',
    icon: 'i-lucide-message-square-more'
  },
  FAILED: {
    label: 'Falha no envio',
    description: 'O envio não foi concluído.',
    color: 'error',
    icon: 'i-lucide-message-square-x'
  },
  CANCELED: {
    label: 'Envio cancelado',
    description: 'O envio foi cancelado.',
    color: 'neutral',
    icon: 'i-lucide-ban'
  }
}

export function pgdasdSummary(row?: SimplesMeiClientRow | null): PgdasdClientSummary | null {
  if (!row?.detail) return null
  if (row.detail.pgdasd) return row.detail.pgdasd

  const hasLegacySummary = row.detail.declaration_state != null
    || row.detail.last_declaration != null
    || row.detail.rbt12 != null
    || row.detail.last_productive_consulted_at != null
    || row.detail.communication != null
  if (!hasLegacySummary) return null

  return {
    expected_period_key: row.detail.period_key,
    latest_declaration: row.detail.last_declaration,
    declaration_state: row.detail.declaration_state,
    last_valid_query_at: row.detail.last_productive_consulted_at,
    rbt12: row.detail.rbt12,
    communication: row.detail.communication
  }
}

export function pgdasdDeclarationState(value?: string | null): PgdasdDeclarationState {
  const state = String(value || '').toUpperCase() as PgdasdDeclarationState
  return state in DECLARATION_META ? state : 'UNVERIFIED'
}

export function pgdasdDeclarationMeta(value?: string | null): PgdasdStateMeta {
  return DECLARATION_META[pgdasdDeclarationState(value)]
}

export function pgdasdTrackingStatus(value?: string | null): PgdasdTrackingStatus {
  const status = String(value || '').toUpperCase() as PgdasdTrackingStatus
  return status in TRACKING_META ? status : 'NO_HISTORY'
}

export function pgdasdTrackingMeta(value?: string | null): PgdasdStateMeta {
  return TRACKING_META[pgdasdTrackingStatus(value)]
}

export function pgdasdDeclarationPeriod(summary?: PgdasdClientSummary | null): string {
  return formatPgdasdPeriod(
    summary?.latest_declaration?.period_key || summary?.expected_period_key
  )
}

/** Formata as chaves oficiais YYYY-MM/YYYYMM sem expor o formato técnico na UI. */
export function formatPgdasdPeriod(value?: string | null): string {
  const raw = String(value || '').trim()
  const technical = raw.match(/^(\d{4})-?(\d{2})$/)
  const displayed = raw.match(/^(\d{2})\/(\d{4})$/)
  const year = technical?.[1] || displayed?.[2]
  const month = technical?.[2] || displayed?.[1]
  if (!year || !month || Number(month) < 1 || Number(month) > 12) return '—'
  return `${month}/${year}`
}

export function pgdasdRbt12Tooltip(rbt12?: PgdasdRbt12Summary | null): string {
  const parsed = rbt12?.status === 'PARSED'
  const hasValue = rbt12?.total_cents != null || Boolean(rbt12?.rbt12_value)
  if (!rbt12 || !parsed || !hasValue) {
    return rbt12?.availability_reason?.trim()
      || rbt12?.unavailable_reason?.trim()
      || 'RBT12 indisponível. O sistema não estima valores ausentes ou ambíguos.'
  }

  const total = rbt12.total_cents != null
    ? formatAmountCents(rbt12.total_cents)
    : formatCurrency(rbt12.rbt12_value)
  const parts = [
    `Receita bruta acumulada nos 12 meses anteriores ao período de apuração: ${total}.`
  ]
  if (rbt12.internal_market_cents != null) {
    parts.push(`Mercado interno: ${formatAmountCents(rbt12.internal_market_cents)}.`)
  } else if (rbt12.composition?.internal_market_cents != null) {
    parts.push(`Mercado interno: ${formatAmountCents(rbt12.composition.internal_market_cents)}.`)
  }
  if (rbt12.external_market_cents != null) {
    parts.push(`Mercado externo: ${formatAmountCents(rbt12.external_market_cents)}.`)
  } else if (rbt12.composition?.external_market_cents != null) {
    parts.push(`Mercado externo: ${formatAmountCents(rbt12.composition.external_market_cents)}.`)
  }
  const origin = [
    rbt12.origin?.das_number ? `DAS nº ${rbt12.origin.das_number}` : null,
    rbt12.origin?.declaration_number
      ? `declaração nº ${rbt12.origin.declaration_number}`
      : null
  ].filter(Boolean).join(' e ')
  if (origin) {
    parts.push(`Origem: extrato do ${origin}.`)
  }
  if (rbt12.extracted_at) {
    parts.push(`Extraído em ${formatDateTime(rbt12.extracted_at)}.`)
  }
  return parts.join(' ')
}

export function pgdasdCanRequestAutomatic(
  preference?: PgdasdCommunicationPreference | null
): boolean {
  if (!preference) return false
  const eligible = new Set(preference.eligible_channels || [])
  return (preference.email_enabled && eligible.has('EMAIL'))
    || (preference.whatsapp_enabled && eligible.has('WHATSAPP'))
}

/** Aceita o envelope oficial e o array aditivo usado durante deploy escalonado. */
export function pgdasdHistoryPeriods(
  payload?: PgdasdHistoryPayload | PgdasdHistoryPeriod[] | null
): PgdasdHistoryPeriod[] {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.periods)) return payload.periods
  if (Array.isArray(payload?.history)) return payload.history
  return []
}
