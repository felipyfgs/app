<script setup lang="ts">
const props = defineProps<{
  selectedClientIds: number[]
  selectedCount: number
  year: number
  canUsePublicServices?: boolean
}>()

const emit = defineEmits<{
  clear: []
  refresh: []
  publicServices: [clientId: number]
}>()

const { requestConsult } = usePgmeiMonitoring()
const toast = useToast()
const querying = ref(false)
const confirmOpen = ref(false)

function validateSelection(): boolean {
  if (props.selectedCount < 1) return false
  if (props.selectedClientIds.length > 100) {
    toast.add({ title: 'Selecione no máximo 100 clientes.', color: 'warning' })
    return false
  }
  return true
}

function openQueryConfirmation() {
  if (!validateSelection()) return
  confirmOpen.value = true
}

async function confirmQuery() {
  if (querying.value || !validateSelection()) return
  querying.value = true
  try {
    const response = await requestConsult(props.selectedClientIds, props.year)
    const count = Number(response.enqueued_count ?? props.selectedClientIds.length)
    toast.add({
      title: `${count} consulta(s) PGMEI solicitada(s).`,
      description: `Ano-calendário ${props.year}. A atualização aparecerá após o processamento.`,
      color: 'success'
    })
    confirmOpen.value = false
    emit('clear')
    emit('refresh')
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar as consultas.'),
      color: 'error'
    })
  } finally {
    querying.value = false
  }
}
</script>

<template>
  <div
    v-if="selectedCount > 0"
    class="flex flex-wrap items-center gap-1.5"
    data-testid="pgmei-bulk-actions"
  >
    <UButton
      size="sm"
      color="primary"
      variant="soft"
      icon="i-lucide-refresh-cw"
      label="Consultar"
      :disabled="querying"
      data-testid="pgmei-bulk-consult"
      @click="openQueryConfirmation"
    >
      <template #trailing>
        <UKbd>{{ selectedCount }}</UKbd>
      </template>
    </UButton>
    <UButton
      v-if="selectedCount === 1 && canUsePublicServices"
      size="sm"
      color="neutral"
      variant="outline"
      icon="i-lucide-landmark"
      label="Serviços MEI"
      data-testid="mei-public-services-open"
      @click="emit('publicServices', selectedClientIds[0]!)"
    />
  </div>

  <ShellConfirmModal
    v-model:open="confirmOpen"
    title="Confirmar consulta de dívida ativa"
    :description="`A consulta à SERPRO para ${selectedCount} cliente(s), ano ${year}, é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-refresh-cw"
    :loading="querying"
    @confirm="confirmQuery"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente selecionado"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>
</template>
