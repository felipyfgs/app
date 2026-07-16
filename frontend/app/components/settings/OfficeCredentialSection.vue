<script setup lang="ts">
/**
 * Credencial canônica e-CNPJ A1.
 * Sem download; mensagem de segurança uma única vez no upload.
 */
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { OfficeCanonicalCredential } from '~/types/api'
import { credentialAlerts } from '~/utils/office-settings'

const props = defineProps<{
  credential: OfficeCanonicalCredential | null
  loading?: boolean
  saving?: boolean
  readonly?: boolean
  /** Reconfirmação de senha (todos os perfis em ações sensíveis). */
  requirePasswordReconfirm?: boolean
}>()

const emit = defineEmits<{
  upload: [payload: { file: File, password: string, reconfirm_password?: string }]
  remove: [payload: { reconfirm_password?: string }]
}>()

const open = ref(false)
const removeOpen = ref(false)
const credentialFile = ref<File | null>(null)
const fileInputKey = ref(0)
const reconfirmPassword = ref('')

const schema = z.object({
  password: z.string().min(1, 'Informe a senha do certificado.')
})
type Schema = z.output<typeof schema>
const state = reactive<Partial<Schema>>({ password: '' })

const alerts = computed(() => credentialAlerts(props.credential))

function clearSensitive() {
  state.password = ''
  credentialFile.value = null
  reconfirmPassword.value = ''
  fileInputKey.value += 1
}

function selectFile(event: Event) {
  const input = event.target as HTMLInputElement
  credentialFile.value = input.files?.[0] || null
}

function onSubmit(_event: FormSubmitEvent<Schema>) {
  if (props.readonly) return
  if (!credentialFile.value) {
    useToast().add({ title: 'Selecione um arquivo PFX/P12.', color: 'warning' })
    return
  }
  if (props.requirePasswordReconfirm && !reconfirmPassword.value) {
    useToast().add({ title: 'Reconfirme sua senha de acesso.', color: 'warning' })
    return
  }
  emit('upload', {
    file: credentialFile.value,
    password: state.password || '',
    reconfirm_password: props.requirePasswordReconfirm ? reconfirmPassword.value : undefined
  })
  open.value = false
  clearSensitive()
}

function confirmRemove() {
  if (props.requirePasswordReconfirm && !reconfirmPassword.value) {
    useToast().add({ title: 'Reconfirme sua senha de acesso.', color: 'warning' })
    return
  }
  emit('remove', {
    reconfirm_password: props.requirePasswordReconfirm ? reconfirmPassword.value : undefined
  })
  removeOpen.value = false
  clearSensitive()
}

watch(open, (v) => {
  if (!v) clearSensitive()
})
watch(removeOpen, (v) => {
  if (!v) clearSensitive()
})
</script>

<template>
  <div data-testid="settings-credential-section">
    <UPageCard
      variant="naked"
      orientation="horizontal"
      class="mb-4"
      title="Certificado A1"
    >
      <UButton
        v-if="!readonly"
        :label="credential ? 'Substituir' : 'Enviar'"
        color="neutral"
        class="w-fit lg:ms-auto"
        icon="i-lucide-upload"
        data-testid="settings-credential-open-upload"
        @click="() => { open = true }"
      />
    </UPageCard>

    <UPageCard variant="subtle">
      <div
        v-if="loading && !credential"
        class="space-y-2"
        role="status"
        aria-label="Carregando certificado"
      >
        <USkeleton class="h-4 w-1/3" />
        <USkeleton class="h-4 w-1/2" />
      </div>
      <div
        v-else-if="credential"
        class="space-y-3 text-sm"
      >
        <div class="flex flex-wrap items-center gap-2">
          <ShellStatusBadge :status="credential.status" />
          <UBadge
            v-for="a in alerts"
            :key="a"
            color="warning"
            variant="subtle"
          >
            {{ a }}
          </UBadge>
        </div>
        <dl class="grid gap-2 sm:grid-cols-2">
          <div v-if="credential.subject_name">
            <dt class="text-xs text-muted">
              Titular
            </dt>
            <dd class="break-words text-highlighted">
              {{ credential.subject_name }}
            </dd>
          </div>
          <div v-if="credential.holder_cnpj">
            <dt class="text-xs text-muted">
              CNPJ
            </dt>
            <dd class="font-mono text-highlighted">
              {{ credential.holder_cnpj }}
            </dd>
          </div>
          <div v-if="credential.valid_to">
            <dt class="text-xs text-muted">
              Validade
            </dt>
            <dd class="text-highlighted">
              {{ formatDateTime(credential.valid_to) }}
            </dd>
          </div>
        </dl>
        <div
          v-if="!readonly"
          class="flex justify-end border-t border-default pt-3"
        >
          <UButton
            color="error"
            variant="ghost"
            icon="i-lucide-trash-2"
            label="Remover"
            data-testid="settings-credential-remove"
            @click="() => { removeOpen = true }"
          />
        </div>
      </div>
      <UEmpty
        v-else
        icon="i-lucide-badge-alert"
        title="Nenhum certificado"
        data-testid="settings-credential-empty"
      >
        <UButton
          v-if="!readonly"
          label="Enviar certificado"
          data-testid="settings-credential-empty-action"
          @click="() => { open = true }"
        />
      </UEmpty>
    </UPageCard>

    <UModal
      v-if="!readonly"
      v-model:open="open"
      :title="credential ? 'Substituir A1' : 'Enviar A1'"
      description="O arquivo não poderá ser baixado depois."
    >
      <template #body>
        <UForm
          :schema="schema"
          :state="state"
          class="space-y-4"
          @submit="onSubmit"
        >
          <UFormField
            label="Arquivo PFX/P12"
            required
          >
            <input
              :key="fileInputKey"
              type="file"
              accept=".pfx,.p12,application/x-pkcs12"
              class="block w-full text-sm"
              data-testid="settings-credential-file"
              @change="selectFile"
            >
          </UFormField>
          <UFormField
            name="password"
            label="Senha do PFX"
            required
          >
            <UInput
              v-model="state.password"
              type="password"
              autocomplete="new-password"
              class="w-full"
              data-testid="settings-credential-password"
            />
          </UFormField>
          <UFormField
            v-if="requirePasswordReconfirm"
            label="Sua senha de acesso"
            required
          >
            <UInput
              v-model="reconfirmPassword"
              type="password"
              autocomplete="current-password"
              class="w-full"
              data-testid="settings-credential-reconfirm"
            />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="ghost"
              label="Cancelar"
              @click="() => { open = false }"
            />
            <UButton
              type="submit"
              color="primary"
              label="Validar e armazenar"
              icon="i-lucide-shield-check"
              :loading="saving"
              data-testid="settings-credential-submit"
            />
          </div>
        </UForm>
      </template>
    </UModal>

    <UModal
      v-if="!readonly"
      v-model:open="removeOpen"
      title="Remover A1?"
      description="As finalidades que dependem do certificado ficam bloqueadas."
    >
      <template #body>
        <UFormField
          v-if="requirePasswordReconfirm"
          label="Sua senha de acesso"
          required
        >
          <UInput
            v-model="reconfirmPassword"
            type="password"
            autocomplete="current-password"
            class="w-full"
          />
        </UFormField>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            label="Cancelar"
            @click="() => { removeOpen = false }"
          />
          <UButton
            color="error"
            label="Remover"
            :loading="saving"
            data-testid="settings-credential-confirm-remove"
            @click="confirmRemove"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
