<script setup lang="ts">
/**
 * Corpo do editor de filtro (option / month / client / text / boolean / date / date_range).
 * Contêiner (popover/drawer) fica no Root; handlers e markup são compartilhados.
 */
import type { DataTableFilterDefinition } from '~/types/data-table-filter'
import {
  decodeClientIds,
  decodeOptionValues,
  encodeClientIds,
  encodeOptionValues,
  isValidDateValue,
  isValidMonthValue,
  optionLabel
} from '~/utils/data-table-filters'

const props = withDefaults(defineProps<{
  definition: DataTableFilterDefinition
  modelValue: string | number | boolean | null
  /** Fim do intervalo (date_range). */
  valueTo?: string | null
  label?: string
  canConfirm: boolean
  /** Exibe "Voltar" para a lista de campos (modo add). */
  showBack?: boolean
}>(), {
  showBack: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | boolean | null]
  'update:valueTo': [value: string | null]
  'update:label': [label: string | undefined]
  'confirm': []
  'cancel': []
  'back': []
}>()

const isOptionMultiple = computed(
  () => props.definition.kind === 'option' && props.definition.multiple === true
)

const optionItems = computed(() => {
  if (props.definition.kind !== 'option') return []
  const empty = props.definition.emptyValue ?? 'all'
  return props.definition.items.filter(item => item.value !== empty)
})

const multiOptionModel = computed({
  get: () => decodeOptionValues(props.modelValue),
  set: (values: string[]) => {
    // Valor vazio estável: '' (não null) — USelect multiple e createFilterModel tratam igual.
    const encoded = encodeOptionValues(values)
    emit('update:modelValue', encoded || '')
    if (!encoded) {
      emit('update:label', undefined)
      return
    }
    if (props.definition.kind !== 'option') return
    const labels = decodeOptionValues(encoded)
      .map(value => optionLabel(props.definition.items, value))
    emit('update:label', labels.join(', '))
  }
})

const booleanItems = computed(() => {
  if (props.definition.kind !== 'boolean') return []
  return [
    { label: props.definition.trueLabel || 'Sim', value: 'true' },
    { label: props.definition.falseLabel || 'Não', value: 'false' }
  ]
})

const monthError = computed(() => {
  if (props.definition.kind !== 'month') return undefined
  const value = String(props.modelValue ?? '').trim()
  if (!value) return undefined
  if (isValidMonthValue(value)) return undefined
  return 'Use uma competência válida no formato AAAA-MM.'
})

const dateError = computed(() => {
  if (props.definition.kind !== 'date') return undefined
  const value = String(props.modelValue ?? '').trim()
  if (!value) return undefined
  if (isValidDateValue(value)) return undefined
  return 'Use uma data válida no formato AAAA-MM-DD.'
})

const dateRangeError = computed(() => {
  if (props.definition.kind !== 'date_range') return undefined
  const from = String(props.modelValue ?? '').trim()
  const to = String(props.valueTo ?? '').trim()
  if (!from && !to) return undefined
  if (from && !isValidDateValue(from)) return 'Data inicial inválida (AAAA-MM-DD).'
  if (to && !isValidDateValue(to)) return 'Data final inválida (AAAA-MM-DD).'
  if (from && to && from > to) return 'A data inicial deve ser anterior ou igual à final.'
  return undefined
})

const fieldError = computed(() => monthError.value || dateError.value || dateRangeError.value)

const operatorHint = computed(() => {
  if (props.definition.kind === 'text' && props.definition.operator === 'contains') {
    return 'Operador: contém'
  }
  if (props.definition.kind === 'date_range') {
    return 'Operador: entre'
  }
  if (isOptionMultiple.value) {
    return 'Operador: é um de (múltipla escolha)'
  }
  return 'Operador: é (igualdade)'
})

const booleanModel = computed({
  get: () => {
    if (typeof props.modelValue === 'boolean') return props.modelValue ? 'true' : 'false'
    if (props.modelValue === 'true' || props.modelValue === 'false') return String(props.modelValue)
    return ''
  },
  set: (value: string) => {
    if (value === 'true') emit('update:modelValue', true)
    else if (value === 'false') emit('update:modelValue', false)
    else emit('update:modelValue', null)
  }
})

function onOption(value: string) {
  emit('update:modelValue', value)
  const item = optionItems.value.find(entry => entry.value === value)
  emit('update:label', item?.label)
}

function onMultiOption(value: unknown) {
  const list = Array.isArray(value)
    ? value.map(item => String(item ?? '')).filter(Boolean)
    : value == null || value === ''
      ? []
      : [String(value)]
  multiOptionModel.value = list
}

function onMonth(value: string | number) {
  emit('update:modelValue', String(value ?? ''))
  emit('update:label', undefined)
}

function onText(value: string | number) {
  emit('update:modelValue', String(value ?? ''))
  emit('update:label', undefined)
}

function onDate(value: string | number) {
  emit('update:modelValue', String(value ?? ''))
  emit('update:label', undefined)
}

function onDateFrom(value: string | number) {
  emit('update:modelValue', String(value ?? ''))
  emit('update:label', undefined)
}

function onDateTo(value: string | number) {
  emit('update:valueTo', String(value ?? ''))
  emit('update:label', undefined)
}

function onBoolean(value: string) {
  booleanModel.value = value
  const item = booleanItems.value.find(entry => entry.value === value)
  emit('update:label', item?.label)
}

const isClientMultiple = computed(
  () => props.definition.kind === 'client' && props.definition.multiple === true
)

const multiClientModel = computed({
  get: () => decodeClientIds(props.modelValue),
  set: (ids: number[]) => {
    emit('update:modelValue', encodeClientIds(ids) || '')
  }
})

function onClientId(value: number | null) {
  emit('update:modelValue', value)
}

function onClientSelect(client: {
  display_name?: string | null
  legal_name?: string | null
  name?: string | null
  cnpj?: string | null
} | null) {
  if (!client) {
    emit('update:label', undefined)
    return
  }
  const name = client.display_name || client.legal_name || client.name || 'Cliente'
  emit('update:label', name)
}

function onClientSelectMany(clients: Array<{
  display_name?: string | null
  legal_name?: string | null
  name?: string | null
}>) {
  if (!clients.length) {
    emit('update:label', undefined)
    return
  }
  const names = clients.map(c => c.display_name || c.legal_name || c.name || 'Cliente')
  emit('update:label', names.join(', '))
}
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-3 p-3"
    data-testid="data-table-filter-editor"
  >
    <div>
      <p class="text-sm font-medium text-highlighted">
        {{ definition.label }}
      </p>
      <p class="mt-0.5 text-xs text-muted">
        {{ operatorHint }}
      </p>
    </div>

    <UFormField
      v-if="definition.kind === 'option' && isOptionMultiple"
      :label="definition.label"
      class="min-w-0"
      hint="Selecione uma ou mais opções"
    >
      <USelect
        :model-value="multiOptionModel"
        :items="optionItems"
        value-key="value"
        multiple
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-option-multi"
        :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
        @update:model-value="onMultiOption"
      />
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'option'"
      :label="definition.label"
      class="min-w-0"
    >
      <USelect
        :model-value="String(modelValue ?? '')"
        :items="optionItems"
        value-key="value"
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-option"
        :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
        @update:model-value="onOption(String($event))"
      />
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'month'"
      :label="definition.label"
      :error="monthError"
      class="min-w-0"
    >
      <UInput
        :model-value="String(modelValue ?? '')"
        type="month"
        placeholder="AAAA-MM"
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-month"
        @update:model-value="onMonth($event)"
      />
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'client' && isClientMultiple"
      :label="definition.label"
      class="min-w-0"
      hint="Um ou mais clientes"
    >
      <slot
        name="client"
        :model-value="multiClientModel"
        :multiple="true"
        :update="(v: number | number[] | null) => {
          multiClientModel = Array.isArray(v) ? v : (v != null ? [v] : [])
        }"
        :select="onClientSelect"
        :select-many="onClientSelectMany"
      >
        <FiscalClientPicker
          :model-value="multiClientModel"
          multiple
          search-mode="select"
          placeholder="Buscar e adicionar clientes"
          class="w-full min-w-0"
          data-testid="data-table-filter-client-multi"
          @update:model-value="(v) => {
            multiClientModel = Array.isArray(v) ? v : (typeof v === 'number' ? [v] : [])
          }"
          @select="onClientSelect"
          @select-many="onClientSelectMany"
        />
      </slot>
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'client'"
      :label="definition.label"
      class="min-w-0"
    >
      <slot
        name="client"
        :model-value="typeof modelValue === 'number' ? modelValue : null"
        :multiple="false"
        :update="onClientId"
        :select="onClientSelect"
      >
        <FiscalClientPicker
          :model-value="typeof modelValue === 'number' ? modelValue : null"
          search-mode="select"
          placeholder="Selecione um cliente"
          class="w-full min-w-0"
          data-testid="data-table-filter-client"
          @update:model-value="onClientId"
          @select="onClientSelect"
        />
      </slot>
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'text'"
      :label="definition.label"
      class="min-w-0"
    >
      <UInput
        :model-value="String(modelValue ?? '')"
        type="text"
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-text"
        @update:model-value="onText($event)"
      />
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'boolean'"
      :label="definition.label"
      class="min-w-0"
    >
      <USelect
        :model-value="booleanModel"
        :items="booleanItems"
        value-key="value"
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-boolean"
        :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
        @update:model-value="onBoolean(String($event))"
      />
    </UFormField>

    <UFormField
      v-else-if="definition.kind === 'date'"
      :label="definition.label"
      :error="dateError"
      class="min-w-0"
    >
      <UInput
        :model-value="String(modelValue ?? '')"
        type="date"
        placeholder="AAAA-MM-DD"
        class="w-full min-w-0"
        :aria-label="definition.label"
        data-testid="data-table-filter-date"
        @update:model-value="onDate($event)"
      />
    </UFormField>

    <div
      v-else-if="definition.kind === 'date_range'"
      class="flex min-w-0 flex-col gap-2"
    >
      <UFormField
        label="De"
        :error="dateRangeError"
        class="min-w-0"
      >
        <UInput
          :model-value="String(modelValue ?? '')"
          type="date"
          placeholder="AAAA-MM-DD"
          class="w-full min-w-0"
          :aria-label="`${definition.label} (início)`"
          data-testid="data-table-filter-date-from"
          @update:model-value="onDateFrom($event)"
        />
      </UFormField>
      <UFormField
        label="Até"
        class="min-w-0"
      >
        <UInput
          :model-value="String(valueTo ?? '')"
          type="date"
          placeholder="AAAA-MM-DD"
          class="w-full min-w-0"
          :aria-label="`${definition.label} (fim)`"
          data-testid="data-table-filter-date-to"
          @update:model-value="onDateTo($event)"
        />
      </UFormField>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-default pt-3">
      <UButton
        v-if="showBack"
        type="button"
        color="neutral"
        variant="ghost"
        icon="i-lucide-arrow-left"
        label="Campos"
        data-testid="data-table-filter-back"
        @click="emit('back')"
      />
      <div
        class="ms-auto flex flex-wrap items-center justify-end gap-2"
      >
        <UButton
          type="button"
          color="neutral"
          variant="ghost"
          label="Cancelar"
          data-testid="data-table-filter-cancel"
          @click="emit('cancel')"
        />
        <UButton
          type="button"
          color="primary"
          label="Confirmar"
          :disabled="!canConfirm || Boolean(fieldError)"
          data-testid="data-table-filter-confirm"
          @click="emit('confirm')"
        />
      </div>
    </div>
  </div>
</template>
