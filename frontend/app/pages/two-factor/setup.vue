<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, StepperItem } from '@nuxt/ui'

/**
 * Setup 2FA em etapas: Senha → QR/código → Recuperação.
 * UForm + schemas + UStepper (não UAuthForm — multi-etapa com artefato de recuperação).
 */
definePageMeta({ layout: 'auth' })

useSeoMeta({ title: 'Configurar 2FA · NFS-e ADN' })

const api = useApi()
const { logout, refreshIdentity } = useSanctumAuth()
const toast = useToast()

const stageIndex = ref(0)
const error = ref('')
const loading = ref(false)
const qrSvg = ref('')
const recoveryCodes = ref<string[]>([])

const passwordState = reactive({ password: '' })
const codeState = reactive({ code: '' })

const passwordSchema = z.object({
  password: z.string().min(1, 'Informe a senha atual')
})

const codeSchema = z.object({
  code: z.string().min(6, 'Informe o código de 6 dígitos').max(8)
})

type PasswordSchema = z.output<typeof passwordSchema>
type CodeSchema = z.output<typeof codeSchema>

const qrSource = computed(() => qrSvg.value
  ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg.value)}`
  : '')

const steps = computed<StepperItem[]>(() => [
  {
    title: 'Senha',
    description: 'Confirme a identidade',
    icon: 'i-lucide-key-round',
    value: 0,
    disabled: stageIndex.value !== 0
  },
  {
    title: 'Autenticador',
    description: 'QR e código',
    icon: 'i-lucide-smartphone',
    value: 1,
    disabled: stageIndex.value < 1
  },
  {
    title: 'Recuperação',
    description: 'Códigos de backup',
    icon: 'i-lucide-shield-check',
    value: 2,
    disabled: stageIndex.value < 2
  }
])

async function onPasswordSubmit(_event: FormSubmitEvent<PasswordSchema>) {
  error.value = ''
  loading.value = true
  try {
    await api.twoFactor.confirmPassword(passwordState.password)
    await api.twoFactor.enable()
    qrSvg.value = (await api.twoFactor.qrCode()).svg
    passwordState.password = ''
    stageIndex.value = 1
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Não foi possível iniciar a configuração do 2FA.')
  } finally {
    loading.value = false
  }
}

async function onCodeSubmit(_event: FormSubmitEvent<CodeSchema>) {
  error.value = ''
  loading.value = true
  try {
    const code = String(codeState.code).replace(/\s/g, '')
    await api.twoFactor.confirm(code)
    recoveryCodes.value = await api.twoFactor.recoveryCodes()
    await refreshIdentity()
    codeState.code = ''
    stageIndex.value = 2
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Código inválido. Confira o autenticador e tente novamente.')
  } finally {
    loading.value = false
  }
}

async function copyRecoveryCodes() {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
    toast.add({ title: 'Códigos copiados', color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível copiar os códigos', color: 'warning' })
  }
}

async function onLogout() {
  await logout()
  await navigateTo('/login')
}
</script>

<template>
  <div class="space-y-6">
    <UPageCard
      variant="subtle"
      class="w-full"
      :ui="{ container: 'sm:p-8 space-y-6' }"
    >
      <div class="space-y-1 text-center sm:text-left">
        <div class="flex items-center justify-center gap-2 sm:justify-start">
          <UIcon name="i-lucide-shield-check" class="size-6 text-primary" />
          <h1 class="text-lg font-semibold text-highlighted">
            Proteja sua conta administrativa
          </h1>
        </div>
        <p class="text-sm text-muted">
          O 2FA é obrigatório antes de acessar funções administrativas do escritório.
        </p>
      </div>

      <UStepper
        v-model="stageIndex"
        :items="steps"
        class="w-full"
        color="primary"
        size="sm"
        disabled
        linear
        data-testid="two-factor-setup-stepper"
      />

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        icon="i-lucide-circle-alert"
        :title="error"
        class="mb-2"
        :close="{ onClick: () => { error = '' } }"
      />

      <!-- Etapa 1: senha -->
      <UForm
        v-if="stageIndex === 0"
        :schema="passwordSchema"
        :state="passwordState"
        class="space-y-4"
        data-testid="two-factor-setup-password"
        @submit="onPasswordSubmit"
      >
        <UFormField
          label="Confirme sua senha"
          name="password"
          required
        >
          <UInput
            v-model="passwordState.password"
            type="password"
            autocomplete="current-password"
            class="w-full"
            autofocus
            :disabled="loading"
          />
        </UFormField>
        <UButton
          type="submit"
          block
          size="lg"
          :loading="loading"
          label="Configurar autenticador"
          icon="i-lucide-shield"
        />
      </UForm>

      <!-- Etapa 2: QR + código -->
      <UForm
        v-else-if="stageIndex === 1"
        :schema="codeSchema"
        :state="codeState"
        class="space-y-4"
        data-testid="two-factor-setup-scan"
        @submit="onCodeSubmit"
      >
        <p class="text-sm text-muted">
          Escaneie o QR code no aplicativo autenticador e informe o código gerado.
        </p>
        <div
          v-if="qrSource"
          class="flex justify-center rounded-lg bg-white p-4 ring-1 ring-default"
        >
          <img
            :src="qrSource"
            alt="QR code para configurar o segundo fator"
            class="size-48"
          >
        </div>
        <UFormField
          label="Código de 6 dígitos"
          name="code"
          required
        >
          <UInput
            v-model="codeState.code"
            inputmode="numeric"
            maxlength="8"
            autocomplete="one-time-code"
            class="w-full"
            autofocus
            :disabled="loading"
          />
        </UFormField>
        <UButton
          type="submit"
          block
          size="lg"
          :loading="loading"
          label="Confirmar 2FA"
          icon="i-lucide-check"
        />
      </UForm>

      <!-- Etapa 3: códigos de recuperação -->
      <div
        v-else
        class="space-y-4"
        data-testid="two-factor-setup-recovery"
      >
        <UAlert
          color="success"
          variant="subtle"
          icon="i-lucide-circle-check"
          title="2FA ativado"
          description="Guarde estes códigos em local seguro. Cada código funciona uma única vez. Não compartilhe nem armazene em canal inseguro."
        />
        <ul
          aria-label="Códigos de recuperação"
          class="grid grid-cols-2 gap-2 rounded-lg bg-elevated p-3 font-mono text-sm"
        >
          <li
            v-for="recoveryCode in recoveryCodes"
            :key="recoveryCode"
          >
            {{ recoveryCode }}
          </li>
        </ul>
        <div class="flex flex-col gap-2 sm:flex-row">
          <UButton
            color="neutral"
            variant="outline"
            block
            icon="i-lucide-copy"
            label="Copiar códigos"
            @click="copyRecoveryCodes"
          />
          <UButton
            block
            to="/"
            icon="i-lucide-arrow-right"
            label="Continuar para o painel"
          />
        </div>
      </div>
    </UPageCard>

    <div class="text-center">
      <UButton
        color="neutral"
        variant="ghost"
        size="sm"
        icon="i-lucide-log-out"
        label="Sair e voltar ao login"
        @click="onLogout"
      />
    </div>
  </div>
</template>
