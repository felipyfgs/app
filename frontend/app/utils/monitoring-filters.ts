import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import type {
  MonitoringAdvancedFilterField,
  MonitoringFilterConfig,
  MonitoringFilterValue,
  MonitoringStructuredFilterField
} from '~/types/fiscal-modules'
import { fiscalSituationFilterItems } from '~/utils/fiscal-status'
import {
  createFilterModel,
  normalizeFilterModels
} from '~/utils/data-table-filters'

export const EMPTY_MONITORING_FILTERS: Readonly<MonitoringFilterValue> = Object.freeze({
  q: '',
  situation: 'all',
  competence: '',
  clientId: null,
  deliveryStatus: 'all',
  paymentStatus: 'all',
  status: 'all',
  coverage: 'all',
  modality: 'all'
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
    status: String(value?.status || 'all'),
    coverage: String(value?.coverage || 'all'),
    modality: String(value?.modality || 'all')
  }
}

export function resetMonitoringFilters(): MonitoringFilterValue {
  return { ...EMPTY_MONITORING_FILTERS }
}

/**
 * Normaliza a config da toolbar para `fields` ordenados.
 * Aceita legado `situation` + `advanced` e converte para o contrato novo.
 */
export function resolveMonitoringFilterFields(
  config: MonitoringFilterConfig | null | undefined
): MonitoringStructuredFilterField[] {
  if (config?.fields && config.fields.length > 0) {
    return config.fields
  }

  const fields: MonitoringStructuredFilterField[] = []
  if (config?.situation !== false && (config?.situation === true || config?.advanced)) {
    // Legado: situation era quick-filter; no novo modelo só entra se explicitamente pedido
    // via fields. Não auto-injetar situation a partir de advanced-only configs.
  }
  if (config?.situation === true) {
    fields.push({
      key: 'situation',
      kind: 'option',
      label: 'Situação',
      items: fiscalSituationFilterItems(false)
    })
  }

  for (const field of config?.advanced || []) {
    if (field.key === 'competence') {
      fields.push({ key: 'competence', kind: 'month', label: field.label })
      continue
    }
    if (field.key === 'clientId') {
      fields.push({ key: 'clientId', kind: 'client', label: field.label })
      continue
    }
    fields.push({
      key: field.key,
      kind: 'option',
      label: field.label,
      items: field.items
    })
  }

  return fields
}

export function monitoringFieldsToDefinitions(
  fields: readonly MonitoringStructuredFilterField[]
): DataTableFilterDefinition[] {
  return fields.map((field): DataTableFilterDefinition => {
    if (field.kind === 'client') {
      return { key: field.key, kind: 'client', label: field.label, emptyValue: null }
    }
    if (field.kind === 'month') {
      return { key: field.key, kind: 'month', label: field.label, emptyValue: '' }
    }
    const items = field.key === 'situation'
      ? (field.items && field.items.length > 0 ? field.items : fiscalSituationFilterItems(false))
      : field.items
    return {
      key: field.key,
      kind: 'option',
      label: field.label,
      items,
      emptyValue: 'all'
    }
  })
}

const OPTION_KEYS = [
  'situation',
  'deliveryStatus',
  'paymentStatus',
  'status',
  'coverage',
  'modality'
] as const

type OptionFilterKey = (typeof OPTION_KEYS)[number]

function isOptionFilterKey(key: string): key is OptionFilterKey {
  return (OPTION_KEYS as readonly string[]).includes(key)
}

/** Converte valor backend-facing → chips (omite defaults vazios). */
export function monitoringFiltersToModels(
  filters: MonitoringFilterValue,
  config: MonitoringFilterConfig | null | undefined,
  clientLabel?: string | null
): DataTableFilterModel[] {
  const fields = resolveMonitoringFilterFields(config)
  const definitions = monitoringFieldsToDefinitions(fields)
  const raw: DataTableFilterModel[] = []

  for (const definition of definitions) {
    if (definition.key === 'competence') {
      const model = createFilterModel(definition, filters.competence)
      if (model) raw.push(model)
      continue
    }
    if (definition.key === 'clientId') {
      if (filters.clientId == null) continue
      const model = createFilterModel(definition, filters.clientId, clientLabel || undefined)
      if (model) raw.push(model)
      continue
    }
    if (isOptionFilterKey(definition.key)) {
      const model = createFilterModel(definition, filters[definition.key])
      if (model) raw.push(model)
    }
  }

  return normalizeFilterModels(raw, definitions)
}

/** Converte chips → valor backend-facing, preservando `q` e defaults. */
export function modelsToMonitoringFilters(
  models: readonly DataTableFilterModel[],
  config: MonitoringFilterConfig | null | undefined,
  base?: Partial<MonitoringFilterValue> | null
): MonitoringFilterValue {
  const fields = resolveMonitoringFilterFields(config)
  const definitions = monitoringFieldsToDefinitions(fields)
  const normalizedModels = normalizeFilterModels(models, definitions)
  const next = normalizeMonitoringFilters(base)
  const byKey = new Map(normalizedModels.map(model => [model.key, model]))

  // Campos estruturados desta config: default se ausente no chip.
  for (const definition of definitions) {
    const model = byKey.get(definition.key)
    if (definition.key === 'clientId') {
      next.clientId = model ? Number(model.value) : null
      continue
    }
    if (definition.key === 'competence') {
      next.competence = model ? String(model.value) : ''
      continue
    }
    if (isOptionFilterKey(definition.key)) {
      next[definition.key] = model ? String(model.value) : 'all'
    }
  }

  return next
}

export function monitoringAdvancedFieldActive(
  filters: MonitoringFilterValue,
  field: MonitoringAdvancedFilterField | MonitoringStructuredFilterField
): boolean {
  if (field.key === 'clientId') return filters.clientId != null
  if (field.key === 'competence') return String(filters.competence || '').trim() !== ''
  if (field.key === 'situation') {
    return String(filters.situation || '').trim() !== '' && filters.situation !== 'all'
  }
  if (field.key === 'deliveryStatus') {
    return String(filters.deliveryStatus || '').trim() !== '' && filters.deliveryStatus !== 'all'
  }
  if (field.key === 'paymentStatus') {
    return String(filters.paymentStatus || '').trim() !== '' && filters.paymentStatus !== 'all'
  }
  if (field.key === 'status') {
    return String(filters.status || '').trim() !== '' && filters.status !== 'all'
  }
  if (field.key === 'coverage') {
    return String(filters.coverage || '').trim() !== '' && filters.coverage !== 'all'
  }
  if (field.key === 'modality') {
    return String(filters.modality || '').trim() !== '' && filters.modality !== 'all'
  }
  return false
}

export function countActiveMonitoringFilters(
  filters: MonitoringFilterValue,
  config: MonitoringFilterConfig
): number {
  const fields = resolveMonitoringFilterFields(config)
  return fields.filter(field => monitoringAdvancedFieldActive(filters, field)).length
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
    normalized.status,
    normalized.coverage,
    normalized.modality
  ])
}

/** Conta chips (ignora busca `q`). */
export function countStructuredMonitoringFilters(
  filters: MonitoringFilterValue,
  config: MonitoringFilterConfig
): number {
  return countActiveMonitoringFilters(filters, config)
}

export function isMonitoringStructuredFieldEmpty(
  key: keyof MonitoringFilterValue,
  value: unknown
): boolean {
  if (key === 'clientId') return value == null || Number(value) <= 0
  if (key === 'competence' || key === 'q') return String(value ?? '').trim() === ''
  return value === 'all' || value === '' || value == null
}
