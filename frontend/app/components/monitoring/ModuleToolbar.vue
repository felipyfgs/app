<script setup lang="ts">
/**
 * Toolbar de filtros da carteira — busca, situação, competência, submódulo,
 * filtros especializados (slot) e exportação. Estado reproduzível na URL via pai.
 */
import { useMediaQuery } from '@vueuse/core'
import { fiscalSituationFilterItems } from '~/utils/fiscal-status'

const props = withDefaults(defineProps<{
  q?: string
  situation?: string
  competence?: string
  submodule?: string
  deliveryStatus?: string
  total?: number
  loading?: boolean
  showSearch?: boolean
  showSituation?: boolean
  showCompetence?: boolean
  showSubmodule?: boolean
  showDeliveryStatus?: boolean
  showClientPicker?: boolean
  showExport?: boolean
  canExport?: boolean
  submoduleItems?: Array<{ label: string, value: string }>
  deliveryStatusItems?: Array<{ label: string, value: string }>
  searchPlaceholder?: string
}>(), {
  showSearch: true,
  showSituation: true,
  showCompetence: false,
  showSubmodule: false,
  showDeliveryStatus: false,
  showClientPicker: true,
  showExport: false,
  canExport: false,
  searchPlaceholder: 'Buscar nome ou CNPJ…',
  total: 0
})

const emit = defineEmits<{
  'update:q': [value: string]
  'update:situation': [value: string]
  'update:competence': [value: string]
  'update:submodule': [value: string]
  'update:deliveryStatus': [value: string]
  'update:clientId': [value: number | null]
  'refresh': []
  'export': []
}>()

const situationItems = fiscalSituationFilterItems(true)

const qModel = computed({
  get: () => props.q || '',
  set: (v: string) => emit('update:q', v)
})
const situationModel = computed({
  get: () => props.situation || 'all',
  set: (v: string) => emit('update:situation', v)
})
const competenceModel = computed({
  get: () => props.competence || '',
  set: (v: string) => emit('update:competence', v)
})
const submoduleModel = computed({
  get: () => props.submodule || '',
  set: (v: string) => emit('update:submodule', v)
})
const deliveryModel = computed({
  get: () => props.deliveryStatus || 'all',
  set: (v: string) => emit('update:deliveryStatus', v)
})

const mobileFiltersOpen = ref(false)
const desktopFilters = useMediaQuery('(min-width: 640px)', { ssrWidth: 1024 })
const filtersOpen = computed({
  get: () => desktopFilters.value || mobileFiltersOpen.value,
  set: (value: boolean) => {
    if (!desktopFilters.value) mobileFiltersOpen.value = value
  }
})

let qDebounce: ReturnType<typeof setTimeout> | null = null
function onQInput(value: string | number) {
  const next = String(value ?? '')
  if (qDebounce) clearTimeout(qDebounce)
  qDebounce = setTimeout(() => {
    emit('update:q', next)
  }, 320)
}

onBeforeUnmount(() => {
  if (qDebounce) clearTimeout(qDebounce)
})
</script>

<template>
  <div
    class="flex w-full min-w-0 flex-wrap items-center justify-between gap-1.5"
    data-testid="page-toolbar"
  >
    <UInput
      v-if="showSearch"
      :model-value="qModel"
      icon="i-lucide-search"
      :placeholder="searchPlaceholder"
      class="w-full sm:max-w-sm"
      aria-label="Buscar por razão social ou CNPJ"
      data-testid="fiscal-filter-q"
      @update:model-value="onQInput"
      @keyup.enter="emit('update:q', qModel)"
    />

    <UCollapsible
      v-model:open="filtersOpen"
      :unmount-on-hide="false"
      class="w-full sm:w-auto"
    >
      <UButton
        color="neutral"
        variant="outline"
        icon="i-lucide-list-filter"
        label="Filtros"
        :trailing-icon="filtersOpen ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
        class="w-full justify-between sm:hidden"
        data-testid="mobile-filters-toggle"
      />

      <template #content>
        <div class="grid min-w-0 grid-cols-1 gap-1.5 pt-1.5 sm:flex sm:flex-wrap sm:items-center sm:pt-0">
          <USelect
            v-if="showSituation"
            v-model="situationModel"
            :items="situationItems"
            value-key="value"
            :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
            class="w-full sm:min-w-40 sm:w-auto"
            aria-label="Filtrar por situação"
            data-testid="fiscal-filter-situation"
          />

          <UInput
            v-if="showCompetence"
            v-model="competenceModel"
            placeholder="AAAA-MM"
            class="w-full sm:w-28"
            aria-label="Filtrar por competência"
            data-testid="fiscal-filter-competence"
          />

          <USelect
            v-if="showSubmodule && submoduleItems?.length"
            v-model="submoduleModel"
            :items="submoduleItems"
            value-key="value"
            :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
            class="w-full sm:min-w-40 sm:w-auto"
            aria-label="Filtrar por submódulo"
            data-testid="fiscal-filter-submodule"
          />

          <USelect
            v-if="showDeliveryStatus && deliveryStatusItems?.length"
            v-model="deliveryModel"
            :items="deliveryStatusItems"
            value-key="value"
            :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
            class="w-full sm:min-w-40 sm:w-auto"
            aria-label="Filtrar por entrega"
            data-testid="fiscal-filter-delivery"
          />

          <FiscalClientPicker
            v-if="showClientPicker"
            search-mode="select"
            class="w-full sm:w-64"
            @update:model-value="emit('update:clientId', $event)"
          />

          <slot name="filters" />

          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            class="w-full justify-start sm:w-auto"
            data-testid="fiscal-filter-refresh"
            @click="emit('refresh')"
          />

          <UButton
            v-if="showExport && canExport"
            color="neutral"
            variant="outline"
            icon="i-lucide-download"
            label="Exportar"
            class="w-full justify-start sm:w-auto"
            data-testid="fiscal-filter-export"
            @click="emit('export')"
          />

          <span class="text-right text-xs text-muted tabular-nums sm:ms-auto">
            {{ total }} registro(s)
          </span>
        </div>
      </template>
    </UCollapsible>
  </div>
</template>
