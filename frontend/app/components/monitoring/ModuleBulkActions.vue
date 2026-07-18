<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type {
  FiscalPortfolioModuleKey,
  MonitoringFilterValue
} from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  moduleKey: FiscalPortfolioModuleKey
  selectedClientIds: number[]
  selectedCount: number
  filters: MonitoringFilterValue
  submodule?: string
}>(), {
  submodule: ''
})

const emit = defineEmits<{
  'availability-change': [available: boolean]
  'clear': []
  'refresh': []
}>()

const {
  canAssociateCategories,
  canTriggerSync,
  canCreateExport,
  enqueueing,
  exporting,
  enqueueReadUpdate,
  exportPortfolio
} = useMonitoringActions(computed(() => props.moduleKey))

const associateOpen = ref(false)
const busy = ref(false)
const actionState = computed(() => monitoringBulkActionState({
  moduleKey: props.moduleKey,
  selectedCount: props.selectedCount,
  canAssociate: canAssociateCategories.value,
  canEnqueue: canTriggerSync.value,
  canExport: canCreateExport.value
}))
const canAssociate = computed(() => actionState.value.associate)
const canEnqueue = computed(() => actionState.value.enqueue)
const canExport = computed(() => actionState.value.export)
const available = computed(() => actionState.value.available)

watch(available, value => emit('availability-change', value), { immediate: true })

async function enqueueSelected() {
  if (!props.selectedClientIds.length) return
  if (props.moduleKey === 'fgts' && !props.filters.competence) {
    useToast().add({
      title: 'Informe a competência (AAAA-MM) para consultar os selecionados.',
      color: 'warning'
    })
    return
  }

  busy.value = true
  let ok = 0
  let fail = 0
  try {
    for (const clientId of props.selectedClientIds) {
      const result = await enqueueReadUpdate({
        client_id: clientId,
        competence: props.filters.competence || undefined
      })
      if (result) ok++
      else fail++
    }
    useToast().add({
      title: 'Consultas enfileiradas',
      description: `${ok} ok${fail ? ` · ${fail} falha(s)` : ''} de ${props.selectedClientIds.length} cliente(s)`,
      color: fail && !ok ? 'error' : fail ? 'warning' : 'success'
    })
    if (ok) {
      emit('clear')
      emit('refresh')
    }
  } finally {
    busy.value = false
  }
}

async function exportSelected() {
  if (!props.selectedClientIds.length) return
  const batch = props.selectedClientIds.slice(0, 10)
  busy.value = true
  let ok = 0
  try {
    for (const clientId of batch) {
      if (await exportPortfolio({
        situation: props.filters.situation,
        competence: props.filters.competence,
        q: props.filters.q,
        submodule: props.submodule,
        client_id: clientId
      }, { navigate: false, silent: true })) ok++
    }
    useToast().add({
      title: ok ? 'Exportações enfileiradas' : 'Nenhuma exportação criada',
      description: props.selectedClientIds.length > 10
        ? `${ok} job(s) de até 10 (de ${props.selectedClientIds.length} selecionados). Veja em Exportações.`
        : `${ok} job(s) · veja em Exportações quando READY.`,
      color: ok ? 'success' : 'warning'
    })
    if (ok) emit('clear')
  } finally {
    busy.value = false
  }
}

const items = computed<DropdownMenuItem[][]>(() => {
  const actions: DropdownMenuItem[] = []
  const disabled = busy.value || enqueueing.value || exporting.value
  if (canAssociate.value) actions.push({
    label: 'Associar categorias',
    icon: 'i-lucide-tags',
    disabled,
    onSelect: () => { associateOpen.value = true }
  })
  if (canEnqueue.value) actions.push({
    label: 'Solicitar consulta',
    icon: 'i-lucide-cloud-download',
    disabled,
    onSelect: () => { void enqueueSelected() }
  })
  if (canExport.value) actions.push({
    label: 'Exportar selecionados',
    icon: 'i-lucide-download',
    disabled,
    onSelect: () => { void exportSelected() }
  })
  return [actions, [{
    label: 'Limpar seleção',
    icon: 'i-lucide-x',
    disabled,
    onSelect: () => emit('clear')
  }]].filter(group => group.length > 0)
})
</script>

<template>
  <div
    v-if="actionState.visible"
    data-testid="fiscal-bulk-actions"
  >
    <UDropdownMenu
      :items="items"
      :content="{ align: 'start' }"
    >
      <UButton
        color="neutral"
        variant="subtle"
        icon="i-lucide-list-checks"
        label="Ações"
        aria-label="Ações em massa"
        :ui="{ label: 'hidden sm:inline' }"
        :loading="busy || enqueueing || exporting"
        data-testid="bulk-actions-menu"
      >
        <template #trailing>
          <UKbd>{{ selectedCount }}</UKbd>
        </template>
      </UButton>
    </UDropdownMenu>

    <FiscalAssociateCategoriesModal
      v-if="canAssociate"
      v-model:open="associateOpen"
      :module-key="moduleKey"
      :default-client-ids="selectedClientIds"
      @success="() => { emit('clear'); emit('refresh') }"
    />
  </div>
</template>
