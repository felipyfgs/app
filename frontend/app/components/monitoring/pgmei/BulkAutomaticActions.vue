<script setup lang="ts">
import { apiErrorMessage } from '~/utils/api-error'

const props = withDefaults(defineProps<{
  selectedClientIds: number[]
  selectedCount: number
  modelValue?: boolean
}>(), {
  modelValue: false
})

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
  <UTooltip
    :text="selectedCount > 0
      ? `Aplicar envio automático aos ${selectedCount} cliente(s) selecionado(s)`
      : 'Selecione ao menos um cliente para aplicar em massa'"
  >
    <USwitch
      :model-value="modelValue"
      :loading="saving"
      :disabled="selectedCount < 1 || saving"
      :aria-label="modelValue
        ? 'Desligar envio automático dos clientes selecionados'
        : 'Ligar envio automático dos clientes selecionados'"
      data-testid="pgmei-bulk-automatic-switch"
      @update:model-value="update"
    />
  </UTooltip>
</template>
