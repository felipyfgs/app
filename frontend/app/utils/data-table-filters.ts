import type {
  DataTableFilterDefinition,
  DataTableFilterModel,
  DataTableFilterOptionItem
} from '~/types/data-table-filter'

const MONTH_RE = /^\d{4}-(0[1-9]|1[0-2])$/

export function isValidMonthValue(value: unknown): boolean {
  return typeof value === 'string' && MONTH_RE.test(value.trim())
}

export function definitionEmptyValue(definition: DataTableFilterDefinition): string | number | null {
  if (definition.kind === 'client') {
    return definition.emptyValue === undefined ? null : definition.emptyValue
  }
  if (definition.kind === 'month') {
    return definition.emptyValue === undefined ? '' : definition.emptyValue
  }
  return definition.emptyValue === undefined ? 'all' : definition.emptyValue
}

export function isEmptyFilterValue(
  definition: DataTableFilterDefinition,
  value: unknown
): boolean {
  if (value === undefined || value === null) return true
  if (definition.kind === 'client') {
    const n = Number(value)
    return !Number.isFinite(n) || n <= 0
  }
  const text = String(value).trim()
  if (text === '') return true
  const empty = definitionEmptyValue(definition)
  return text === String(empty)
}

export function findDefinition(
  definitions: readonly DataTableFilterDefinition[],
  key: string
): DataTableFilterDefinition | undefined {
  return definitions.find(item => item.key === key)
}

export function optionLabel(
  items: readonly DataTableFilterOptionItem[],
  value: string
): string {
  return items.find(item => item.value === value)?.label ?? value
}

export function resolveModelLabel(
  definition: DataTableFilterDefinition,
  value: string | number,
  explicitLabel?: string
): string | undefined {
  if (explicitLabel && explicitLabel.trim()) return explicitLabel.trim()
  if (definition.kind === 'option') {
    return optionLabel(definition.items, String(value))
  }
  if (definition.kind === 'month') {
    return String(value)
  }
  return explicitLabel
}

export function createFilterModel(
  definition: DataTableFilterDefinition,
  value: string | number,
  label?: string
): DataTableFilterModel | null {
  if (isEmptyFilterValue(definition, value)) return null
  if (definition.kind === 'month' && !isValidMonthValue(value)) return null
  if (definition.kind === 'client') {
    const id = Math.floor(Number(value))
    if (!Number.isFinite(id) || id <= 0) return null
    return {
      key: definition.key,
      operator: 'eq',
      value: id,
      label: resolveModelLabel(definition, id, label)
    }
  }
  const text = String(value).trim()
  return {
    key: definition.key,
    operator: 'eq',
    value: text,
    label: resolveModelLabel(definition, text, label)
  }
}

/**
 * Normaliza modelos aplicados: omite vazios, deduplica por chave (último vence),
 * ordena conforme as definitions e descarta chaves desconhecidas.
 */
export function normalizeFilterModels(
  models: readonly DataTableFilterModel[] | null | undefined,
  definitions: readonly DataTableFilterDefinition[]
): DataTableFilterModel[] {
  const byKey = new Map<string, DataTableFilterModel>()
  for (const model of models || []) {
    const definition = findDefinition(definitions, model.key)
    if (!definition) continue
    const next = createFilterModel(definition, model.value, model.label)
    if (!next) {
      byKey.delete(model.key)
      continue
    }
    byKey.set(model.key, next)
  }

  const ordered: DataTableFilterModel[] = []
  for (const definition of definitions) {
    const model = byKey.get(definition.key)
    if (model) ordered.push(model)
  }
  return ordered
}

export function activeDefinitionKeys(
  models: readonly DataTableFilterModel[],
  definitions: readonly DataTableFilterDefinition[]
): Set<string> {
  return new Set(normalizeFilterModels(models, definitions).map(model => model.key))
}

export function inactiveDefinitions(
  models: readonly DataTableFilterModel[],
  definitions: readonly DataTableFilterDefinition[]
): DataTableFilterDefinition[] {
  const active = activeDefinitionKeys(models, definitions)
  return definitions.filter(definition => !active.has(definition.key))
}

export function canConfirmDraftValue(
  definition: DataTableFilterDefinition | undefined,
  value: unknown
): boolean {
  if (!definition) return false
  if (isEmptyFilterValue(definition, value)) return false
  if (definition.kind === 'month') return isValidMonthValue(value)
  if (definition.kind === 'client') {
    const n = Number(value)
    return Number.isFinite(n) && n > 0
  }
  return true
}

export function formatChipDisplay(
  definition: DataTableFilterDefinition,
  model: DataTableFilterModel
): { fieldLabel: string, operatorLabel: string, valueLabel: string } {
  const valueLabel = model.label
    || (definition.kind === 'option'
      ? optionLabel(definition.items, String(model.value))
      : String(model.value))
  return {
    fieldLabel: definition.label,
    operatorLabel: 'é',
    valueLabel
  }
}

export function upsertFilterModel(
  models: readonly DataTableFilterModel[],
  definitions: readonly DataTableFilterDefinition[],
  next: DataTableFilterModel
): DataTableFilterModel[] {
  const without = models.filter(model => model.key !== next.key)
  return normalizeFilterModels([...without, next], definitions)
}

export function removeFilterModel(
  models: readonly DataTableFilterModel[],
  definitions: readonly DataTableFilterDefinition[],
  key: string
): DataTableFilterModel[] {
  return normalizeFilterModels(
    models.filter(model => model.key !== key),
    definitions
  )
}
