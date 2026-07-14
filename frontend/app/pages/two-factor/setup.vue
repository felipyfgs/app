<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const api = useApi()
const { logout, refreshIdentity } = useSanctumAuth()
const toast = useToast()
const stage = ref<'password' | 'scan' | 'recovery'>('password')
const password = ref('')
const code = ref('')
const qrSvg = ref('')
const recoveryCodes = ref<string[]>([])
const error = ref('')
const loading = ref(false)
const qrSource = computed(() => qrSvg.value
  ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg.value)}`
  : '')

async function startSetup() {
  error.value = ''
  loading.value = true
  try {
    await api.twoFactor.confirmPassword(password.value)
    await api.twoFactor.enable()
    qrSvg.value = (await api.twoFactor.qrCode()).svg
    stage.value = 'scan'
    password.value = ''
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Não foi possível iniciar a configuração do 2FA.')
  } finally {
    loading.value = false
  }
}

async function confirmSetup() {
  error.value = ''
  loading.value = true
  try {
    await api.twoFactor.confirm(code.value.replace(/\s/g, ''))
    recoveryCodes.value = await api.twoFactor.recoveryCodes()
    await refreshIdentity()
    stage.value = 'recovery'
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
  <UCard>
    <template #header>
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <UIcon name="i-lucide-shield-check" class="size-6 text-primary" />
          <h1 class="text-lg font-semibold">
            Proteja sua conta administrativa
          </h1>
        </div>
        <p class="text-sm text-muted">
          O 2FA é obrigatório antes de acessar funções administrativas.
        </p>
      </div>
    </template>

    <form v-if="stage === 'password'" class="space-y-4" @submit.prevent="startSetup">
      <UFormField label="Confirme sua senha">
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
        Configurar autenticador
      </UButton>
    </form>

    <form v-else-if="stage === 'scan'" class="space-y-4" @submit.prevent="confirmSetup">
      <p class="text-sm text-muted">
        Escaneie o QR code no aplicativo autenticador e informe o código gerado.
      </p>
      <div class="flex justify-center rounded-lg bg-white p-4">
        <img :src="qrSource" alt="QR code para configurar o segundo fator" class="size-48">
      </div>
      <UFormField label="Código de 6 dígitos">
        <UInput
          v-model="code"
          inputmode="numeric"
          maxlength="6"
          autocomplete="one-time-code"
          class="w-full"
          required
        />
      </UFormField>
      <UAlert v-if="error" color="error" :title="error" />
      <UButton type="submit" block :loading="loading">
        Confirmar 2FA
      </UButton>
    </form>

    <div v-else class="space-y-4">
      <UAlert
        color="success"
        icon="i-lucide-circle-check"
        title="2FA ativado"
        description="Guarde estes códigos em local seguro. Cada código funciona uma única vez."
      />
      <ul
        aria-label="Códigos de recuperação"
        class="grid grid-cols-2 gap-2 rounded-lg bg-elevated p-3 font-mono text-sm"
      >
        <li v-for="recoveryCode in recoveryCodes" :key="recoveryCode">
          {{ recoveryCode }}
        </li>
      </ul>
      <div class="flex flex-col gap-2 sm:flex-row">
        <UButton
          color="neutral"
          variant="outline"
          block
          @click="copyRecoveryCodes"
        >
          Copiar códigos
        </UButton>
        <UButton block to="/">
          Continuar para o painel
        </UButton>
      </div>
    </div>

    <template #footer>
      <UButton
        color="neutral"
        variant="link"
        block
        type="button"
        icon="i-lucide-log-out"
        label="Sair desta conta"
        @click="onLogout"
      />
    </template>
  </UCard>
</template>
