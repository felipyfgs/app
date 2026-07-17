import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { DataTableFilterDefinition, DataTableFilterModel } from '../../app/types/data-table-filter'
import { useDataTableFilters } from '../../app/composables/useDataTableFilters'

const definitions: DataTableFilterDefinition[] = [
  {
    key: 'status',
    kind: 'option',
    label: 'Status',
    items: [
      { label: 'Ativo', value: 'ACTIVE' },
      { label: 'Inativo', value: 'INACTIVE' }
    ],
    emptyValue: 'all'
  },
  { key: 'competence', kind: 'month', label: 'Competência' },
  { key: 'clientId', kind: 'client', label: 'Cliente' }
]

describe('useDataTableFilters', () => {
  it('mantém rascunho separado e não emite até confirmar', () => {
    const modelValue = ref<DataTableFilterModel[]>([])
    const onUpdate = vi.fn((models: DataTableFilterModel[]) => {
      modelValue.value = models
    })
    const api = useDataTableFilters({
      definitions,
      modelValue,
      onUpdate
    })

    api.startAdd('status')
    expect(api.draft.value?.key).toBe('status')
    api.setDraftValue('ACTIVE', 'Ativo')
    expect(onUpdate).not.toHaveBeenCalled()

    api.confirmDraft()
    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(onUpdate.mock.calls[0]?.[0]).toEqual([
      { key: 'status', operator: 'eq', value: 'ACTIVE', label: 'Ativo' }
    ])
    expect(api.draft.value).toBeNull()
  })

  it('descarta rascunho ao fechar sem confirmar', () => {
    const modelValue = ref<DataTableFilterModel[]>([])
    const onUpdate = vi.fn()
    const api = useDataTableFilters({ definitions, modelValue, onUpdate })

    api.startAdd('competence')
    api.setDraftValue('2026-07')
    api.closeEditor()
    expect(onUpdate).not.toHaveBeenCalled()
    expect(api.draft.value).toBeNull()
    expect(modelValue.value).toEqual([])
  })

  it('bloqueia confirmação de competência inválida', () => {
    const modelValue = ref<DataTableFilterModel[]>([])
    const onUpdate = vi.fn()
    const api = useDataTableFilters({ definitions, modelValue, onUpdate })

    api.startAdd('competence')
    api.setDraftValue('2026-99')
    expect(api.canConfirm.value).toBe(false)
    api.confirmDraft()
    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('preserva rótulo de cliente e limpa no resetKey', () => {
    const modelValue = ref<DataTableFilterModel[]>([])
    const resetKey = ref(1)
    const onUpdate = vi.fn((models: DataTableFilterModel[]) => {
      modelValue.value = models
    })
    const api = useDataTableFilters({
      definitions,
      modelValue,
      resetKey,
      onUpdate
    })

    api.startAdd('clientId')
    api.setDraftValue(42, 'ACME LTDA')
    api.confirmDraft()
    expect(onUpdate.mock.calls[0]?.[0][0]).toMatchObject({
      key: 'clientId',
      value: 42,
      label: 'ACME LTDA'
    })
    expect(api.resolveClientLabel(42)).toBe('ACME LTDA')

    resetKey.value = 2
    // watch is sync in vue for refs
    expect(api.clientLabels.value).toEqual({})
    expect(api.draft.value).toBeNull()
  })

  it('remove e clear emitem uma vez cada', () => {
    const modelValue = ref<DataTableFilterModel[]>([
      { key: 'status', operator: 'eq', value: 'ACTIVE', label: 'Ativo' }
    ])
    const onUpdate = vi.fn((models: DataTableFilterModel[]) => {
      modelValue.value = models
    })
    const onClear = vi.fn()
    const api = useDataTableFilters({ definitions, modelValue, onUpdate, onClear })

    api.remove('status')
    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(onUpdate.mock.calls[0]?.[0]).toEqual([])

    api.clear()
    expect(onClear).toHaveBeenCalledTimes(1)
  })

  it('backToSelector em modo add descarta draft e reabre seletor', () => {
    const modelValue = ref<DataTableFilterModel[]>([])
    const onUpdate = vi.fn()
    const api = useDataTableFilters({ definitions, modelValue, onUpdate })

    api.startAdd('status')
    api.setDraftValue('ACTIVE', 'Ativo')
    expect(api.draft.value?.mode).toBe('add')
    expect(api.editorOpen.value).toBe(true)

    api.backToSelector()
    expect(onUpdate).not.toHaveBeenCalled()
    expect(api.draft.value).toBeNull()
    expect(api.editorOpen.value).toBe(false)
    expect(api.selectorOpen.value).toBe(true)
  })

  it('backToSelector em modo edit equivale a closeEditor (sem reabrir seletor)', () => {
    const modelValue = ref<DataTableFilterModel[]>([
      { key: 'status', operator: 'eq', value: 'ACTIVE', label: 'Ativo' }
    ])
    const onUpdate = vi.fn()
    const api = useDataTableFilters({ definitions, modelValue, onUpdate })

    api.startEdit('status')
    expect(api.draft.value?.mode).toBe('edit')
    api.setDraftValue('INACTIVE', 'Inativo')
    api.backToSelector()
    expect(onUpdate).not.toHaveBeenCalled()
    expect(api.draft.value).toBeNull()
    expect(api.editorOpen.value).toBe(false)
    expect(api.selectorOpen.value).toBe(false)
    expect(modelValue.value).toEqual([
      { key: 'status', operator: 'eq', value: 'ACTIVE', label: 'Ativo' }
    ])
  })
})
