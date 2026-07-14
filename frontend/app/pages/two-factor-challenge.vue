<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const api = useApi()
const { refreshIdentity } = useSanctumAuth()
const useRecoveryCode = ref(false)
const value = ref('')
const error = ref('')
const loading = ref(false)

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    await api.twoFactor.challenge(useRecoveryCode.value
      ? { recovery_code: value.value.trim() }
      : { code: value.value.replace(/\s/g, '') })
    await refreshIdentity()
    await navigateTo('/')
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Código inválido ou expirado.')
  } finally {
    loading.value = false
  }
}

function toggleMode() {
  useRecoveryCode.value = !useRecoveryCode.value
  value.value = ''
  error.value = ''
}
</script>

<template>
  <UCard>
    <template #header>
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-shield-check" class="size-6 text-primary" />
          <h1 class="text-lg font-semibold">
            Verificação em duas etapas
          </h1>
        </div>
        <p class="text-sm text-muted">
          {{ useRecoveryCode ? 'Informe um código de recuperação.' : 'Informe o código do aplicativo autenticador.' }}
        </p>
      </div>
    </template>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <UFormField :label="useRecoveryCode ? 'Código de recuperação' : 'Código de 6 dígitos'">
        <UInput
          v-model="value"
          :inputmode="useRecoveryCode ? 'text' : 'numeric'"
          :maxlength="useRecoveryCode ? undefined : 6"
          autocomplete="one-time-code"
          class="w-full"
          autofocus
          required
        />
      </UFormField>
      <UAlert v-if="error" color="error" :title="error" />
      <UButton type="submit" block :loading="loading">
        Confirmar
      </UButton>
      <UButton
        color="neutral"
        variant="link"
        block
        type="button"
        @click="toggleMode"
      >
        {{ useRecoveryCode ? 'Usar aplicativo autenticador' : 'Usar código de recuperação' }}
      </UButton>
    </form>
  </UCard>
</template>
