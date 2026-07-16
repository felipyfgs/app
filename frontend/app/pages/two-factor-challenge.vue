<script setup lang="ts">
import * as z from 'zod'
import type { AuthFormField, FormSubmitEvent } from '@nuxt/ui'
import { unwrapMeUser, type MeIdentity } from '~/utils/permissions'

definePageMeta({ layout: 'auth' })

useSeoMeta({ title: 'Verificação em duas etapas · NFS-e ADN' })

const api = useApi()
const { refreshIdentity, user } = useSanctumAuth()
const useRecoveryCode = ref(false)
const error = ref('')
const loading = ref(false)

const otpSchema = z.object({
  // PinInput/OTP: normaliza array de dígitos → string no handler (evita InferInput unknown do preprocess)
  code: z.string().min(6, 'Informe o código de 6 dígitos').max(8)
})

const recoverySchema = z.object({
  recovery_code: z.string().min(1, 'Informe o código de recuperação')
})

type OtpSchema = z.output<typeof otpSchema>
type RecoverySchema = z.output<typeof recoverySchema>

const otpFields: AuthFormField[] = [{
  name: 'code',
  type: 'otp',
  label: 'Código do autenticador',
  length: 6,
  otp: true,
  required: true
}]

const recoveryFields: AuthFormField[] = [{
  name: 'recovery_code',
  type: 'text',
  label: 'Código de recuperação',
  placeholder: 'XXXX-XXXX',
  required: true,
  autocomplete: 'one-time-code'
}]

async function onSubmitOtp(event: FormSubmitEvent<OtpSchema>) {
  const raw = event.data.code as unknown
  const code = (Array.isArray(raw) ? raw.join('') : String(raw ?? '')).replace(/\s/g, '')
  await submit({ code })
}

async function onSubmitRecovery(event: FormSubmitEvent<RecoverySchema>) {
  await submit({ recovery_code: event.data.recovery_code.trim() })
}

async function submit(body: { code?: string, recovery_code?: string }) {
  error.value = ''
  loading.value = true
  try {
    await api.twoFactor.challenge(body)
    await refreshIdentity()
    const identity = unwrapMeUser(user.value as MeIdentity)
    await navigateTo(identity?.role === 'OPERATOR' ? '/work' : '/')
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Código inválido ou expirado.')
  } finally {
    loading.value = false
  }
}

function toggleMode() {
  useRecoveryCode.value = !useRecoveryCode.value
  error.value = ''
}
</script>

<template>
  <div class="space-y-6">
    <UPageCard
      variant="subtle"
      class="w-full"
      :ui="{ container: 'sm:p-8' }"
    >
      <UAuthForm
        v-if="!useRecoveryCode"
        :schema="otpSchema"
        :fields="otpFields"
        :loading="loading"
        title="Verificação em duas etapas"
        description="Abra o aplicativo autenticador e informe o código de 6 dígitos."
        icon="i-lucide-shield-check"
        :submit="{
          label: 'Confirmar',
          color: 'primary',
          block: true,
          size: 'lg',
          loading
        }"
        @submit="onSubmitOtp"
      >
        <template #validation>
          <UAlert
            v-if="error"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :title="error"
          />
        </template>
        <template #footer>
          <UButton
            color="neutral"
            variant="link"
            block
            type="button"
            @click="toggleMode"
          >
            Usar código de recuperação
          </UButton>
        </template>
      </UAuthForm>

      <UAuthForm
        v-else
        :schema="recoverySchema"
        :fields="recoveryFields"
        :loading="loading"
        title="Código de recuperação"
        description="Use um dos códigos salvos quando configurou o segundo fator."
        icon="i-lucide-key-round"
        :submit="{
          label: 'Confirmar',
          color: 'primary',
          block: true,
          size: 'lg',
          loading
        }"
        @submit="onSubmitRecovery"
      >
        <template #validation>
          <UAlert
            v-if="error"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :title="error"
          />
        </template>
        <template #footer>
          <UButton
            color="neutral"
            variant="link"
            block
            type="button"
            @click="toggleMode"
          >
            Usar aplicativo autenticador
          </UButton>
        </template>
      </UAuthForm>
    </UPageCard>
  </div>
</template>
