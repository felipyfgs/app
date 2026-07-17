import { describe, expect, it } from 'vitest'
import type { DataTableFilterDefinition, DataTableFilterModel } from '../../app/types/data-table-filter'
import {
  canConfirmDraftValue,
  createFilterModel,
  decodeClientIds,
  decodeOptionValues,
  encodeClientIds,
  encodeDateRange,
  encodeOptionValues,
  filterKindIcon,
  formatChipDisplay,
  formatOptionChipValueLabel,
  inactiveDefinitions,
  isValidDateRangeValue,
  isValidDateValue,
  isValidMonthValue,
  normalizeFilterModels,
  removeFilterModel,
  sanitizeMultipleOptionValues,
  upsertFilterModel
} from '../../app/utils/data-table-filters'

const definitions: DataTableFilterDefinition[] = [
  {
    key: 'situation',
    kind: 'option',
    label: 'Situação',
    items: [
      { label: 'Pendente', value: 'PENDING' },
      { label: 'Em dia', value: 'UP_TO_DATE' },
      { label: 'Atenção', value: 'ATTENTION' },
      { label: 'Erro', value: 'ERROR' },
      { label: 'Bloqueado', value: 'BLOCKED' }
    ],
    emptyValue: 'all',
    multiple: true
  },
  { key: 'competence', kind: 'month', label: 'Competência', emptyValue: '' },
  { key: 'clientId', kind: 'client', label: 'Cliente', emptyValue: null, multiple: true },
  { key: 'process_number', kind: 'text', label: 'Processo', operator: 'contains' },
  { key: 'is_overdue', kind: 'boolean', label: 'Em atraso', trueLabel: 'Sim', falseLabel: 'Não' },
  { key: 'due_at', kind: 'date', label: 'Vencimento' },
  { key: 'consulted', kind: 'date_range', label: 'Consultado em' }
]

describe('data-table-filters', () => {
  it('valida competência YYYY-MM', () => {
    expect(isValidMonthValue('2026-07')).toBe(true)
    expect(isValidMonthValue('2026-13')).toBe(false)
    expect(isValidMonthValue('07-2026')).toBe(false)
    expect(isValidMonthValue('')).toBe(false)
  })

  it('valida data YYYY-MM-DD e rejeita calendário inválido', () => {
    expect(isValidDateValue('2026-07-17')).toBe(true)
    expect(isValidDateValue('2026-02-30')).toBe(false)
    expect(isValidDateValue('2026-13-01')).toBe(false)
    expect(isValidDateValue('17-07-2026')).toBe(false)
  })

  it('valida date_range ordenado', () => {
    expect(isValidDateRangeValue(encodeDateRange('2026-01-01', '2026-01-31'))).toBe(true)
    expect(isValidDateRangeValue(encodeDateRange('2026-02-01', '2026-01-01'))).toBe(false)
    expect(isValidDateRangeValue('2026-01-01')).toBe(false)
  })

  it('omite valores vazios e defaults', () => {
    const models: DataTableFilterModel[] = [
      { key: 'situation', operator: 'eq', value: 'all' },
      { key: 'competence', operator: 'eq', value: '' },
      { key: 'clientId', operator: 'eq', value: 0 },
      { key: 'process_number', operator: 'contains', value: '  ' }
    ]
    expect(normalizeFilterModels(models, definitions)).toEqual([])
  })

  it('deduplica por chave e ordena conforme definitions', () => {
    const models: DataTableFilterModel[] = [
      { key: 'clientId', operator: 'in', value: '9', label: 'Z' },
      { key: 'situation', operator: 'eq', value: 'PENDING' },
      { key: 'clientId', operator: 'in', value: '3', label: 'A' },
      { key: 'competence', operator: 'eq', value: '2026-01' }
    ]
    expect(normalizeFilterModels(models, definitions)).toEqual([
      { key: 'situation', operator: 'in', value: 'PENDING', label: 'Pendente' },
      { key: 'competence', operator: 'eq', value: '2026-01', label: '2026-01' },
      { key: 'clientId', operator: 'in', value: '3', label: 'A' }
    ])
  })

  it('client multiple serializa ids e chip é um de', () => {
    expect(encodeClientIds([3, 1, 3])).toBe('1,3')
    expect(decodeClientIds('3,1,0')).toEqual([1, 3])
    const model = createFilterModel(definitions[2]!, '3,1', 'Acme, Beta')
    expect(model).toMatchObject({
      key: 'clientId',
      operator: 'in',
      value: '1,3',
      label: 'Acme, Beta'
    })
    const display = formatChipDisplay(definitions[2]!, model!)
    expect(display.operatorLabel).toBe('é um de')
    expect(filterKindIcon(definitions[2]!)).toBe('i-lucide-users')
  })

  it('option multiple serializa IN com rótulos e chip "é um de"', () => {
    expect(encodeOptionValues(['UP_TO_DATE', 'PENDING', 'PENDING'])).toBe('PENDING,UP_TO_DATE')
    expect(decodeOptionValues('ATTENTION,PENDING')).toEqual(['ATTENTION', 'PENDING'])

    const model = createFilterModel(definitions[0]!, 'UP_TO_DATE,PENDING,UNKNOWN')
    expect(model).toEqual({
      key: 'situation',
      operator: 'in',
      value: 'PENDING,UP_TO_DATE',
      label: 'Pendente, Em dia'
    })
    expect(canConfirmDraftValue(definitions[0], 'PENDING,ATTENTION')).toBe(true)
    expect(canConfirmDraftValue(definitions[0], '')).toBe(false)

    const display = formatChipDisplay(definitions[0]!, model!)
    expect(display.operatorLabel).toBe('é um de')
    expect(display.valueLabel).toContain('Pendente')
  })

  it('rejeita competência inválida ao criar modelo', () => {
    expect(createFilterModel(definitions[1]!, '2026-99')).toBeNull()
    expect(canConfirmDraftValue(definitions[1], '2026-99')).toBe(false)
    expect(canConfirmDraftValue(definitions[1], '2026-07')).toBe(true)
  })

  it('cria modelo text com operator contains', () => {
    const model = createFilterModel(definitions[3]!, '  ABC  ')
    expect(model).toEqual({
      key: 'process_number',
      operator: 'contains',
      value: 'ABC',
      label: 'ABC'
    })
    expect(canConfirmDraftValue(definitions[3], '')).toBe(false)
    expect(canConfirmDraftValue(definitions[3], 'x')).toBe(true)
  })

  it('cria modelo boolean com rótulos', () => {
    expect(createFilterModel(definitions[4]!, true)).toEqual({
      key: 'is_overdue',
      operator: 'eq',
      value: true,
      label: 'Sim'
    })
    expect(createFilterModel(definitions[4]!, false)?.label).toBe('Não')
    expect(canConfirmDraftValue(definitions[4], true)).toBe(true)
    expect(canConfirmDraftValue(definitions[4], null)).toBe(false)
  })

  it('cria modelo date e date_range', () => {
    expect(createFilterModel(definitions[5]!, '2026-07-17')).toMatchObject({
      key: 'due_at',
      operator: 'eq',
      value: '2026-07-17'
    })
    expect(createFilterModel(definitions[5]!, '2026-99-01')).toBeNull()

    const range = createFilterModel(definitions[6]!, '2026-01-01..2026-01-31')
    expect(range).toMatchObject({
      key: 'consulted',
      operator: 'between',
      value: '2026-01-01..2026-01-31'
    })
    expect(canConfirmDraftValue(definitions[6], '2026-01-01', '2026-01-31')).toBe(true)
    expect(canConfirmDraftValue(definitions[6], '2026-02-01', '2026-01-01')).toBe(false)
  })

  it('não lista campos já ativos para adição', () => {
    const applied = normalizeFilterModels([
      { key: 'situation', operator: 'in', value: 'PENDING' }
    ], definitions)
    const inactive = inactiveDefinitions(applied, definitions)
    expect(inactive.map(item => item.key)).toEqual([
      'competence',
      'clientId',
      'process_number',
      'is_overdue',
      'due_at',
      'consulted'
    ])
  })

  it('upsert substitui e remove emite sem a chave', () => {
    const base = normalizeFilterModels([
      { key: 'situation', operator: 'eq', value: 'PENDING' }
    ], definitions)
    const next = upsertFilterModel(base, definitions, {
      key: 'situation',
      operator: 'eq',
      value: 'UP_TO_DATE',
      label: 'Em dia'
    })
    expect(next).toHaveLength(1)
    expect(next[0]?.value).toBe('UP_TO_DATE')
    expect(removeFilterModel(next, definitions, 'situation')).toEqual([])
  })

  it('formatChipDisplay multi resolve tokens e trunca com +N', () => {
    const two: DataTableFilterModel = {
      key: 'situation',
      operator: 'in',
      value: 'ATTENTION,PENDING'
    }
    const twoDisplay = formatChipDisplay(definitions[0]!, two)
    expect(twoDisplay.valueLabel).toBe('Atenção, Pendente')
    expect(twoDisplay.operatorLabel).toBe('é um de')
    expect(twoDisplay.fieldLabel).toBe('Situação')

    const many: DataTableFilterModel = {
      key: 'situation',
      operator: 'in',
      value: 'ATTENTION,BLOCKED,ERROR,PENDING',
      label: 'rótulo longo ignorado no chip multi'
    }
    const manyDisplay = formatChipDisplay(definitions[0]!, many)
    expect(manyDisplay.valueLabel).toBe('Atenção, Bloqueado +2')
    expect(manyDisplay.operatorLabel).toBe('é um de')
    expect(formatOptionChipValueLabel(['A', 'B', 'C', 'D'], 2)).toBe('A, B +2')
    expect(sanitizeMultipleOptionValues(definitions[0]!, 'PENDING,HACK,all')).toEqual(['PENDING'])
  })
})
