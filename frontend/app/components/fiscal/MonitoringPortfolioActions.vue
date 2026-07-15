<script setup lang="ts">
/**
 * Ações reais da carteira (não decorativas):
 * Adicionar cliente · Associar categorias · Solicitar consulta · Exportar
 * Visibilidade por papel (ADMIN/OPERATOR/VIEWER).
 */
import type { FiscalModuleKey } from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  moduleKey: FiscalModuleKey | string
  /** client_id do filtro da página (para enqueue). */
  clientId?: string | number | null
  competence?: string | null
  situation?: string | null
  q?: string | null
  submodule?: string | null
  /** Exibe botão de associação. */
  showAssociate?: boolean
  showEnqueue?: boolean
  showExport?: boolean
  showAddClient?: boolean
}>(), {
  showAssociate: true,
  showEnqueue: true,
  showExport: true,
  showAddClient: true
})

const emit = defineEmits<{
  refreshed: []
}>()

const {
  canManageClients,
  canAssociateCategories,
  canTriggerSync,
  canCreateExport,
  enqueueing,
  exporting,
  addClient,
  enqueueReadUpdate,
  exportPortfolio,
  moduleSupportsEnqueueRead,
  moduleSupportsPortfolioExport
} = useMonitoringActions(computed(() => props.moduleKey))

const associateOpen = ref(false)

const clientIdNum = computed(() => {
  const n = Number(props.clientId)
  return Number.isFinite(n) && n > 0 ? n : null
})

const showAdd = computed(() => props.showAddClient && canManageClients.value)
const showAssoc = computed(() => props.showAssociate && canAssociateCategories.value)
const showEnq = computed(() =>
  props.showEnqueue
  && canTriggerSync.value
  && moduleSupportsEnqueueRead.value
)
const showExp = computed(() =>
  props.showExport
  && canCreateExport.value
  && moduleSupportsPortfolioExport.value
)

async function onEnqueue() {
  if (!clientIdNum.value) {
    useToast().add({
      title: 'Informe o cliente (filtro Cliente ID) para solicitar consulta.',
      color: 'warning'
    })
    return
  }
  const result = await enqueueReadUpdate({
    client_id: clientIdNum.value,
    competence: props.competence || undefined
  })
  if (result) emit('refreshed')
}

async function onExport() {
  const ok = await exportPortfolio({
    situation: props.situation || undefined,
    competence: props.competence || undefined,
    q: props.q || undefined,
    submodule: props.submodule || undefined,
    client_id: clientIdNum.value || undefined
  })
  if (ok) emit('refreshed')
}
</script>

<template>
  <div
    class="flex flex-wrap items-center gap-2"
    data-testid="monitoring-portfolio-actions"
  >
    <UButton
      v-if="showAdd"
      color="primary"
      variant="soft"
      icon="i-lucide-user-plus"
      label="Adicionar cliente"
      size="sm"
      data-testid="action-add-client"
      @click="addClient"
    />
    <UButton
      v-if="showAssoc"
      color="neutral"
      variant="outline"
      icon="i-lucide-tags"
      label="Associar categorias"
      size="sm"
      data-testid="action-associate-categories"
      @click="() => { associateOpen = true }"
    />
    <UButton
      v-if="showEnq"
      color="neutral"
      variant="outline"
      icon="i-lucide-cloud-download"
      label="Solicitar consulta"
      size="sm"
      :loading="enqueueing"
      :disabled="!clientIdNum || (moduleKey === 'fgts' && !String(competence || '').trim())"
      data-testid="action-enqueue-read"
      @click="onEnqueue"
    />
    <UButton
      v-if="showExp"
      color="neutral"
      variant="outline"
      icon="i-lucide-download"
      label="Exportar carteira"
      size="sm"
      :loading="exporting"
      data-testid="action-export-portfolio"
      @click="onExport"
    />

    <FiscalAssociateCategoriesModal
      v-if="showAssoc"
      v-model:open="associateOpen"
      :module-key="moduleKey"
      :default-client-ids="clientIdNum ? [clientIdNum] : []"
      @success="emit('refreshed')"
    />
  </div>
</template>
