<script setup lang="ts">
/**
 * Chip de filtro ativo: editar (campo + operador + valor) e remover.
 */
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import { formatChipDisplay } from '~/utils/data-table-filters'

const props = defineProps<{
  definition: DataTableFilterDefinition
  model: DataTableFilterModel
}>()

const emit = defineEmits<{
  edit: []
  remove: []
}>()

const display = computed(() => formatChipDisplay(props.definition, props.model))
const editAria = computed(
  () => `Editar filtro ${display.value.fieldLabel} ${display.value.operatorLabel} ${display.value.valueLabel}`
)
const removeAria = computed(
  () => `Remover filtro ${display.value.fieldLabel}`
)
</script>

<template>
  <UFieldGroup
    size="sm"
    data-testid="data-table-filter-chip"
    :data-filter-key="model.key"
  >
    <UButton
      color="neutral"
      variant="subtle"
      :aria-label="editAria"
      data-testid="data-table-filter-chip-edit"
      @click="emit('edit')"
    >
      <span class="max-w-56 truncate">
        <span class="text-muted">{{ display.fieldLabel }}</span>
        <span class="mx-1 text-dimmed">{{ display.operatorLabel }}</span>
        <span class="font-medium text-highlighted">{{ display.valueLabel }}</span>
      </span>
    </UButton>
    <UButton
      color="neutral"
      variant="subtle"
      icon="i-lucide-x"
      :aria-label="removeAria"
      data-testid="data-table-filter-chip-remove"
      @click="emit('remove')"
    />
  </UFieldGroup>
</template>
