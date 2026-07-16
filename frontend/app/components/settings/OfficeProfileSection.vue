<script setup lang="ts">
/**
 * Perfil institucional (4 campos) — arquétipo settings/index do template.
 */
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { OfficeInstitutionalProfile } from '~/types/api'

const props = defineProps<{
  profile: OfficeInstitutionalProfile | null
  loading?: boolean
  saving?: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  save: [payload: {
    cnpj: string
    legal_name: string
    institutional_email: string
    institutional_phone: string
    confirm_cnpj_change?: boolean
  }]
}>()

const schema = z.object({
  cnpj: z.string().min(14, 'Informe o CNPJ com 14 caracteres.').max(18),
  legal_name: z.string().min(2, 'Informe a razão social.').max(200),
  institutional_email: z.string().email('E-mail institucional inválido.'),
  institutional_phone: z.string().min(8, 'Informe o telefone institucional.').max(32)
})

type Schema = z.output<typeof schema>

const state = reactive<Schema>({
  cnpj: '',
  legal_name: '',
  institutional_email: '',
  institutional_phone: ''
})

const confirmCnpjOpen = ref(false)
const pendingSubmit = ref<Schema | null>(null)

const originalCnpj = computed(() =>
  String(props.profile?.cnpj || '').replace(/\D/g, '').toUpperCase()
)

watch(
  () => props.profile,
  (p) => {
    if (!p) return
    state.cnpj = p.cnpj ? String(p.cnpj) : ''
    state.legal_name = p.legal_name ? String(p.legal_name) : ''
    state.institutional_email = p.institutional_email ? String(p.institutional_email) : ''
    state.institutional_phone = p.institutional_phone ? String(p.institutional_phone) : ''
  },
  { immediate: true }
)

function onSubmit(event: FormSubmitEvent<Schema>) {
  if (props.readonly) return
  const nextCnpj = event.data.cnpj.replace(/[^0-9A-Za-z]/g, '').toUpperCase()
  const prev = originalCnpj.value
  if (prev && nextCnpj && prev !== nextCnpj) {
    pendingSubmit.value = event.data
    confirmCnpjOpen.value = true
    return
  }
  emit('save', {
    cnpj: nextCnpj,
    legal_name: event.data.legal_name.trim(),
    institutional_email: event.data.institutional_email.trim(),
    institutional_phone: event.data.institutional_phone.trim()
  })
}

function confirmCnpjChange() {
  const data = pendingSubmit.value
  confirmCnpjOpen.value = false
  if (!data) return
  emit('save', {
    cnpj: data.cnpj.replace(/[^0-9A-Za-z]/g, '').toUpperCase(),
    legal_name: data.legal_name.trim(),
    institutional_email: data.institutional_email.trim(),
    institutional_phone: data.institutional_phone.trim(),
    confirm_cnpj_change: true
  })
  pendingSubmit.value = null
}
</script>

<template>
  <div data-testid="settings-profile-section">
    <UForm
      id="office-profile-form"
      :schema="schema"
      :state="state"
      @submit="onSubmit"
    >
      <UPageCard
        title="Perfil"
        variant="naked"
        orientation="horizontal"
        class="mb-4"
      >
        <UButton
          v-if="!readonly"
          form="office-profile-form"
          type="submit"
          label="Salvar"
          color="neutral"
          icon="i-lucide-save"
          class="w-fit lg:ms-auto"
          :loading="saving"
          :disabled="loading"
          data-testid="settings-profile-save"
        />
      </UPageCard>

      <UPageCard variant="subtle">
        <div
          v-if="loading && !profile"
          class="space-y-3"
          role="status"
          aria-label="Carregando perfil"
        >
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-full" />
          <USkeleton class="h-10 w-2/3" />
        </div>
        <template v-else>
          <UFormField
            name="cnpj"
            label="CNPJ"
            required
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="state.cnpj"
              autocomplete="off"
              placeholder="14 caracteres"
              class="w-full sm:w-64"
              :disabled="readonly"
              data-testid="settings-profile-cnpj"
            />
          </UFormField>
          <USeparator />
          <UFormField
            name="legal_name"
            label="Razão social"
            required
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="state.legal_name"
              autocomplete="organization"
              class="w-full sm:w-64"
              :disabled="readonly"
              data-testid="settings-profile-legal-name"
            />
          </UFormField>
          <USeparator />
          <UFormField
            name="institutional_email"
            label="E-mail institucional"
            required
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="state.institutional_email"
              type="email"
              autocomplete="email"
              class="w-full sm:w-64"
              :disabled="readonly"
              data-testid="settings-profile-email"
            />
          </UFormField>
          <USeparator />
          <UFormField
            name="institutional_phone"
            label="Telefone institucional"
            required
            class="flex max-sm:flex-col justify-between items-start gap-4"
          >
            <UInput
              v-model="state.institutional_phone"
              type="tel"
              autocomplete="tel"
              class="w-full sm:w-64"
              :disabled="readonly"
              data-testid="settings-profile-phone"
            />
          </UFormField>
        </template>
      </UPageCard>
    </UForm>

    <UModal
      v-model:open="confirmCnpjOpen"
      title="Trocar CNPJ?"
      description="O certificado A1 atual será invalidado se for de outro CNPJ."
    >
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton
            color="neutral"
            variant="ghost"
            label="Cancelar"
            @click="() => { confirmCnpjOpen = false }"
          />
          <UButton
            color="warning"
            label="Confirmar troca"
            data-testid="settings-confirm-cnpj-change"
            @click="confirmCnpjChange"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>
