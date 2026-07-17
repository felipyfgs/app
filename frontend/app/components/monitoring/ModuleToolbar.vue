<script setup lang="ts">
/**
 * Toolbar de lista (customers.vue): busca dedicada + chips estruturados + ações.
 * Situação e demais campos vêm como chips via DataTableFilter; busca com debounce/Enter.
 */
import type { DataTableFilterModel } from '~/types/data-table-filter'
import type {
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import {
  countActiveMonitoringFilters,
  modelsToMonitoringFilters,
  monitoringFieldsToDefinitions,
  monitoringFiltersToModels,
  normalizeMonitoringFilters,
  resetMonitoringFilters,
  resolveMonitoringFilterFields
} from '~/utils/monitoring-filters'
import DataTableFilterRoot from '~/components/data-table-filter/Root.vue'

const props = withDefaults(defineProps<{
  filters: MonitoringFilterValue
  filterConfig?: MonitoringFilterConfig
  total?: number
  loading?: boolean
  showExport?: boolean
  canExport?: boolean
  /** Contagem no canto (default true). ModuleTable usa o footer. */
  showTotal?: boolean
  /** sessionEpoch — limpa rascunho/rótulos de cliente no núcleo de chips. */
  resetKey?: string | number | null
}>(), {
  filterConfig: () => ({}),
  showExport: false,
  canExport: false,
  showTotal: true,
  total: 0,
  resetKey: 0
})

const emit = defineEmits<{
  'quick-filter-change': [filters: MonitoringFilterValue]
  'apply-filters': [filters: MonitoringFilterValue]
  'reset-filters': [filters: MonitoringFilterValue]
  'refresh': []
  'export': []
}>()

const config = computed<MonitoringFilterConfig>(() => props.filterConfig || {})
const showSearch = computed(() => config.value.search !== false)
const structuredFields = computed(() => resolveMonitoringFilterFields(config.value))
const definitions = computed(() => monitoringFieldsToDefinitions(structuredFields.value))
const hasStructuredFilters = computed(() => definitions.value.length > 0)

const searchPlaceholder = computed(() => {
  const search = config.value.search
  if (search && typeof search === 'object' && search.placeholder) return search.placeholder
  return 'Buscar nome ou CNPJ…'
})

const searchAriaLabel = computed(() => {
  const search = config.value.search
  if (search && typeof search === 'object' && search.ariaLabel) return search.ariaLabel
  return 'Buscar por razão social ou CNPJ'
})

const appliedFilters = computed(() => normalizeMonitoringFilters(props.filters))

const qDraft = ref(appliedFilters.value.q)
watch(() => appliedFilters.value.q, (value) => {
  if (value !== qDraft.value) qDraft.value = value
})

/** Rótulo visual do cliente (preservado entre recargas do mesmo Office). */
const clientLabelCache = ref<string | null>(null)

watch(
  () => appliedFilters.value.clientId,
  (id) => {
    if (id == null) clientLabelCache.value = null
  }
)

watch(
  () => props.resetKey,
  (next, prev) => {
    if (prev === undefined) return
    if (next === prev) return
    clientLabelCache.value = null
    qDraft.value = ''
  }
)

const chipModels = computed(() =>
  monitoringFiltersToModels(
    appliedFilters.value,
    config.value,
    clientLabelCache.value
  )
)

const activeStructuredCount = computed(() =>
  countActiveMonitoringFilters(appliedFilters.value, config.value)
)
const hasActiveStructured = computed(() => activeStructuredCount.value > 0 || Boolean(qDraft.value.trim()))

function emitStructured(models: DataTableFilterModel[]) {
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  const next = modelsToMonitoringFilters(models, config.value, {
    ...appliedFilters.value,
    q: qDraft.value
  })
  const clientModel = models.find(model => model.key === 'clientId')
  if (clientModel?.label) {
    clientLabelCache.value = clientModel.label
  } else if (!clientModel) {
    clientLabelCache.value = null
  }
  emit('apply-filters', next)
}

function onChipsUpdate(models: DataTableFilterModel[]) {
  emitStructured(models)
}

function onChipsClear() {
  resetAllFilters()
}

function onClientPicked(client: {
  display_name?: string | null
  legal_name?: string | null
  name?: string | null
} | null) {
  if (!client) {
    clientLabelCache.value = null
    return
  }
  clientLabelCache.value = client.display_name || client.legal_name || client.name || null
}

function resetAllFilters() {
  const next = resetMonitoringFilters()
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  qDraft.value = next.q
  clientLabelCache.value = null
  emit('reset-filters', next)
}

let qDebounce: ReturnType<typeof setTimeout> | null = null
function onQInput(value: string | number) {
  const next = String(value ?? '')
  qDraft.value = next
  if (qDebounce) clearTimeout(qDebounce)
  qDebounce = setTimeout(() => {
    emit('quick-filter-change', normalizeMonitoringFilters({
      ...appliedFilters.value,
      q: next
    }))
  }, 320)
}

function submitQ() {
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  emit('quick-filter-change', normalizeMonitoringFilters({
    ...appliedFilters.value,
    q: qDraft.value
  }))
}

onBeforeUnmount(() => {
  if (qDebounce) clearTimeout(qDebounce)
})

const filterResetKey = computed(() => props.resetKey)
</script>

<template>
  <div
    class="w-full min-w-0"
    data-testid="page-toolbar"
  >
    <div class="flex flex-wrap items-center justify-between gap-1.5">
      <UInput
        v-if="showSearch"
        :model-value="qDraft"
        icon="i-lucide-search"
        :placeholder="searchPlaceholder"
        class="w-full sm:w-auto sm:max-w-sm"
        :aria-label="searchAriaLabel"
        data-testid="fiscal-filter-q"
        @update:model-value="onQInput"
        @keyup.enter="submitQ"
      />

      <div class="flex w-full flex-wrap items-center gap-1.5 sm:ms-auto sm:w-auto sm:justify-end">
        <!-- customers.vue: ação contextual vem antes do filtro principal. -->
        <slot name="actions" />

        <UTooltip text="Atualizar dados">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            :loading="loading"
            aria-label="Atualizar dados"
            data-testid="fiscal-filter-refresh"
            @click="emit('refresh')"
          />
        </UTooltip>

        <UButton
          v-if="showExport && canExport"
          color="neutral"
          variant="outline"
          icon="i-lucide-download"
          label="Exportar"
          data-testid="fiscal-filter-export"
          @click="emit('export')"
        />

        <!-- customers.vue: Exibir é sempre o último controle da lista. -->
        <slot name="trailing" />

        <span
          v-if="showTotal"
          class="text-right text-xs text-muted tabular-nums"
        >
          {{ total }} registro(s)
        </span>
      </div>
    </div>

    <!-- Chips entre toolbar e tabela (spec). -->
    <div
      v-if="hasStructuredFilters || hasActiveStructured"
      class="mt-1.5 flex min-w-0 w-full flex-wrap items-center gap-1.5"
      data-testid="fiscal-structured-filters"
    >
      <DataTableFilterRoot
        :definitions="definitions"
        :model-value="chipModels"
        :reset-key="filterResetKey"
        :show-clear="hasActiveStructured"
        data-testid="fiscal-filter-chips"
        @update:model-value="onChipsUpdate"
        @clear="onChipsClear"
      >
        <template #client="{ modelValue, update, select }">
          <FiscalClientPicker
            :model-value="modelValue"
            search-mode="select"
            placeholder="Selecione um cliente"
            class="w-full min-w-0"
            data-testid="fiscal-filter-client"
            @update:model-value="update"
            @select="(client) => { select(client); onClientPicked(client) }"
          />
        </template>
      </DataTableFilterRoot>
    </div>
  </div>
</template>
