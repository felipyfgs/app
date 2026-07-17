/**
 * Estado controlado de filtros estruturados: modelos aplicados vêm de fora;
 * rascunho e cache de rótulos de cliente ficam internos e não disparam consulta.
 */
import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import type {
  DataTableFilterDefinition,
  DataTableFilterDraft,
  DataTableFilterModel
} from '~/types/data-table-filter'
import {
  canConfirmDraftValue,
  createFilterModel,
  findDefinition,
  inactiveDefinitions,
  normalizeFilterModels,
  removeFilterModel,
  upsertFilterModel
} from '~/utils/data-table-filters'

export interface UseDataTableFiltersOptions {
  definitions: MaybeRefOrGetter<readonly DataTableFilterDefinition[]>
  modelValue: MaybeRefOrGetter<readonly DataTableFilterModel[]>
  /** Troca de tenant/contexto — descarta rascunho e cache de rótulos. */
  resetKey?: MaybeRefOrGetter<string | number | null | undefined>
  onUpdate?: (models: DataTableFilterModel[]) => void
  onClear?: () => void
}

export function useDataTableFilters(options: UseDataTableFiltersOptions) {
  const definitions = computed(() => toValue(options.definitions) || [])
  const applied = computed(() =>
    normalizeFilterModels(toValue(options.modelValue), definitions.value)
  )

  const draft = ref<DataTableFilterDraft>(null)
  /** Cache visual de cliente keyed por id — limpo em resetKey. */
  const clientLabels = ref<Record<number, string>>({})

  const selectorOpen = ref(false)
  const editorOpen = ref(false)

  const inactive = computed(() =>
    inactiveDefinitions(applied.value, definitions.value)
  )

  const draftDefinition = computed(() => {
    if (!draft.value) return undefined
    return findDefinition(definitions.value, draft.value.key)
  })

  const canConfirm = computed(() =>
    canConfirmDraftValue(draftDefinition.value, draft.value?.value)
  )

  function discardDraft() {
    draft.value = null
    editorOpen.value = false
    selectorOpen.value = false
  }

  function rememberClientLabel(id: number, label: string) {
    if (!Number.isFinite(id) || id <= 0 || !label.trim()) return
    clientLabels.value = { ...clientLabels.value, [id]: label.trim() }
  }

  function resolveClientLabel(id: number, fallback?: string): string | undefined {
    return clientLabels.value[id] || fallback || undefined
  }

  function startAdd(key: string) {
    const definition = findDefinition(definitions.value, key)
    if (!definition) return
    if (applied.value.some(model => model.key === key)) {
      startEdit(key)
      return
    }
    const emptyDefault = definition.kind === 'client'
      ? null
      : definition.kind === 'month'
        ? ''
        : (definition.emptyValue ?? 'all')
    draft.value = {
      mode: 'add',
      key,
      value: emptyDefault as string | number | null,
      label: undefined
    }
    selectorOpen.value = false
    editorOpen.value = true
  }

  function startEdit(key: string) {
    const definition = findDefinition(definitions.value, key)
    const model = applied.value.find(item => item.key === key)
    if (!definition || !model) return
    draft.value = {
      mode: 'edit',
      key,
      value: model.value,
      label: model.label
    }
    selectorOpen.value = false
    editorOpen.value = true
  }

  function setDraftValue(value: string | number | null, label?: string) {
    if (!draft.value) return
    draft.value = {
      ...draft.value,
      value,
      label: label !== undefined ? label : draft.value.label
    }
    if (draftDefinition.value?.kind === 'client' && value != null && label) {
      rememberClientLabel(Number(value), label)
    }
  }

  function confirmDraft() {
    const definition = draftDefinition.value
    const current = draft.value
    if (!definition || !current || !canConfirm.value) return

    let label = current.label
    if (definition.kind === 'client' && current.value != null) {
      label = resolveClientLabel(Number(current.value), current.label)
    }

    const model = createFilterModel(definition, current.value as string | number, label)
    if (!model) return

    const next = upsertFilterModel(applied.value, definitions.value, model)
    discardDraft()
    options.onUpdate?.(next)
  }

  function remove(key: string) {
    const next = removeFilterModel(applied.value, definitions.value, key)
    if (draft.value?.key === key) discardDraft()
    options.onUpdate?.(next)
  }

  function clear() {
    discardDraft()
    clientLabels.value = {}
    options.onClear?.()
  }

  function openSelector() {
    if (inactive.value.length === 0) return
    discardDraft()
    selectorOpen.value = true
  }

  function closeSelector() {
    selectorOpen.value = false
  }

  function closeEditor() {
    // Fechar sem confirmar descarta o rascunho — não emite update.
    discardDraft()
  }

  watch(
    () => toValue(options.resetKey),
    (next, prev) => {
      if (prev === undefined || prev === null) return
      if (next === prev) return
      discardDraft()
      clientLabels.value = {}
    },
    { flush: 'sync' }
  )

  return {
    definitions,
    applied,
    draft,
    draftDefinition,
    inactive,
    selectorOpen,
    editorOpen,
    canConfirm,
    clientLabels,
    startAdd,
    startEdit,
    setDraftValue,
    confirmDraft,
    remove,
    clear,
    discardDraft,
    openSelector,
    closeSelector,
    closeEditor,
    rememberClientLabel,
    resolveClientLabel
  }
}
