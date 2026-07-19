<script setup lang="ts">
/**
 * Onboarding inicial da plataforma (instalação vazia).
 * Token só no fragmento `#token=` → memória → body do POST.
 */
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import { consumeActivationTokenFromLocation } from '~/utils/activation'
import { apiErrorMessage } from '~/utils/api-error'

definePageMeta({ layout: 'auth' })

useSeoMeta({
  title: 'Configurar plataforma · NFS-e ADN',
  description: 'Primeiro administrador global da instalação'
})

const api = useApi()
const { refreshIdentity } = useSanctumAuth()

const token = ref<string | null>(null)
const checking = ref(true)
const available = ref(false)
const error = ref('')
const loading = ref(false)

const schema = z.object({
  organization_name: z.string('Informe o nome da organização').min(2, 'Informe o nome da organização').max(255),
  email: z.email('Informe um e-mail válido'),
  password: z.string('Informe a senha').min(8, 'Mínimo de 8 caracteres'),
  password_confirmation: z.string('Confirme a senha').min(1, 'Confirme a senha')
}).refine(d => d.password === d.password_confirmation, {
  message: 'As senhas não coincidem',
  path: ['password_confirmation']
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  organization_name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

onMounted(async () => {
  token.value = consumeActivationTokenFromLocation()
  try {
    const res = await api.onboarding.status()
    available.value = res.data?.available === true
  } catch {
    available.value = false
  } finally {
    checking.value = false
  }
})

const blocked = computed(() => {
  if (checking.value) return false
  if (!available.value) return true
  if (!token.value) return true
  return false
})

const blockReason = computed(() => {
  if (!available.value) {
    return 'Este onboarding não está disponível. Use o login se a plataforma já foi configurada.'
  }
  if (!token.value) {
    return 'Abra o link completo fornecido no deploy (com #token=…). O segredo não deve aparecer em favoritos ou histórico de servidor.'
  }
  return ''
})

async function onSubmit(_event: FormSubmitEvent<Schema>) {
  if (!token.value || !available.value) return
  error.value = ''
  loading.value = true
  try {
    const res = await api.onboarding.complete({
      organization_name: state.organization_name!.trim(),
      email: state.email!.trim(),
      password: state.password!,
      password_confirmation: state.password_confirmation!,
      onboarding_token: token.value
    })
    token.value = null
    state.password = ''
    state.password_confirmation = ''
    await refreshIdentity()
    const target = res.data?.redirect || '/admin/offices/new'
    await navigateTo(target)
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Não foi possível concluir o onboarding.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full min-w-0 space-y-6">
    <div class="space-y-1 text-center lg:hidden">
      <h1 class="text-xl font-semibold text-highlighted text-pretty">
        Configurar plataforma
      </h1>
      <p class="text-sm text-muted text-pretty">
        Crie o primeiro administrador global
      </p>
    </div>

    <UPageCard
      variant="subtle"
      class="w-full"
      :ui="{ container: 'sm:p-8 space-y-6' }"
      data-testid="onboarding-panel"
    >
      <div
        v-if="checking"
        class="space-y-3"
        role="status"
        aria-label="Verificando disponibilidade"
      >
        <USkeleton class="h-8 w-2/3" />
        <USkeleton class="h-10 w-full" />
        <USkeleton class="h-10 w-full" />
      </div>

      <UAlert
        v-else-if="blocked"
        color="warning"
        icon="i-lucide-shield-off"
        title="Onboarding indisponível"
        :description="blockReason"
        data-testid="onboarding-blocked"
      >
        <template #actions>
          <UButton
            to="/login"
            color="neutral"
            variant="soft"
            label="Ir para o login"
            data-testid="onboarding-to-login"
          />
        </template>
      </UAlert>

      <template v-else>
        <div class="space-y-1">
          <h2 class="text-lg font-semibold text-highlighted">
            Primeiro administrador
          </h2>
          <p class="text-sm text-muted">
            Organização, e-mail e senha. O token de deploy já foi lido e removido da URL.
          </p>
        </div>

        <UAlert
          v-if="error"
          color="error"
          variant="subtle"
          icon="i-lucide-circle-alert"
          :title="error"
          :close="{ onClick: () => { error = '' } }"
          data-testid="onboarding-error"
        />

        <UForm
          :schema="schema"
          :state="state"
          class="space-y-4"
          data-testid="onboarding-form"
          @submit="onSubmit"
        >
          <UFormField
            label="Nome da organização"
            name="organization_name"
            required
          >
            <UInput
              v-model="state.organization_name"
              autocomplete="organization"
              placeholder="Ex.: Inova Contábil"
              data-testid="onboarding-organization"
              class="w-full"
            />
          </UFormField>

          <UFormField
            label="E-mail do administrador"
            name="email"
            required
          >
            <UInput
              v-model="state.email"
              type="email"
              autocomplete="username"
              placeholder="admin@suaempresa.com.br"
              data-testid="onboarding-email"
              class="w-full"
            />
          </UFormField>

          <UFormField
            label="Senha"
            name="password"
            required
          >
            <UInput
              v-model="state.password"
              type="password"
              autocomplete="new-password"
              data-testid="onboarding-password"
              class="w-full"
            />
          </UFormField>

          <UFormField
            label="Confirmar senha"
            name="password_confirmation"
            required
          >
            <UInput
              v-model="state.password_confirmation"
              type="password"
              autocomplete="new-password"
              data-testid="onboarding-password-confirm"
              class="w-full"
            />
          </UFormField>

          <UButton
            type="submit"
            label="Concluir e entrar"
            color="primary"
            block
            size="lg"
            :loading="loading"
            icon="i-lucide-shield-check"
            data-testid="onboarding-submit"
          />
        </UForm>
      </template>
    </UPageCard>
  </div>
</template>
