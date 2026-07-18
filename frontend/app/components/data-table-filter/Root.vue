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
  backToSelector,
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
/** Voltar à lista de campos ao adicionar (não no edit de chip). */
const showEditorBack = computed(() => draft.value?.mode === 'add')

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

/** Painel flutuante: um pouco mais largo no editor de cliente multi. */
const panelClass = computed(() => {
  const wide = overlayMode.value === 'editor' && draftDefinition.value?.kind === 'client'
  return [
    wide ? 'w-96' : 'w-80',
    'max-w-[min(24rem,calc(100vw-2rem))] min-w-0 overflow-hidden rounded-lg border border-default bg-default shadow-lg'
  ].join(' ')
})

function definitionFor(key: string) {
  return findDefinition(props.definitions, key)
}

function onDraftValue(value: string | number | boolean | null) {
  setDraftValue(value)
}

function onDraftValueTo(value: string | null) {
  setDraftValue(draft.value?.value ?? null, undefined, value)
}

function onDraftLabel(label: string | undefined) {
  setDraftValue(draft.value?.value ?? null, label)
}

/**
 * Popover content props.
 * Cliente multi: bloqueia dismiss por clique fora (Confirmar/Cancelar fecham).
 * Não usa onFocusOutside — travar focus quebra a barra de busca do picker.
 */
const popoverContent = computed(() => ({
  align: 'start' as const,
  side: 'bottom' as const,
  sideOffset: 6,
  onInteractOutside: (e: Event) => {
    if (overlayMode.value !== 'editor' || draftDefinition.value?.kind !== 'client') return
    e.preventDefault()
  }
}))
</script>

<template>
  <div
    class="flex min-w-0 flex-wrap items-center gap-1.5"
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

    <!-- Desktop: popover com seletor ou editor (portal + painel elevado). -->
    <UPopover
      v-if="!isMobile"
      v-model:open="overlayOpen"
      :portal="true"
      :content="popoverContent"
      :ui="{ content: 'p-0 bg-transparent ring-0 shadow-none' }"
    >
      <UButton
        v-if="canAdd"
        color="neutral"
        variant="outline"
        icon="i-lucide-funnel-plus"
        :label="addLabel"
        :aria-label="addLabel"
        :ui="{ label: 'hidden sm:inline' }"
        data-testid="data-table-filter-add"
      />
      <span
        v-else
        class="sr-only"
        data-testid="data-table-filter-anchor"
      >Filtros</span>
      <template #content>
        <div
          :class="panelClass"
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
            :value-to="draft?.valueTo ?? null"
            :label="draft?.label"
            :can-confirm="canConfirm"
            :show-back="showEditorBack"
            @update:model-value="onDraftValue"
            @update:value-to="onDraftValueTo"
            @update:label="onDraftLabel"
            @confirm="confirmDraft"
            @cancel="closeEditor"
            @back="backToSelector"
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
        icon="i-lucide-funnel-plus"
        :label="addLabel"
        :aria-label="addLabel"
        :ui="{ label: 'hidden sm:inline' }"
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
              :value-to="draft?.valueTo ?? null"
              :label="draft?.label"
              :can-confirm="canConfirm"
              :show-back="showEditorBack"
              @update:model-value="onDraftValue"
              @update:value-to="onDraftValueTo"
              @update:label="onDraftLabel"
              @confirm="confirmDraft"
              @cancel="closeEditor"
              @back="backToSelector"
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
      :aria-label="clearLabel"
      :ui="{ label: 'hidden sm:inline' }"
      icon="i-lucide-filter-x"
      data-testid="data-table-filter-clear"
      @click="clear"
    />
  </div>
</template>
