<script setup lang="ts">
import type { LoginResponse } from '~/types/api'
import type { MeIdentity } from '~/utils/permissions'

definePageMeta({ layout: 'auth' })

const { login, refreshIdentity, user } = useSanctumAuth()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    const response = await login({ email: email.value, password: password.value }, false) as LoginResponse
    if (response?.two_factor) {
      await navigateTo('/two-factor-challenge')
      return
    }

    await refreshIdentity()
    const identity = unwrapMeUser(user.value as MeIdentity)
    await navigateTo(identity?.requires_two_factor_setup ? '/two-factor/setup' : '/')
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Credenciais inválidas ou sessão não iniciada.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <div class="space-y-1">
        <div class="flex items-center gap-2 mb-2">
          <UIcon name="i-lucide-receipt" class="size-6 text-primary" />
          <span class="font-semibold">NFS-e ADN</span>
        </div>
        <h1 class="text-lg font-semibold">
          Entrar
        </h1>
        <p class="text-sm text-muted">
          Acesso exclusivo para funcionários do escritório
        </p>
      </div>
    </template>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <UFormField label="E-mail">
        <UInput
          v-model="email"
          type="email"
          autocomplete="username"
          class="w-full"
          required
        />
      </UFormField>
      <UFormField label="Senha">
        <UInput
          v-model="password"
          type="password"
          autocomplete="current-password"
          class="w-full"
          required
        />
      </UFormField>
      <UAlert v-if="error" color="error" :title="error" />
      <UButton type="submit" block :loading="loading">
        Entrar
      </UButton>
    </form>
  </UCard>
</template>
