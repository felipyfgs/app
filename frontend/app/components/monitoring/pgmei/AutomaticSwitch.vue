<script setup lang="ts">
import type { PgdasdCommunicationPreference } from '~/types/fiscal-modules'

const props = defineProps<{
  clientId: number
  preference?: PgdasdCommunicationPreference | null
  canManage?: boolean
}>()

const emit = defineEmits<{
  configure: []
  saved: [preference: PgdasdCommunicationPreference]
}>()

const { updatePreferences } = usePgmeiMonitoring()
const toast = useToast()
const saving = ref(false)

async function update(automaticRequested: boolean) {
  if (!props.canManage || saving.value) return
  if (automaticRequested && !pgdasdCanRequestAutomatic(props.preference)) {
    emit('configure')
    return
  }

  const preference = props.preference
  if (!preference) {
    emit('configure')
    return
  }

  saving.value = true
  try {
    const saved = await updatePreferences(props.clientId, {
      automatic_requested: automaticRequested,
      email_enabled: preference.email_enabled,
      whatsapp_enabled: preference.whatsapp_enabled,
      lock_version: preference.lock_version
    })
    emit('saved', saved)
    toast.add({
      title: automaticRequested ? 'Automático solicitado.' : 'Automático desativado.',
      description: 'Modo template: nenhum envio foi realizado.',
      color: 'success'
    })
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível atualizar a preferência.'),
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UTooltip
    :text="canManage
      ? 'Registra a intenção de envio automático do PGMEI. Nenhum envio será executado nesta etapa.'
      : 'Somente ADMIN ou OPERATOR pode alterar esta preferência.'"
  >
    <USwitch
      :model-value="preference?.automatic_requested === true"
      :loading="saving"
      :disabled="!canManage || saving"
      :aria-label="preference?.automatic_requested ? 'Desativar envio automático PGMEI' : 'Ativar envio automático PGMEI'"
      data-testid="pgmei-automatic-switch"
      @update:model-value="update"
    />
  </UTooltip>
</template>
