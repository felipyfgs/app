<script setup lang="ts">
/**
 * Filtros estruturados controlados: chips + seletor responsivo + editor.
 * Desktop: UPopover; mobile: UDrawer. Confirmação explícita; fechar descarta rascunho.
 */
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import type {
  DataTableFilterDefinition,
  DataTableFilterModel
} from '~/types/data-table-filter'
import { findDefinition } from '~/utils/data-table-filters'
import { useDataTableFilters } from '~/composables/useDataTableFilters'
import DataTableFilterChip from '~/components/data-table-filter/Chip.vue'
import DataTableFilterEditor from '~/components/data-table-filter/Editor.vue'
import DataTableFilterSelector from '~/components/data-table-filter/Selector.vue'

const props = withDefaults(defineProps<{
  definitions: DataTableFilterDefinition[]
  modelValue?: DataTableFilterModel[]
  /** sessionEpoch / office — limpa rascunho e rótulos de cliente. */
  resetKey?: string | number | null
  addLabel?: string
  clearLabel?: string
  showClear?: boolean
}>(), {
  modelValue: () => [],
  addLabel: 'Adicionar filtro',
  clearLabel: 'Limpar tudo',
  showClear: true
})

const emit = defineEmits<{
  'update:modelValue': [models: DataTableFilterModel[]]
  'clear': []
}>()

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const {
  applied,
  draft,
  draftDefinition,
  inactive,
  selectorOpen,
  editorOpen,
  canConfirm,
  startAdd,
  startEdit,
  setDraftValue,
  confirmDraft,
  remove,
  clear,
  openSelector,
  closeEditor,
  discardDraft
} = useDataTableFilters({
  definitions: () => props.definitions,
  modelValue: () => props.modelValue,
  resetKey: () => props.resetKey,
  onUpdate: models => emit('update:modelValue', models),
  onClear: () => emit('clear')
})

const hasApplied = computed(() => applied.value.length > 0)
const canAdd = computed(() => inactive.value.length > 0)

/** Overlay unificado: seletor ou editor (mesmo contêiner desktop/mobile). */
const overlayOpen = computed({
  get: () => selectorOpen.value || editorOpen.value,
  set: (open: boolean) => {
    if (!open) {
      discardDraft()
      return
    }
    // Trigger "Adicionar filtro" pediu abertura sem editor ativo → seletor.
    if (!editorOpen.value) openSelector()
  }
})

const overlayMode = computed<'selector' | 'editor' | null>(() => {
  if (editorOpen.value && draftDefinition.value) return 'editor'
  if (selectorOpen.value) return 'selector'
  return null
})

const overlayTitle = computed(() => {
  if (overlayMode.value === 'editor') return draftDefinition.value?.label || 'Editar filtro'
  return 'Adicionar filtro'
})

function definitionFor(key: string) {
  return findDefinition(props.definitions, key)
}

function onDraftValue(value: string | number | null) {
  setDraftValue(value)
}

function onDraftLabel(label: string | undefined) {
  setDraftValue(draft.value?.value ?? null, label)
}
</script>

<template>
  <div
    class="flex min-w-0 w-full flex-wrap items-center gap-1.5"
    data-testid="data-table-filters"
  >
    <DataTableFilterChip
      v-for="model in applied"
      :key="model.key"
      :definition="definitionFor(model.key)!"
      :model="model"
      @edit="startEdit(model.key)"
      @remove="remove(model.key)"
    />

    <!-- Desktop: popover com seletor ou editor -->
    <UPopover
      v-if="!isMobile"
      v-model:open="overlayOpen"
      :content="{ align: 'start', side: 'bottom' }"
    >
      <UButton
        v-if="canAdd"
        color="neutral"
        variant="outline"
        icon="i-lucide-list-filter"
        :label="addLabel"
        data-testid="data-table-filter-add"
      />
      <span
        v-else
        class="sr-only"
        data-testid="data-table-filter-anchor"
      >Filtros</span>
      <template #content>
        <div
          class="w-80 max-w-[min(20rem,calc(100vw-2rem))] min-w-0 overflow-x-hidden"
          data-testid="data-table-filter-overlay"
        >
          <DataTableFilterSelector
            v-if="overlayMode === 'selector'"
            :definitions="inactive"
            @select="startAdd"
          />
          <DataTableFilterEditor
            v-else-if="overlayMode === 'editor' && draftDefinition"
            :definition="draftDefinition"
            :model-value="draft?.value ?? null"
            :label="draft?.label"
            :can-confirm="canConfirm"
            @update:model-value="onDraftValue"
            @update:label="onDraftLabel"
            @confirm="confirmDraft"
            @cancel="closeEditor"
          >
            <template
              v-if="$slots.client"
              #client="slotProps"
            >
              <slot
                name="client"
                v-bind="slotProps"
              />
            </template>
          </DataTableFilterEditor>
        </div>
      </template>
    </UPopover>

    <!-- Mobile: drawer com seletor ou editor -->
    <template v-else>
      <UButton
        v-if="canAdd"
        color="neutral"
        variant="outline"
        icon="i-lucide-list-filter"
        :label="addLabel"
        data-testid="data-table-filter-add"
        @click="openSelector"
      />
      <UDrawer
        v-model:open="overlayOpen"
        :title="overlayTitle"
        :handle="false"
      >
        <template #content>
          <div
            class="min-w-0 w-full max-w-full overflow-x-hidden"
            data-testid="data-table-filter-overlay"
          >
            <DataTableFilterSelector
              v-if="overlayMode === 'selector'"
              :definitions="inactive"
              @select="startAdd"
            />
            <DataTableFilterEditor
              v-else-if="overlayMode === 'editor' && draftDefinition"
              :definition="draftDefinition"
              :model-value="draft?.value ?? null"
              :label="draft?.label"
              :can-confirm="canConfirm"
              @update:model-value="onDraftValue"
              @update:label="onDraftLabel"
              @confirm="confirmDraft"
              @cancel="closeEditor"
            >
              <template
                v-if="$slots.client"
                #client="slotProps"
              >
                <slot
                  name="client"
                  v-bind="slotProps"
                />
              </template>
            </DataTableFilterEditor>
          </div>
        </template>
      </UDrawer>
    </template>

    <UButton
      v-if="showClear && hasApplied"
      color="neutral"
      variant="ghost"
      :label="clearLabel"
      data-testid="data-table-filter-clear"
      @click="clear"
    />
  </div>
</template>
