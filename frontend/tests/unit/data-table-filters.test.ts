import { describe, expect, it } from 'vitest'
import type { DataTableFilterDefinition, DataTableFilterModel } from '../../app/types/data-table-filter'
import {
  canConfirmDraftValue,
  createFilterModel,
  inactiveDefinitions,
  isValidMonthValue,
  normalizeFilterModels,
  removeFilterModel,
  upsertFilterModel
} from '../../app/utils/data-table-filters'

const definitions: DataTableFilterDefinition[] = [
  {
    key: 'situation',
    kind: 'option',
    label: 'Situação',
    items: [
      { label: 'Pendente', value: 'PENDING' },
      { label: 'Em dia', value: 'UP_TO_DATE' }
    ],
    emptyValue: 'all'
  },
  { key: 'competence', kind: 'month', label: 'Competência', emptyValue: '' },
  { key: 'clientId', kind: 'client', label: 'Cliente', emptyValue: null }
]

describe('data-table-filters', () => {
  it('valida competência YYYY-MM', () => {
    expect(isValidMonthValue('2026-07')).toBe(true)
    expect(isValidMonthValue('2026-13')).toBe(false)
    expect(isValidMonthValue('07-2026')).toBe(false)
    expect(isValidMonthValue('')).toBe(false)
  })

  it('omite valores vazios e defaults', () => {
    const models: DataTableFilterModel[] = [
      { key: 'situation', operator: 'eq', value: 'all' },
      { key: 'competence', operator: 'eq', value: '' },
      { key: 'clientId', operator: 'eq', value: 0 }
    ]
    expect(normalizeFilterModels(models, definitions)).toEqual([])
  })

  it('deduplica por chave e ordena conforme definitions', () => {
    const models: DataTableFilterModel[] = [
      { key: 'clientId', operator: 'eq', value: 9, label: 'Z' },
      { key: 'situation', operator: 'eq', value: 'PENDING' },
      { key: 'clientId', operator: 'eq', value: 3, label: 'A' },
      { key: 'competence', operator: 'eq', value: '2026-01' }
    ]
    expect(normalizeFilterModels(models, definitions)).toEqual([
      { key: 'situation', operator: 'eq', value: 'PENDING', label: 'Pendente' },
      { key: 'competence', operator: 'eq', value: '2026-01', label: '2026-01' },
      { key: 'clientId', operator: 'eq', value: 3, label: 'A' }
    ])
  })

  it('rejeita competência inválida ao criar modelo', () => {
    expect(createFilterModel(definitions[1]!, '2026-99')).toBeNull()
    expect(canConfirmDraftValue(definitions[1], '2026-99')).toBe(false)
    expect(canConfirmDraftValue(definitions[1], '2026-07')).toBe(true)
  })

  it('não lista campos já ativos para adição', () => {
    const applied = normalizeFilterModels([
      { key: 'situation', operator: 'eq', value: 'PENDING' }
    ], definitions)
    const inactive = inactiveDefinitions(applied, definitions)
    expect(inactive.map(item => item.key)).toEqual(['competence', 'clientId'])
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
})
