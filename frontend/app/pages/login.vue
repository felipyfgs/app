<script setup lang="ts">
import * as z from 'zod'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import type { LoginResponse } from '~/types/api'
import type { MeIdentity } from '~/utils/permissions'

/**
 * Login no padrão oficial Nuxt UI:
 * https://ui.nuxt.com/docs/components/auth-form
 * (UPageCard + UAuthForm + schema Zod)
 */
definePageMeta({ layout: 'auth' })

useSeoMeta({
  title: 'Entrar · NFS-e ADN',
  description: 'Acesso interno ao painel de captura NFS-e via ADN'
})

const { login, refreshIdentity, user } = useSanctumAuth()
const error = ref('')
const loading = ref(false)

const schema = z.object({
  email: z.email('Informe um e-mail válido'),
  password: z.string().min(1, 'Informe a senha')
})

type Schema = z.output<typeof schema>

const fields: AuthFormField[] = [{
  name: 'email',
  type: 'email',
  label: 'E-mail',
  placeholder: 'voce@escritorio.com.br',
  required: true,
  autocomplete: 'username'
}, {
  name: 'password',
  type: 'password',
  label: 'Senha',
  placeholder: 'Sua senha',
  required: true,
  autocomplete: 'current-password'
}]

async function onSubmit(event: FormSubmitEvent<Schema>) {
  error.value = ''
  loading.value = true
  try {
    const response = await login({
      email: event.data.email,
      password: event.data.password
    }, false) as LoginResponse

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
  <div class="space-y-6">
    <div class="text-center lg:text-left space-y-1 lg:hidden">
      <h1 class="text-xl font-semibold text-highlighted">
        Entrar no painel
      </h1>
      <p class="text-sm text-muted">
        Uso exclusivo de funcionários do escritório
      </p>
    </div>

    <UPageCard
      variant="subtle"
      class="w-full"
      :ui="{
        container: 'sm:p-8'
      }"
    >
      <UAuthForm
        :schema="schema"
        :fields="fields"
        :loading="loading"
        title="Bem-vindo de volta"
        description="Entre com o e-mail corporativo do escritório."
        icon="i-lucide-lock-keyhole"
        :submit="{
          label: 'Entrar',
          color: 'primary',
          block: true,
          size: 'lg',
          loading
        }"
        @submit="onSubmit"
      >
        <template #validation>
          <UAlert
            v-if="error"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :title="error"
            :close="{ onClick: () => { error = '' } }"
          />
        </template>

        <template #footer>
          <p class="text-center text-xs text-muted text-pretty">
            Ambiente interno · não compartilhe credenciais · sessões protegidas por CSRF
          </p>
        </template>
      </UAuthForm>
    </UPageCard>

    <p class="text-center text-xs text-muted">
      Problemas de acesso? Fale com o administrador do escritório.
    </p>
  </div>
</template>
