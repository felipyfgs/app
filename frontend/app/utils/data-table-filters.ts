import type {
  DataTableFilterDefinition,
  DataTableFilterModel,
  DataTableFilterOperator,
  DataTableFilterOptionItem
} from '~/types/data-table-filter'

const MONTH_RE = /^\d{4}-(0[1-9]|1[0-2])$/
const DATE_RE = /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/

export function isValidMonthValue(value: unknown): boolean {
  return typeof value === 'string' && MONTH_RE.test(value.trim())
}

export function isValidDateValue(value: unknown): boolean {
  if (typeof value !== 'string') return false
  const text = value.trim()
  if (!DATE_RE.test(text)) return false
  // Validação calendário (rejeita 2026-02-31 etc.).
  const [y, m, d] = text.split('-').map(Number)
  if (!y || !m || !d) return false
  const dt = new Date(Date.UTC(y, m - 1, d))
  return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d
}

/** Serializa intervalo de datas para o valor do chip. */
export function encodeDateRange(from: string, to: string): string {
  return `${from.trim()}..${to.trim()}`
}

/** Parse de "YYYY-MM-DD..YYYY-MM-DD". */
export function decodeDateRange(value: unknown): { from: string, to: string } | null {
  if (typeof value !== 'string') return null
  const parts = value.trim().split('..')
  if (parts.length !== 2) return null
  const from = parts[0]!.trim()
  const to = parts[1]!.trim()
  if (!isValidDateValue(from) || !isValidDateValue(to)) return null
  return { from, to }
}

export function isValidDateRangeValue(value: unknown): boolean {
  const range = decodeDateRange(value)
  if (!range) return false
  return range.from <= range.to
}

export function definitionEmptyValue(definition: DataTableFilterDefinition): string | number | boolean | null {
  if (definition.kind === 'client' || definition.kind === 'boolean') {
    return definition.emptyValue === undefined ? null : definition.emptyValue
  }
  if (definition.kind === 'month' || definition.kind === 'date' || definition.kind === 'date_range' || definition.kind === 'text') {
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
  if (definition.kind === 'boolean') {
    if (typeof value === 'boolean') return false
    if (value === 'true' || value === 'false' || value === 1 || value === 0 || value === '1' || value === '0') {
      return false
    }
    return true
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

function booleanLabel(definition: Extract<DataTableFilterDefinition, { kind: 'boolean' }>, value: boolean): string {
  if (value) return definition.trueLabel || 'Sim'
  return definition.falseLabel || 'Não'
}

export function resolveModelLabel(
  definition: DataTableFilterDefinition,
  value: string | number | boolean,
  explicitLabel?: string
): string | undefined {
  if (explicitLabel && explicitLabel.trim()) return explicitLabel.trim()
  if (definition.kind === 'option') {
    return optionLabel(definition.items, String(value))
  }
  if (definition.kind === 'month' || definition.kind === 'date' || definition.kind === 'text') {
    return String(value)
  }
  if (definition.kind === 'boolean') {
    return booleanLabel(definition, Boolean(value))
  }
  if (definition.kind === 'date_range') {
    const range = decodeDateRange(value)
    if (!range) return String(value)
    return `${range.from} – ${range.to}`
  }
  return explicitLabel
}

function operatorForDefinition(
  definition: DataTableFilterDefinition
): DataTableFilterOperator {
  if (definition.kind === 'date_range') return 'between'
  if (definition.kind === 'text') return definition.operator === 'contains' ? 'contains' : 'eq'
  return 'eq'
}

export function createFilterModel(
  definition: DataTableFilterDefinition,
  value: string | number | boolean,
  label?: string
): DataTableFilterModel | null {
  if (isEmptyFilterValue(definition, value)) return null

  if (definition.kind === 'month' && !isValidMonthValue(value)) return null
  if (definition.kind === 'date' && !isValidDateValue(value)) return null
  if (definition.kind === 'date_range' && !isValidDateRangeValue(value)) return null

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

  if (definition.kind === 'boolean') {
    const bool = typeof value === 'boolean' ? value : value === 'true' || value === 1 || value === '1'
    if (typeof value === 'string' && value !== 'true' && value !== 'false' && value !== '1' && value !== '0') {
      return null
    }
    return {
      key: definition.key,
      operator: 'eq',
      value: bool,
      label: resolveModelLabel(definition, bool, label)
    }
  }

  if (definition.kind === 'date_range') {
    const range = decodeDateRange(value)
    if (!range) return null
    const encoded = encodeDateRange(range.from, range.to)
    return {
      key: definition.key,
      operator: 'between',
      value: encoded,
      label: resolveModelLabel(definition, encoded, label)
    }
  }

  const text = String(value).trim()
  return {
    key: definition.key,
    operator: operatorForDefinition(definition),
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
  value: unknown,
  valueTo?: unknown
): boolean {
  if (!definition) return false

  if (definition.kind === 'date_range') {
    const from = String(value ?? '').trim()
    const to = String(valueTo ?? '').trim()
    if (!from || !to) return false
    return isValidDateRangeValue(encodeDateRange(from, to))
  }

  if (isEmptyFilterValue(definition, value)) return false
  if (definition.kind === 'month') return isValidMonthValue(value)
  if (definition.kind === 'date') return isValidDateValue(value)
  if (definition.kind === 'client') {
    const n = Number(value)
    return Number.isFinite(n) && n > 0
  }
  if (definition.kind === 'boolean') {
    return typeof value === 'boolean'
      || value === 'true'
      || value === 'false'
      || value === 1
      || value === 0
      || value === '1'
      || value === '0'
  }
  if (definition.kind === 'text') {
    return String(value).trim().length > 0
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
      : definition.kind === 'boolean'
        ? booleanLabel(definition, Boolean(model.value))
        : definition.kind === 'date_range'
          ? (decodeDateRange(model.value)
              ? `${decodeDateRange(model.value)!.from} – ${decodeDateRange(model.value)!.to}`
              : String(model.value))
          : String(model.value))

  let operatorLabel = 'é'
  if (model.operator === 'contains' || (definition.kind === 'text' && definition.operator === 'contains')) {
    operatorLabel = 'contém'
  } else if (model.operator === 'between' || definition.kind === 'date_range') {
    operatorLabel = 'entre'
  }

  return {
    fieldLabel: definition.label,
    operatorLabel,
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

/** Valor inicial de rascunho ao adicionar um filtro. */
export function draftEmptyValue(
  definition: DataTableFilterDefinition
): string | number | boolean | null {
  if (definition.kind === 'client' || definition.kind === 'boolean') return null
  if (definition.kind === 'month' || definition.kind === 'date' || definition.kind === 'date_range' || definition.kind === 'text') {
    return ''
  }
  return definition.emptyValue ?? 'all'
}
