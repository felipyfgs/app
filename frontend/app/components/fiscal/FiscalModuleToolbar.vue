<script setup lang="ts">
/**
 * Toolbar de filtros da carteira — busca, situação, competência, submódulo,
 * filtros especializados (slot) e exportação. Estado reproduzível na URL via pai.
 */
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
    class="flex w-full min-w-0 flex-wrap items-center gap-2"
    data-testid="fiscal-module-toolbar"
  >
    <UInput
      v-if="showSearch"
      :model-value="qModel"
      icon="i-lucide-search"
      :placeholder="searchPlaceholder"
      class="w-40 sm:w-56"
      aria-label="Buscar por razão social ou CNPJ"
      data-testid="fiscal-filter-q"
      @update:model-value="onQInput"
      @keyup.enter="emit('update:q', qModel)"
    />

    <USelect
      v-if="showSituation"
      v-model="situationModel"
      :items="situationItems"
      value-key="value"
      class="w-40"
      aria-label="Filtrar por situação"
      data-testid="fiscal-filter-situation"
    />

    <UInput
      v-if="showCompetence"
      v-model="competenceModel"
      placeholder="AAAA-MM"
      class="w-28"
      aria-label="Filtrar por competência"
      data-testid="fiscal-filter-competence"
    />

    <USelect
      v-if="showSubmodule && submoduleItems?.length"
      v-model="submoduleModel"
      :items="submoduleItems"
      value-key="value"
      class="w-40"
      aria-label="Filtrar por submódulo"
      data-testid="fiscal-filter-submodule"
    />

    <USelect
      v-if="showDeliveryStatus && deliveryStatusItems?.length"
      v-model="deliveryModel"
      :items="deliveryStatusItems"
      value-key="value"
      class="w-40"
      aria-label="Filtrar por entrega"
      data-testid="fiscal-filter-delivery"
    />

    <FiscalClientPicker
      v-if="showClientPicker"
      search-mode="select"
      class="w-52 sm:w-64"
      @update:model-value="emit('update:clientId', $event)"
    />

    <slot name="filters" />

    <UButton
      color="neutral"
      variant="ghost"
      icon="i-lucide-refresh-cw"
      label="Atualizar"
      :loading="loading"
      data-testid="fiscal-filter-refresh"
      @click="emit('refresh')"
    />

    <UButton
      v-if="showExport && canExport"
      color="neutral"
      variant="outline"
      icon="i-lucide-download"
      label="Exportar"
      data-testid="fiscal-filter-export"
      @click="emit('export')"
    />

    <span class="ms-auto text-xs text-muted tabular-nums">
      {{ total }} registro(s)
    </span>
  </div>
</template>
