<script setup lang="ts">
const props = defineProps<{
  selectedClientIds: number[]
  selectedCount: number
  year: number
}>()

const emit = defineEmits<{
  clear: []
  refresh: []
}>()

const { batchAutomatic, requestConsult } = usePgmeiMonitoring()
const toast = useToast()
const savingAutomatic = ref(false)
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

async function updateAutomatic(automaticRequested: boolean) {
  if (savingAutomatic.value || !validateSelection()) return
  savingAutomatic.value = true
  try {
    const updated = await batchAutomatic(props.selectedClientIds, automaticRequested)
    toast.add({
      title: `${updated.length} preferência(s) atualizada(s).`,
      description: 'Modo template: nenhum envio foi realizado.',
      color: 'success'
    })
    emit('clear')
    emit('refresh')
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Nenhuma preferência do lote foi alterada.'),
      color: 'error'
    })
  } finally {
    savingAutomatic.value = false
  }
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
      label="Consultar dívida"
      :disabled="savingAutomatic"
      @click="openQueryConfirmation"
    >
      <template #trailing>
        <UKbd>{{ selectedCount }}</UKbd>
      </template>
    </UButton>
    <UButton
      size="sm"
      color="primary"
      variant="soft"
      icon="i-lucide-toggle-right"
      label="Ligar automático"
      :loading="savingAutomatic"
      @click="updateAutomatic(true)"
    />
    <UButton
      size="sm"
      color="neutral"
      variant="outline"
      icon="i-lucide-toggle-left"
      label="Desligar"
      :disabled="savingAutomatic"
      @click="updateAutomatic(false)"
    />
  </div>

  <UModal
    v-model:open="confirmOpen"
    title="Confirmar consulta de dívida ativa"
    :description="`A consulta à SERPRO para ${selectedCount} cliente(s), ano ${year}, é explícita e pode ser faturável.`"
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-lg', footer: 'justify-end' }"
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
    <template #footer>
      <UButton
        color="neutral"
        variant="ghost"
        label="Cancelar"
        :disabled="querying"
        @click="() => { confirmOpen = false }"
      />
      <UButton
        color="primary"
        icon="i-lucide-refresh-cw"
        label="Confirmar consulta"
        :loading="querying"
        @click="confirmQuery"
      />
    </template>
  </UModal>
</template>
