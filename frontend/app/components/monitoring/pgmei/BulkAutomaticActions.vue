<script setup lang="ts">
import { apiErrorMessage } from '~/utils/api-error'

const props = defineProps<{
  selectedClientIds: number[]
  selectedCount: number
}>()

const emit = defineEmits<{
  clear: []
  refresh: []
}>()

const { fiscal } = useApi()
const toast = useToast()
const saving = ref(false)

async function update(automaticRequested: boolean) {
  if (saving.value || props.selectedCount < 1) return
  if (props.selectedClientIds.length > 100) {
    toast.add({ title: 'Selecione no máximo 100 clientes.', color: 'warning' })
    return
  }

  saving.value = true
  try {
    const response = await fiscal.pgmei.communication.updateBulk({
      client_ids: props.selectedClientIds,
      automatic_requested: automaticRequested
    })
    const updated = response.updated_count ?? response.data.length
    toast.add({
      title: `${updated} preferência(s) PGMEI atualizada(s).`,
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
    saving.value = false
  }
}
</script>

<template>
  <div
    v-if="selectedCount > 0"
    class="flex items-center gap-1.5"
    data-testid="pgmei-bulk-automatic-actions"
  >
    <UButton
      size="sm"
      color="primary"
      variant="soft"
      icon="i-lucide-toggle-right"
      label="Ligar automático"
      :loading="saving"
      @click="update(true)"
    >
      <template #trailing>
        <UKbd>{{ selectedCount }}</UKbd>
      </template>
    </UButton>
    <UButton
      size="sm"
      color="neutral"
      variant="outline"
      icon="i-lucide-toggle-left"
      label="Desligar"
      :disabled="saving"
      @click="update(false)"
    />
  </div>
</template>
