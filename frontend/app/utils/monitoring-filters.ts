import type {
  MonitoringAdvancedFilterField,
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'

export const EMPTY_MONITORING_FILTERS: Readonly<MonitoringFilterValue> = Object.freeze({
  q: '',
  situation: 'all',
  competence: '',
  clientId: null,
  deliveryStatus: 'all',
  paymentStatus: 'all',
  status: 'all'
})

function normalizeClientId(value: unknown): number | null {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null
}

export function normalizeMonitoringFilters(
  value?: Partial<MonitoringFilterValue> | null
): MonitoringFilterValue {
  return {
    q: String(value?.q ?? '').trim(),
    situation: String(value?.situation || 'all'),
    competence: String(value?.competence ?? '').trim(),
    clientId: normalizeClientId(value?.clientId),
    deliveryStatus: String(value?.deliveryStatus || 'all'),
    paymentStatus: String(value?.paymentStatus || 'all'),
    status: String(value?.status || 'all')
  }
}

export function resetMonitoringFilters(): MonitoringFilterValue {
  return { ...EMPTY_MONITORING_FILTERS }
}

export function monitoringAdvancedFieldActive(
  filters: MonitoringFilterValue,
  field: MonitoringAdvancedFilterField
): boolean {
  const value = filters[field.key]
  if (field.kind === 'client') return value != null
  return String(value || '').trim() !== '' && value !== 'all'
}

export function countActiveMonitoringFilters(
  filters: MonitoringFilterValue,
  config: MonitoringFilterConfig
): number {
  return (config.advanced || []).filter(field => monitoringAdvancedFieldActive(filters, field)).length
}

export function monitoringFilterSignature(filters: MonitoringFilterValue): string {
  const normalized = normalizeMonitoringFilters(filters)
  return JSON.stringify([
    normalized.q,
    normalized.situation,
    normalized.competence,
    normalized.clientId,
    normalized.deliveryStatus,
    normalized.paymentStatus,
    normalized.status
  ])
}
