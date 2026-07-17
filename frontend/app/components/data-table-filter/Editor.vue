<script setup lang="ts">
/**
 * Corpo do editor de filtro (option / month / client / text / boolean / date / date_range).
 * Contêiner (popover/drawer) fica no Root; handlers e markup são compartilhados.
 */
import type { DataTableFilterDefinition } from '~/types/data-table-filter'
import { isValidDateValue, isValidMonthValue } from '~/utils/data-table-filters'

const props = defineProps<{
  definition: DataTableFilterDefinition
  modelValue: string | number | boolean | null
  /** Fim do intervalo (date_range). */
  valueTo?: string | null
  label?: string
  canConfirm: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | number | boolean | null]
  'update:valueTo': [value: string | null]
  'update:label': [label: string | undefined]
  'confirm': []
  'cancel': []
}>()

const optionItems = computed(() => {
  if (props.definition.kind !== 'option') return []
  const empty = props.definition.emptyValue ?? 'all'
  return props.definition.items.filter(item => item.value !== empty)
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
      v-if="definition.kind === 'option'"
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
      v-else-if="definition.kind === 'client'"
      :label="definition.label"
      class="min-w-0"
    >
      <slot
        name="client"
        :model-value="typeof modelValue === 'number' ? modelValue : null"
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

    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-default pt-3">
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
</template>
