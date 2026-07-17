<script setup lang="ts">
/**
 * Corpo do editor de filtro (option / month / client).
 * Contêiner (popover/drawer) fica no Root; handlers e markup são compartilhados.
 */
import type { DataTableFilterDefinition } from '~/types/data-table-filter'

const props = defineProps<{
  definition: DataTableFilterDefinition
  modelValue: string | number | null
  label?: string
  canConfirm: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
  'update:label': [label: string | undefined]
  'confirm': []
  'cancel': []
}>()

const optionItems = computed(() => {
  if (props.definition.kind !== 'option') return []
  const empty = props.definition.emptyValue ?? 'all'
  return props.definition.items.filter(item => item.value !== empty)
})

const monthError = computed(() => {
  if (props.definition.kind !== 'month') return undefined
  const value = String(props.modelValue ?? '').trim()
  if (!value) return undefined
  if (/^\d{4}-(0[1-9]|1[0-2])$/.test(value)) return undefined
  return 'Use uma competência válida no formato AAAA-MM.'
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
        Operador: é (igualdade)
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
        :disabled="!canConfirm || Boolean(monthError)"
        data-testid="data-table-filter-confirm"
        @click="emit('confirm')"
      />
    </div>
  </div>
</template>
