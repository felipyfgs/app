<script setup lang="ts">
/**
 * Toolbar de lista (customers.vue): busca + chips na mesma faixa; ações à direita.
 * Situação e demais campos vêm como chips via DataTableFilter; busca com debounce/Enter.
 * Presets nomeados (surface) — salvar / aplicar / gerenciar.
 */
import type { DataTableFilterModel } from '~/types/data-table-filter'
import type {
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import type {
  SavedFilterVisibility,
  SavedListFilter
} from '~/types/saved-list-filters'
import {
  countActiveMonitoringFilters,
  modelsToMonitoringFilters,
  monitoringFieldsToDefinitions,
  monitoringFiltersToModels,
  normalizeMonitoringFilters,
  resetMonitoringFilters,
  resolveMonitoringFilterFields
} from '~/utils/monitoring-filters'
import {
  hasActiveMonitoringFiltersForSave,
  monitoringFiltersToPayload,
  monitoringPayloadToFilters
} from '~/utils/saved-list-filters'
import { canCreateExport } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'
import { useApi } from '~/composables/useApi'
import { useDashboard } from '~/composables/useDashboard'
import DataTableFilterRoot from '~/components/data-table-filter/Root.vue'
import DataTableFilterSaveFilterModal from '~/components/data-table-filter/SaveFilterModal.vue'
import DataTableFilterSavedFiltersMenu from '~/components/data-table-filter/SavedFiltersMenu.vue'
import DataTableFilterManageSavedFiltersModal from '~/components/data-table-filter/ManageSavedFiltersModal.vue'

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
  /**
   * Surface estável de presets (ex. monitoring.installments).
   * Sem surface, ações de filtros salvos ficam ocultas.
   */
  surface?: string | null
  /** Override: VIEWER não publica (default: role ADMIN|OPERATOR). */
  canShareFilters?: boolean
}>(), {
  filterConfig: () => ({}),
  showExport: false,
  canExport: false,
  showTotal: true,
  total: 0,
  resetKey: 0,
  surface: null,
  canShareFilters: undefined
})

const emit = defineEmits<{
  'quick-filter-change': [filters: MonitoringFilterValue]
  'apply-filters': [filters: MonitoringFilterValue]
  'reset-filters': [filters: MonitoringFilterValue]
  'refresh': []
  'export': []
}>()

const api = useApi()
const toast = useToast() // auto-import Nuxt UI
const { me } = useDashboard()

const config = computed<MonitoringFilterConfig>(() => props.filterConfig || {})
const showSearch = computed(() => config.value.search !== false)
const structuredFields = computed(() => resolveMonitoringFilterFields(config.value))
const definitions = computed(() => monitoringFieldsToDefinitions(structuredFields.value))
const hasStructuredFilters = computed(() => definitions.value.length > 0)

const savedFiltersEnabled = computed(() => Boolean(props.surface && String(props.surface).trim()))

const canShare = computed(() => {
  if (typeof props.canShareFilters === 'boolean') return props.canShareFilters
  return canCreateExport(me.value)
})

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

// ── Presets ──────────────────────────────────────────────────────────────
const presets = ref<SavedListFilter[]>([])
const presetsLoaded = ref(false)
const presetsLoading = ref(false)
const saveOpen = ref(false)
const manageOpen = ref(false)
const saveLoading = ref(false)
const saveError = ref<string | null>(null)
const manageError = ref<string | null>(null)
const actingId = ref<number | null>(null)

const canSavePreset = computed(() =>
  savedFiltersEnabled.value
  && hasActiveMonitoringFiltersForSave(appliedFilters.value, config.value)
)

function clearPresetCache() {
  presets.value = []
  presetsLoaded.value = false
  presetsLoading.value = false
  saveOpen.value = false
  manageOpen.value = false
  saveError.value = null
  manageError.value = null
  actingId.value = null
}

watch(
  () => props.resetKey,
  (next, prev) => {
    if (prev === undefined) return
    if (next === prev) return
    clientLabelCache.value = null
    qDraft.value = ''
    clearPresetCache()
  }
)

watch(
  () => props.surface,
  (next, prev) => {
    if (prev === undefined) return
    if (next === prev) return
    clearPresetCache()
  }
)

async function loadPresets(force = false) {
  if (!savedFiltersEnabled.value || !props.surface) return
  if (presetsLoading.value) return
  if (presetsLoaded.value && !force) return

  presetsLoading.value = true
  manageError.value = null
  try {
    const res = await api.savedListFilters.list({ surface: props.surface })
    presets.value = Array.isArray(res?.data) ? res.data : []
    presetsLoaded.value = true
  } catch (error) {
    manageError.value = apiErrorMessage(error, 'Falha ao carregar filtros salvos.')
    presets.value = []
  } finally {
    presetsLoading.value = false
  }
}

function onSavedMenuOpen() {
  void loadPresets()
}

function applyPreset(filter: SavedListFilter) {
  if (!props.surface || filter.surface !== props.surface) {
    toast.add({
      title: 'Filtro incompatível',
      description: 'Este preset não pertence a esta lista.',
      color: 'warning'
    })
    return
  }

  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }

  const next = monitoringPayloadToFilters(filter.payload, config.value)
  qDraft.value = next.q

  // Preserva rótulo de cliente do chip se vier no payload.
  const models = Array.isArray((filter.payload as { filters?: DataTableFilterModel[] })?.filters)
    ? (filter.payload as { filters: DataTableFilterModel[] }).filters
    : []
  const clientModel = models.find(model => model.key === 'clientId')
  clientLabelCache.value = clientModel?.label || null

  // Uma única emissão → página 1 + limpa seleção no host.
  emit('apply-filters', next)
}

async function onSaveConfirm(payload: { name: string, share: boolean }) {
  if (!props.surface) return
  saveLoading.value = true
  saveError.value = null
  try {
    const bodyPayload = monitoringFiltersToPayload(
      appliedFilters.value,
      config.value,
      clientLabelCache.value
    )
    const res = await api.savedListFilters.create({
      surface: props.surface,
      name: payload.name,
      visibility: payload.share ? 'office' : 'personal',
      payload: bodyPayload,
      schema_version: 1
    })
    if (res?.data) {
      presets.value = [
        res.data,
        ...presets.value.filter(item => item.id !== res.data.id)
      ]
      presetsLoaded.value = true
    } else {
      await loadPresets(true)
    }
    saveOpen.value = false
    toast.add({
      title: 'Filtro salvo',
      description: payload.share ? 'Compartilhado com o escritório.' : 'Disponível em Meus filtros.',
      color: 'success'
    })
  } catch (error) {
    saveError.value = apiErrorMessage(error, 'Não foi possível salvar o filtro.')
  } finally {
    saveLoading.value = false
  }
}

async function onRename(payload: { id: number, name: string }) {
  actingId.value = payload.id
  manageError.value = null
  try {
    const res = await api.savedListFilters.update(payload.id, { name: payload.name })
    if (res?.data) {
      presets.value = presets.value.map(item =>
        item.id === payload.id ? res.data : item
      )
    }
  } catch (error) {
    manageError.value = apiErrorMessage(error, 'Falha ao renomear.')
  } finally {
    actingId.value = null
  }
}

async function onToggleShare(payload: { id: number, visibility: SavedFilterVisibility }) {
  actingId.value = payload.id
  manageError.value = null
  try {
    const res = await api.savedListFilters.update(payload.id, {
      visibility: payload.visibility
    })
    if (res?.data) {
      presets.value = presets.value.map(item =>
        item.id === payload.id ? res.data : item
      )
    }
  } catch (error) {
    manageError.value = apiErrorMessage(error, 'Falha ao alterar compartilhamento.')
  } finally {
    actingId.value = null
  }
}

async function onDeletePreset(payload: { id: number }) {
  actingId.value = payload.id
  manageError.value = null
  try {
    await api.savedListFilters.delete(payload.id)
    presets.value = presets.value.filter(item => item.id !== payload.id)
  } catch (error) {
    manageError.value = apiErrorMessage(error, 'Falha ao excluir filtro.')
  } finally {
    actingId.value = null
  }
}

function openManage() {
  manageOpen.value = true
  void loadPresets(true)
}

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
    <!--
      customers.vue: busca à esquerda (larga); filtros + ações à direita.
    -->
    <div class="flex flex-wrap items-center justify-between gap-1.5">
      <UInput
        v-if="showSearch"
        :model-value="qDraft"
        icon="i-lucide-search"
        :placeholder="searchPlaceholder"
        class="w-full min-w-0 flex-1 basis-full sm:basis-auto sm:min-w-[18rem] sm:max-w-2xl"
        :aria-label="searchAriaLabel"
        data-testid="fiscal-filter-q"
        @update:model-value="onQInput"
        @keyup.enter="submitQ"
      />

      <div class="flex min-w-0 max-w-full flex-wrap items-center justify-end gap-1.5 sm:ms-auto">
        <!-- customers.vue: bulk antes do filtro. -->
        <slot name="actions" />

        <div
          v-if="hasStructuredFilters || hasActiveStructured"
          class="flex min-w-0 max-w-full flex-wrap items-center justify-end gap-1.5"
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

        <template v-if="savedFiltersEnabled">
          <UButton
            v-if="canSavePreset"
            color="neutral"
            variant="outline"
            icon="i-lucide-save"
            label="Salvar"
            data-testid="save-filters-button"
            @click="() => { saveOpen = true }"
          />
          <DataTableFilterSavedFiltersMenu
            :items="presets"
            :loading="presetsLoading"
            @apply="applyPreset"
            @manage="openManage"
            @open="onSavedMenuOpen"
          />
        </template>

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

    <DataTableFilterSaveFilterModal
      v-if="savedFiltersEnabled"
      v-model:open="saveOpen"
      :can-share="canShare"
      :loading="saveLoading"
      :error="saveError"
      @confirm="onSaveConfirm"
    />

    <DataTableFilterManageSavedFiltersModal
      v-if="savedFiltersEnabled"
      v-model:open="manageOpen"
      :items="presets"
      :can-share="canShare"
      :loading="presetsLoading"
      :acting-id="actingId"
      :error="manageError"
      @rename="onRename"
      @toggle-share="onToggleShare"
      @delete="onDeletePreset"
    />
  </div>
</template>
