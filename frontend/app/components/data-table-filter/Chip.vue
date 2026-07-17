<script setup lang="ts">
/**
 * Chip de filtro ativo — UFieldGroup + UButton outline (mesmo padrão da toolbar).
 * Rótulo: "Campo é Valor" com espaçamento explícito (evita "SituaçãoéBloqueado").
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

/** Texto legível com espaços reais (acessibilidade + fallback visual). */
const labelText = computed(
  () => `${display.value.fieldLabel} ${display.value.operatorLabel} ${display.value.valueLabel}`
)

const editAria = computed(() => `Editar filtro ${labelText.value}`)
const removeAria = computed(() => `Remover filtro ${display.value.fieldLabel}`)
</script>

<template>
  <UFieldGroup
    data-testid="data-table-filter-chip"
    :data-filter-key="model.key"
  >
    <UButton
      color="neutral"
      variant="outline"
      :aria-label="editAria"
      data-testid="data-table-filter-chip-edit"
      @click="emit('edit')"
    >
      <span class="inline-flex max-w-64 min-w-0 items-center gap-1.5">
        <span class="truncate text-muted">{{ display.fieldLabel }}</span>
        <span class="shrink-0 text-dimmed">{{ display.operatorLabel }}</span>
        <span class="truncate font-medium text-highlighted">{{ display.valueLabel }}</span>
      </span>
    </UButton>
    <UButton
      color="neutral"
      variant="outline"
      icon="i-lucide-x"
      square
      :aria-label="removeAria"
      data-testid="data-table-filter-chip-remove"
      @click="emit('remove')"
    />
  </UFieldGroup>
</template>
