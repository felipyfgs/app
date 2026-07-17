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

/**
 * Serializa múltiplas opções de filtro (estável: unique + sort).
 * Usa vírgula — alinhado a query HTTP `situation=A,B`.
 */
export function encodeOptionValues(values: readonly string[]): string {
  const unique = new Set<string>()
  for (const raw of values) {
    const text = String(raw ?? '').trim()
    if (text) unique.add(text)
  }
  return [...unique].sort((a, b) => a.localeCompare(b)).join(',')
}

/** Parse de valor option (único ou multi "a,b"). Aceita array. */
export function decodeOptionValues(value: unknown): string[] {
  if (value == null) return []
  const parts = Array.isArray(value)
    ? value.map(item => String(item ?? '').trim()).filter(Boolean)
    : String(value).split(',').map(item => item.trim()).filter(Boolean)
  if (parts.length === 0) return []
  const encoded = encodeOptionValues(parts)
  return encoded ? encoded.split(',') : []
}

export function isMultipleOption(
  definition: DataTableFilterDefinition
): definition is Extract<DataTableFilterDefinition, { kind: 'option' }> & { multiple: true } {
  return definition.kind === 'option' && definition.multiple === true
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
  if (definition.kind === 'option' && definition.multiple) {
    const empty = String(definitionEmptyValue(definition))
    const values = decodeOptionValues(value).filter(item => item !== empty)
    return values.length === 0
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
    if (definition.multiple) {
      const values = decodeOptionValues(value)
      if (values.length === 0) return undefined
      return values.map(item => optionLabel(definition.items, item)).join(', ')
    }
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
  if (definition.kind === 'option' && definition.multiple) return 'in'
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

  if (definition.kind === 'option' && definition.multiple) {
    const empty = String(definitionEmptyValue(definition))
    const allowed = new Set(definition.items.map(item => item.value))
    const values = decodeOptionValues(value)
      .filter(item => item !== empty && allowed.has(item))
    if (values.length === 0) return null
    const encoded = encodeOptionValues(values)
    return {
      key: definition.key,
      operator: 'in',
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
  if (definition.kind === 'option' && definition.multiple) {
    const empty = String(definitionEmptyValue(definition))
    const allowed = new Set(definition.items.map(item => item.value))
    return decodeOptionValues(value).some(item => item !== empty && allowed.has(item))
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
  let valueLabel = model.label
  if (!valueLabel) {
    if (definition.kind === 'option') {
      // Multi/CSV sem label pré-resolvido: mapear tokens → rótulos (não o CSV cru).
      if (
        definition.multiple
        || model.operator === 'in'
        || decodeOptionValues(model.value).length > 1
      ) {
        const tokens = decodeOptionValues(model.value)
        valueLabel = tokens.length > 0
          ? tokens.map(token => optionLabel(definition.items, token)).join(', ')
          : String(model.value)
      } else {
        valueLabel = optionLabel(definition.items, String(model.value))
      }
    } else if (definition.kind === 'boolean') {
      valueLabel = booleanLabel(definition, Boolean(model.value))
    } else if (definition.kind === 'date_range') {
      const range = decodeDateRange(model.value)
      valueLabel = range ? `${range.from} – ${range.to}` : String(model.value)
    } else {
      valueLabel = String(model.value)
    }
  }

  let operatorLabel = 'é'
  if (model.operator === 'contains' || (definition.kind === 'text' && definition.operator === 'contains')) {
    operatorLabel = 'contém'
  } else if (model.operator === 'between' || definition.kind === 'date_range') {
    operatorLabel = 'entre'
  } else if (
    model.operator === 'in'
    || (definition.kind === 'option' && definition.multiple && decodeOptionValues(model.value).length > 1)
  ) {
    operatorLabel = decodeOptionValues(model.value).length > 1 ? 'é um de' : 'é'
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
  if (definition.kind === 'option' && definition.multiple) return ''
  return definition.emptyValue ?? 'all'
}
