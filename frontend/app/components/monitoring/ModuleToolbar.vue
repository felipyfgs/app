<script setup lang="ts">
/**
 * Toolbar de lista no padrão customers.vue: busca/situação rápidas à esquerda/direita
 * e painel avançado recolhível com rascunho controlado e apply/reset atômicos.
 */
import { fiscalSituationFilterItems } from '~/utils/fiscal-status'
import type {
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import {
  countActiveMonitoringFilters,
  normalizeMonitoringFilters,
  resetMonitoringFilters
} from '~/utils/monitoring-filters'

const props = withDefaults(defineProps<{
  filters: MonitoringFilterValue
  filterConfig?: MonitoringFilterConfig
  total?: number
  loading?: boolean
  showExport?: boolean
  canExport?: boolean
  /** Contagem no canto (default true). ModuleTable usa o footer. */
  showTotal?: boolean
}>(), {
  filterConfig: () => ({}),
  showExport: false,
  canExport: false,
  showTotal: true,
  total: 0
})

const emit = defineEmits<{
  'quick-filter-change': [filters: MonitoringFilterValue]
  'apply-filters': [filters: MonitoringFilterValue]
  'reset-filters': [filters: MonitoringFilterValue]
  'refresh': []
  'export': []
}>()

const situationItems = fiscalSituationFilterItems(true)

const config = computed<MonitoringFilterConfig>(() => props.filterConfig || {})
const showSearch = computed(() => config.value.search !== false)
const showSituation = computed(() => config.value.situation !== false)
const advancedFields = computed(() => config.value.advanced || [])
const hasAdvancedFilters = computed(() => advancedFields.value.length > 0)

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

const situationModel = computed({
  get: () => appliedFilters.value.situation || 'all',
  set: (value: string) => {
    emit('quick-filter-change', normalizeMonitoringFilters({
      ...appliedFilters.value,
      situation: value || 'all'
    }))
  }
})

const advancedDraft = ref<MonitoringFilterValue>(normalizeMonitoringFilters(appliedFilters.value))
const advancedFiltersOpen = ref(false)

const activeAdvancedFilterCount = computed(() =>
  countActiveMonitoringFilters(appliedFilters.value, config.value)
)
const hasActiveAdvancedFilters = computed(() => activeAdvancedFilterCount.value > 0)
const advancedFiltersLabel = computed(() => activeAdvancedFilterCount.value
  ? `Filtros (${activeAdvancedFilterCount.value})`
  : 'Filtros')

const competenceError = computed(() => {
  const field = advancedFields.value.find(item => item.key === 'competence')
  if (!field) return undefined
  const value = String(advancedDraft.value.competence || '').trim()
  if (!value || /^\d{4}-(0[1-9]|1[0-2])$/.test(value)) return undefined
  return 'Use uma competência válida no formato AAAA-MM.'
})

function syncAdvancedDraft() {
  advancedDraft.value = normalizeMonitoringFilters(appliedFilters.value)
}

watch(advancedFiltersOpen, (open, wasOpen) => {
  if (open && !wasOpen) syncAdvancedDraft()
  if (!open && wasOpen) syncAdvancedDraft()
})

watch(
  () => appliedFilters.value,
  () => {
    if (!advancedFiltersOpen.value) syncAdvancedDraft()
  },
  { deep: true }
)

function toggleAdvancedFilters() {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

function applyAdvancedFilters() {
  if (competenceError.value) return
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  // Combina rascunho avançado com busca/situação mais recentes (não as do painel).
  emit('apply-filters', normalizeMonitoringFilters({
    ...advancedDraft.value,
    q: qDraft.value,
    situation: appliedFilters.value.situation
  }))
  advancedFiltersOpen.value = false
}

function resetAllFilters() {
  const next = resetMonitoringFilters()
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  advancedDraft.value = next
  qDraft.value = next.q
  emit('reset-filters', next)
  advancedFiltersOpen.value = false
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

        <USelect
          v-if="showSituation"
          v-model="situationModel"
          :items="situationItems"
          value-key="value"
          :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
          class="min-w-40 flex-1 sm:flex-none"
          aria-label="Filtrar por situação"
          data-testid="fiscal-filter-situation"
        />

        <!-- Na mesma faixa da toolbar (não em linha solta sobre a tabela). -->
        <UButton
          v-if="hasAdvancedFilters"
          color="neutral"
          :variant="hasActiveAdvancedFilters ? 'subtle' : 'outline'"
          icon="i-lucide-list-filter"
          :label="advancedFiltersLabel"
          :trailing-icon="advancedFiltersOpen ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
          aria-controls="fiscal-advanced-filters"
          :aria-expanded="advancedFiltersOpen"
          data-testid="advanced-filters-toggle"
          @click="toggleAdvancedFilters"
        />

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

    <UCollapsible
      v-if="hasAdvancedFilters"
      v-model:open="advancedFiltersOpen"
      :unmount-on-hide="false"
      class="w-full"
    >
      <template #content>
        <form
          id="fiscal-advanced-filters"
          class="mt-1.5 rounded-lg border border-default bg-elevated/25 p-3"
          data-testid="fiscal-advanced-filters"
          @submit.prevent="applyAdvancedFilters"
        >
          <div class="flex flex-wrap items-start justify-between gap-2 border-b border-default pb-3">
            <div>
              <p class="text-sm font-medium text-highlighted">
                Filtros avançados
              </p>
              <p class="mt-0.5 text-xs text-muted">
                Combine os campos e aplique de uma vez à carteira e aos indicadores.
              </p>
            </div>
            <span class="text-xs text-muted tabular-nums">
              {{ activeAdvancedFilterCount }} ativo(s)
            </span>
          </div>

          <div class="grid min-w-0 grid-cols-1 gap-3 pt-3 sm:grid-cols-2 lg:grid-cols-3">
            <template
              v-for="field in advancedFields"
              :key="field.key"
            >
              <UFormField
                v-if="field.kind === 'client'"
                :label="field.label"
                :hint="field.hint || 'Restringe a carteira a um único cliente'"
                class="lg:col-span-2"
              >
                <FiscalClientPicker
                  :model-value="advancedDraft.clientId"
                  search-mode="select"
                  placeholder="Selecione um cliente"
                  class="w-full"
                  @update:model-value="advancedDraft = normalizeMonitoringFilters({
                    ...advancedDraft,
                    clientId: $event
                  })"
                />
              </UFormField>

              <UFormField
                v-else-if="field.kind === 'month'"
                :label="field.label"
                :hint="field.hint || 'Mês de apuração'"
                :error="field.key === 'competence' ? competenceError : undefined"
              >
                <UInput
                  :model-value="String(advancedDraft[field.key] || '')"
                  type="month"
                  placeholder="AAAA-MM"
                  class="w-full"
                  :aria-label="field.label"
                  :data-testid="field.key === 'competence' ? 'fiscal-filter-competence' : undefined"
                  @update:model-value="advancedDraft = normalizeMonitoringFilters({
                    ...advancedDraft,
                    [field.key]: $event
                  })"
                />
              </UFormField>

              <UFormField
                v-else-if="field.kind === 'select'"
                :label="field.label"
                :hint="field.hint"
              >
                <USelect
                  :model-value="String(advancedDraft[field.key] || 'all')"
                  :items="field.items"
                  value-key="value"
                  :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
                  class="w-full"
                  :aria-label="field.label"
                  :data-testid="field.key === 'deliveryStatus'
                    ? 'fiscal-filter-delivery'
                    : field.key === 'paymentStatus'
                      ? 'guides-payment-status-filter'
                      : field.key === 'status'
                        ? 'fiscal-filter-status'
                        : undefined"
                  @update:model-value="advancedDraft = normalizeMonitoringFilters({
                    ...advancedDraft,
                    [field.key]: $event
                  })"
                />
              </UFormField>
            </template>
          </div>

          <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-default pt-3">
            <UButton
              type="button"
              color="neutral"
              variant="ghost"
              label="Limpar"
              data-testid="fiscal-filters-reset"
              @click="resetAllFilters"
            />
            <UButton
              type="submit"
              color="primary"
              label="Aplicar filtros"
              :disabled="Boolean(competenceError)"
              data-testid="fiscal-filters-apply"
            />
          </div>
        </form>
      </template>
    </UCollapsible>
  </div>
</template>
