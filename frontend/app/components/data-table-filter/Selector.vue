<script setup lang="ts">
/**
 * Lista de campos ainda inativos para adicionar filtro.
 * Usado dentro de UCommandPalette (desktop) ou lista simples no drawer.
 */
import type { DataTableFilterDefinition } from '~/types/data-table-filter'
import type { CommandPaletteGroup, CommandPaletteItem } from '@nuxt/ui'

const props = defineProps<{
  definitions: readonly DataTableFilterDefinition[]
}>()

const emit = defineEmits<{
  select: [key: string]
}>()

const groups = computed<CommandPaletteGroup[]>(() => [{
  id: 'fields',
  label: 'Campos',
  items: props.definitions.map((definition): CommandPaletteItem & { key: string } => ({
    key: definition.key,
    label: definition.label,
    icon: definition.kind === 'client'
      ? 'i-lucide-user'
      : definition.kind === 'month'
        ? 'i-lucide-calendar'
        : 'i-lucide-list-filter',
    onSelect: () => emit('select', definition.key)
  }))
}])

function onSelect(item: CommandPaletteItem | CommandPaletteItem[] | undefined | null) {
  if (!item || Array.isArray(item)) return
  const key = (item as CommandPaletteItem & { key?: string }).key
  if (key) emit('select', key)
}
</script>

<template>
  <UCommandPalette
    :groups="groups"
    placeholder="Buscar campo…"
    class="min-w-0 w-full max-h-72"
    :ui="{
      root: 'min-w-0',
      input: 'border-b border-default'
    }"
    data-testid="data-table-filter-selector"
    @update:model-value="onSelect"
  />
</template>
