<script setup lang="ts">
/**
 * Toolbar de lista no padrão customers.vue: busca à esquerda e controles essenciais
 * à direita. Os refinamentos ocultos usam rascunho local e só são aplicados no submit.
 */
import { fiscalSituationFilterItems } from '~/utils/fiscal-status'
import type { FiscalModuleFilterFormValue } from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  q?: string
  situation?: string
  competence?: string
  submodule?: string
  deliveryStatus?: string
  clientId?: number | string | null
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
  /** Contagem no canto (default true). ModuleTable usa o footer. */
  showTotal?: boolean
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
  showTotal: true,
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
  'apply': [filters: FiscalModuleFilterFormValue]
  'reset': [filters: FiscalModuleFilterFormValue]
  'refresh': []
  'export': []
}>()

const slots = useSlots()
const situationItems = fiscalSituationFilterItems(true)

const qDraft = ref(props.q || '')

watch(() => props.q, (value) => {
  const next = value || ''
  if (next !== qDraft.value) qDraft.value = next
})

const situationModel = computed({
  get: () => props.situation || 'all',
  set: (v: string) => emit('update:situation', v)
})
function normalizedClientId(value: number | string | null | undefined): number | null {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null
}

const competenceDraft = ref(props.competence || '')
const submoduleDraft = ref(props.submodule || '')
const deliveryDraft = ref(props.deliveryStatus || 'all')
const clientIdDraft = ref<number | null>(normalizedClientId(props.clientId))
const advancedQDraft = ref(props.q || '')
const advancedSituationDraft = ref(props.situation || 'all')

const hasAdvancedFilters = computed(() => Boolean(
  props.showCompetence
  || (props.showSubmodule && props.submoduleItems?.length)
  || (props.showDeliveryStatus && props.deliveryStatusItems?.length)
  || props.showClientPicker
  || slots.filters
))

const activeAdvancedFilterCount = computed(() => {
  let count = 0
  if (props.showSearch && String(props.q || '').trim()) count++
  if (props.showSituation && props.situation && props.situation !== 'all') count++
  if (props.showCompetence && String(props.competence || '').trim()) count++
  if (props.showSubmodule && props.submodule && props.submodule !== 'all') count++
  if (props.showDeliveryStatus && props.deliveryStatus && props.deliveryStatus !== 'all') count++
  if (props.showClientPicker && normalizedClientId(props.clientId)) count++
  return count
})
const hasActiveAdvancedFilters = computed(() => activeAdvancedFilterCount.value > 0)
const advancedFiltersLabel = computed(() => activeAdvancedFilterCount.value
  ? `Filtros (${activeAdvancedFilterCount.value})`
  : 'Filtros')
const advancedFiltersOpen = ref(false)

const competenceError = computed(() => {
  const value = competenceDraft.value.trim()
  if (!value || /^\d{4}-(0[1-9]|1[0-2])$/.test(value)) return undefined
  return 'Use uma competência válida no formato AAAA-MM.'
})

function syncAdvancedDraft() {
  advancedQDraft.value = props.q || ''
  advancedSituationDraft.value = props.situation || 'all'
  competenceDraft.value = props.competence || ''
  submoduleDraft.value = props.submodule || ''
  deliveryDraft.value = props.deliveryStatus || 'all'
  clientIdDraft.value = normalizedClientId(props.clientId)
}

function advancedFilterValue(): FiscalModuleFilterFormValue {
  return {
    q: props.showSearch ? advancedQDraft.value.trim() : (props.q || ''),
    situation: props.showSituation ? advancedSituationDraft.value : (props.situation || 'all'),
    competence: props.showCompetence ? competenceDraft.value.trim() : (props.competence || ''),
    submodule: props.showSubmodule ? submoduleDraft.value : (props.submodule || ''),
    deliveryStatus: props.showDeliveryStatus
      ? deliveryDraft.value
      : (props.deliveryStatus || 'all'),
    clientId: props.showClientPicker
      ? clientIdDraft.value
      : normalizedClientId(props.clientId)
  }
}

function toggleAdvancedFilters() {
  if (!advancedFiltersOpen.value) syncAdvancedDraft()
  advancedFiltersOpen.value = !advancedFiltersOpen.value
  if (!advancedFiltersOpen.value) syncAdvancedDraft()
}

function applyAdvancedFilters() {
  if (competenceError.value) return
  emit('apply', advancedFilterValue())
  advancedFiltersOpen.value = false
}

function resetAdvancedFilters() {
  advancedQDraft.value = ''
  advancedSituationDraft.value = 'all'
  competenceDraft.value = ''
  deliveryDraft.value = 'all'
  clientIdDraft.value = null

  // Submódulos estruturais (sem opção "all") pertencem à rota e são preservados.
  const allSubmodule = props.submoduleItems?.find(item => item.value === 'all')?.value
  submoduleDraft.value = allSubmodule || props.submodule || ''

  emit('reset', advancedFilterValue())
  advancedFiltersOpen.value = false
}

watch(
  () => [
    props.q,
    props.situation,
    props.competence,
    props.submodule,
    props.deliveryStatus,
    props.clientId
  ],
  () => {
    if (!advancedFiltersOpen.value) syncAdvancedDraft()
  }
)

let qDebounce: ReturnType<typeof setTimeout> | null = null
function onQInput(value: string | number) {
  const next = String(value ?? '')
  qDraft.value = next
  if (qDebounce) clearTimeout(qDebounce)
  qDebounce = setTimeout(() => {
    emit('update:q', next)
  }, 320)
}

function submitQ() {
  if (qDebounce) {
    clearTimeout(qDebounce)
    qDebounce = null
  }
  emit('update:q', qDraft.value)
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
        aria-label="Buscar por razão social ou CNPJ"
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
            <UFormField
              v-if="showSearch"
              label="Busca geral"
              hint="Razão social, nome fantasia ou CNPJ"
              class="lg:col-span-2"
            >
              <UInput
                v-model="advancedQDraft"
                icon="i-lucide-search"
                :placeholder="searchPlaceholder"
                class="w-full"
                aria-label="Busca geral nos clientes"
                data-testid="fiscal-advanced-filter-q"
              />
            </UFormField>

            <UFormField
              v-if="showSituation"
              label="Situação"
            >
              <USelect
                v-model="advancedSituationDraft"
                :items="situationItems"
                value-key="value"
                :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
                class="w-full"
                aria-label="Situação fiscal"
                data-testid="fiscal-advanced-filter-situation"
              />
            </UFormField>

            <UFormField
              v-if="showClientPicker"
              label="Cliente específico"
              hint="Restringe a carteira a um único cliente"
              class="lg:col-span-2"
            >
              <FiscalClientPicker
                v-model="clientIdDraft"
                search-mode="select"
                placeholder="Selecione um cliente"
                class="w-full"
              />
            </UFormField>

            <UFormField
              v-if="showCompetence"
              label="Competência"
              hint="Mês de apuração"
              :error="competenceError"
            >
              <UInput
                v-model="competenceDraft"
                type="month"
                placeholder="AAAA-MM"
                class="w-full"
                aria-label="Filtrar por competência"
                data-testid="fiscal-filter-competence"
              />
            </UFormField>

            <UFormField
              v-if="showSubmodule && submoduleItems?.length"
              label="Submódulo"
            >
              <USelect
                v-model="submoduleDraft"
                :items="submoduleItems"
                value-key="value"
                :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
                class="w-full"
                aria-label="Filtrar por submódulo"
                data-testid="fiscal-filter-submodule"
              />
            </UFormField>

            <UFormField
              v-if="showDeliveryStatus && deliveryStatusItems?.length"
              label="Status de entrega"
            >
              <USelect
                v-model="deliveryDraft"
                :items="deliveryStatusItems"
                value-key="value"
                :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
                class="w-full"
                aria-label="Filtrar por entrega"
                data-testid="fiscal-filter-delivery"
              />
            </UFormField>

            <slot name="filters" />
          </div>

          <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-default pt-3">
            <UButton
              type="button"
              color="neutral"
              variant="ghost"
              label="Limpar"
              data-testid="fiscal-filters-reset"
              @click="resetAdvancedFilters"
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
