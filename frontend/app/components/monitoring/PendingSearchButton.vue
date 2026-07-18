<script setup lang="ts">
import type { FiscalPortfolioModuleKey } from '~/types/fiscal-modules'
import { COMPACT_BUTTON_LABEL_UI } from '~/utils/list-filter-layout'

const props = withDefaults(defineProps<{
  moduleKey: FiscalPortfolioModuleKey
  submodule?: string
  competence?: string
  currentPageClientIds: number[]
  selectedClientIds?: number[]
}>(), {
  submodule: '',
  competence: '',
  selectedClientIds: () => []
})

const emit = defineEmits<{
  submitted: []
}>()

const { canTriggerSync } = useDashboard()
const {
  enqueueing,
  enqueueReadUpdate,
  moduleSupportsEnqueueRead
} = useMonitoringActions(computed(() => props.moduleKey))
const { requestConsult: requestPgmeiConsult } = usePgmeiMonitoring()
const toast = useToast()

const open = ref(false)
const queryingPgmei = ref(false)
const normalizedSubmodule = computed(() => String(props.submodule || '').trim().toUpperCase())
const isPgmei = computed(() =>
  props.moduleKey === 'simples_mei' && normalizedSubmodule.value === 'PGMEI'
)
const isMit = computed(() =>
  props.moduleKey === 'dctfweb' && normalizedSubmodule.value === 'MIT'
)
const supportsSearch = computed(() =>
  isPgmei.value || moduleSupportsEnqueueRead.value
)

function uniqueClientIds(values: number[]) {
  return [...new Set(values
    .map(Number)
    .filter(value => Number.isInteger(value) && value > 0))]
    .slice(0, 100)
}

const targets = computed(() => {
  const selected = uniqueClientIds(props.selectedClientIds)
  if (selected.length > 0) return selected
  return uniqueClientIds(props.currentPageClientIds)
})
const usesSelection = computed(() => uniqueClientIds(props.selectedClientIds).length > 0)
const requiresCompetence = computed(() => props.moduleKey === 'fgts' && !props.competence.trim())
const searching = computed(() => enqueueing.value || queryingPgmei.value)
const visible = computed(() =>
  canTriggerSync.value && supportsSearch.value && targets.value.length > 0
)
const scopeLabel = computed(() => usesSelection.value ? 'selecionado(s)' : 'da página atual')
const currentYear = computed(() => new Date().getFullYear())

function openConfirmation() {
  if (requiresCompetence.value) {
    toast.add({
      title: 'Informe a competência antes de buscar pendências do FGTS.',
      color: 'warning'
    })
    return
  }
  open.value = true
}

async function submitSearch() {
  if (searching.value || targets.value.length === 0) return

  let queued = 0
  let failed = 0

  if (isPgmei.value) {
    queryingPgmei.value = true
    try {
      const response = await requestPgmeiConsult(targets.value, currentYear.value)
      queued = Number(response.enqueued_count ?? targets.value.length)
      failed = Math.max(0, targets.value.length - queued)
    } catch {
      failed = targets.value.length
    } finally {
      queryingPgmei.value = false
    }
  } else {
    for (const clientId of targets.value) {
      const result = await enqueueReadUpdate({
        client_id: clientId,
        competence: props.competence || undefined,
        system_code: isMit.value ? 'INTEGRA_MIT' : undefined,
        service_code: isMit.value ? 'MIT' : undefined,
        operation_code: isMit.value ? 'CONSULTAR_APURACAO' : undefined,
        silent: true
      })
      if (result) queued += 1
      else failed += 1
    }
  }

  toast.add({
    title: queued > 0 ? 'Busca de pendências solicitada' : 'Nenhuma consulta foi solicitada',
    description: `${queued} enfileirada(s)${failed ? ` · ${failed} falha(s)` : ''}. Os resultados aparecerão após o processamento.`,
    color: queued > 0 ? (failed ? 'warning' : 'success') : 'error'
  })

  if (queued > 0) {
    open.value = false
    emit('submitted')
  }
}
</script>

<template>
  <UButton
    v-if="visible"
    color="primary"
    variant="soft"
    icon="i-lucide-scan-search"
    label="Buscar pendências"
    aria-label="Buscar pendências"
    :ui="COMPACT_BUTTON_LABEL_UI"
    :loading="searching"
    data-testid="monitoring-pending-search"
    @click="openConfirmation"
  >
    <template #trailing>
      <UKbd>{{ targets.length }}</UKbd>
    </template>
  </UButton>

  <UModal
    v-if="visible"
    v-model:open="open"
    title="Confirmar busca de pendências"
    :description="`Será solicitada uma consulta de leitura para ${targets.length} cliente(s) ${scopeLabel}.`"
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-lg', footer: 'justify-end' }"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma consulta por cliente"
      >
        <template #description>
          A ação é somente de leitura, mas pode consumir a franquia da integração. Abrir esta confirmação não realiza nenhuma chamada.
        </template>
      </UAlert>
    </template>

    <template #footer>
      <UButton
        color="neutral"
        variant="ghost"
        label="Cancelar"
        :disabled="searching"
        @click="() => { open = false }"
      />
      <UButton
        color="primary"
        icon="i-lucide-search-check"
        label="Confirmar busca"
        :loading="searching"
        data-testid="monitoring-pending-search-confirm"
        @click="submitSearch"
      />
    </template>
  </UModal>
</template>
