<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { ClientCredential, Establishment } from '~/types/api'
import {
  formatSourceDate,
  registrationSourceLabel,
  registrationStatusColor,
  registrationStatusIcon,
  registrationStatusLabel
} from '~/utils/registrationLabels'

const props = defineProps<{
  clientId: number
  establishments: Establishment[]
  credential: ClientCredential | null
  canManageClients: boolean
  canTriggerSync: boolean
  canManageCredentials: boolean
  triggeringId: number | null
  /** Incrementar para abrir o modal de criação (ex.: rodapé do detalhe). */
  createRequestTick?: number
}>()

const emit = defineEmits<{
  created: []
  sync: [establishment: Establishment]
}>()

const createOpen = ref(false)

watch(
  () => props.createRequestTick,
  (tick) => {
    if (tick && props.canManageClients) {
      createOpen.value = true
    }
  }
)
const editOpen = ref(false)
const editing = ref<Establishment | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const api = useApi()
const toast = useToast()

const createSchema = z.object({
  cnpj: z.string().min(8, 'Informe o CNPJ completo.'),
  trade_name: z.string().optional(),
  is_matrix: z.boolean().optional()
})

type CreateSchema = z.output<typeof createSchema>

const createState = reactive<Partial<CreateSchema>>({
  cnpj: '',
  trade_name: '',
  is_matrix: false
})

const editSchema = z.object({
  trade_name: z.string().optional(),
  is_matrix: z.boolean().optional(),
  is_active: z.boolean().optional(),
  capture_enabled: z.boolean().optional(),
  capture_enable_reason: z.string().optional(),
  public_email: z.string().optional(),
  public_phone: z.string().optional(),
  main_cnae_code: z.string().optional(),
  main_cnae_name: z.string().optional(),
  address_city: z.string().optional(),
  address_state: z.string().optional(),
  address_street: z.string().optional(),
  address_number: z.string().optional(),
  address_district: z.string().optional(),
  address_postal_code: z.string().optional()
})

type EditSchema = z.output<typeof editSchema>
const editState = reactive<Partial<EditSchema>>({})

function resetCreate() {
  createState.cnpj = ''
  createState.trade_name = ''
  createState.is_matrix = false
  fieldErrors.value = {}
}

function openEdit(est: Establishment) {
  editing.value = est
  editState.trade_name = est.trade_name || ''
  editState.is_matrix = est.is_matrix
  editState.is_active = est.is_active
  editState.capture_enabled = est.capture_enabled ?? true
  editState.capture_enable_reason = ''
  editState.public_email = est.public_email || ''
  editState.public_phone = est.public_phone || ''
  editState.main_cnae_code = est.main_cnae_code || ''
  editState.main_cnae_name = est.main_cnae_name || ''
  editState.address_city = est.address?.city || ''
  editState.address_state = est.address?.state || ''
  editState.address_street = est.address?.street || ''
  editState.address_number = est.address?.number || ''
  editState.address_district = est.address?.district || ''
  editState.address_postal_code = est.address?.postal_code || ''
  fieldErrors.value = {}
  editOpen.value = true
}

async function onCreate(event: FormSubmitEvent<CreateSchema>) {
  fieldErrors.value = {}
  saving.value = true
  try {
    await api.establishments.create(props.clientId, {
      cnpj: event.data.cnpj,
      trade_name: event.data.trade_name,
      is_matrix: event.data.is_matrix
    })
    createOpen.value = false
    resetCreate()
    toast.add({ title: 'Estabelecimento cadastrado.', color: 'success' })
    emit('created')
  } catch (caught) {
    fieldErrors.value = apiFieldErrors(caught)
    toast.add({ title: apiErrorMessage(caught, 'Falha ao cadastrar estabelecimento.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function onEdit(event: FormSubmitEvent<EditSchema>) {
  if (!editing.value) return
  fieldErrors.value = {}
  saving.value = true
  try {
    await api.establishments.update(editing.value.id, {
      trade_name: event.data.trade_name || null,
      is_matrix: event.data.is_matrix,
      is_active: event.data.is_active,
      capture_enabled: event.data.capture_enabled,
      capture_enable_reason: event.data.capture_enable_reason || null,
      public_email: event.data.public_email || null,
      public_phone: event.data.public_phone || null,
      main_cnae_code: event.data.main_cnae_code || null,
      main_cnae_name: event.data.main_cnae_name || null,
      address: {
        city: event.data.address_city || null,
        state: event.data.address_state || null,
        street: event.data.address_street || null,
        number: event.data.address_number || null,
        district: event.data.address_district || null,
        postal_code: event.data.address_postal_code || null
      }
    })
    editOpen.value = false
    toast.add({ title: 'Estabelecimento atualizado.', color: 'success' })
    emit('created')
  } catch (caught) {
    fieldErrors.value = apiFieldErrors(caught)
    toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar estabelecimento.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

watch(createOpen, (value) => {
  if (!value) resetCreate()
})

function canSync(establishment: Establishment) {
  if (!props.canTriggerSync || !establishment.is_active) {
    return false
  }
  if (establishment.capture_enabled === false) {
    return false
  }
  if (establishment.capture_eligibility && !establishment.capture_eligibility.eligible) {
    return false
  }
  if (props.canManageCredentials && !props.credential) {
    return false
  }
  return true
}

function ineligibilityHint(est: Establishment): string | null {
  if (est.capture_eligibility && !est.capture_eligibility.eligible) {
    return est.capture_eligibility.reasons.join(' ')
  }
  if (est.capture_enabled === false) {
    return 'Captura desabilitada para este estabelecimento.'
  }
  if (!est.is_active) {
    return 'Estabelecimento inativo.'
  }
  return null
}
</script>

<template>
  <div class="space-y-4">
    <UPageCard
      variant="naked"
      orientation="horizontal"
      class="mb-4"
      title="Estabelecimentos"
      description="CNPJ completo, numérico ou alfanumérico — armazenado em maiúsculas e sem máscara."
    >
      <UButton
        v-if="canManageClients"
        icon="i-lucide-plus"
        label="Adicionar"
        color="primary"
        variant="soft"
        class="w-fit lg:ms-auto"
        @click="() => { createOpen = true }"
      />
    </UPageCard>

    <UPageCard variant="subtle">
      <div v-if="establishments.length" class="divide-y divide-default">
        <div
          v-for="establishment in establishments"
          :key="establishment.id"
          class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ establishment.trade_name || establishment.cnpj }}</span>
                <UBadge v-if="establishment.is_matrix" color="info" variant="subtle">
                  Matriz
                </UBadge>
                <UBadge :color="establishment.is_active ? 'success' : 'neutral'" variant="subtle">
                  {{ establishment.is_active ? 'Ativo' : 'Inativo' }}
                </UBadge>
                <UBadge
                  :color="registrationStatusColor(establishment.registration_status)"
                  variant="subtle"
                  :icon="registrationStatusIcon(establishment.registration_status)"
                >
                  {{ registrationStatusLabel(establishment.registration_status) }}
                </UBadge>
                <UBadge
                  :color="establishment.capture_enabled !== false ? 'success' : 'warning'"
                  variant="subtle"
                  :icon="establishment.capture_enabled !== false ? 'i-lucide-radio' : 'i-lucide-radio-off'"
                >
                  {{ establishment.capture_enabled !== false ? 'Captura on' : 'Captura off' }}
                </UBadge>
              </div>
              <p class="font-mono text-sm text-muted">
                {{ establishment.cnpj }}
              </p>
              <p v-if="establishment.public_email || establishment.public_phone" class="mt-1 text-xs text-muted">
                <UIcon name="i-lucide-globe" class="mr-1 inline size-3" aria-hidden="true" />
                Contato público CNPJ:
                <template v-if="establishment.public_email">
                  {{ establishment.public_email }}
                </template>
                <template v-if="establishment.public_phone">
                  · {{ establishment.public_phone }}
                </template>
              </p>
              <p class="mt-1 text-xs text-muted">
                Fonte {{ registrationSourceLabel(establishment.registration_source) }}
                · {{ formatSourceDate(establishment.registration_refreshed_at) }}
              </p>
              <p
                v-if="ineligibilityHint(establishment)"
                class="mt-1 text-xs text-warning"
                data-testid="capture-ineligible-reason"
              >
                {{ ineligibilityHint(establishment) }}
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <UButton
                v-if="canManageClients"
                icon="i-lucide-pencil"
                label="Editar"
                color="neutral"
                variant="subtle"
                size="sm"
                @click="openEdit(establishment)"
              />
              <UButton
                v-if="canTriggerSync"
                icon="i-lucide-refresh-cw"
                label="Sincronizar"
                color="neutral"
                variant="subtle"
                size="sm"
                :loading="triggeringId === establishment.id"
                :disabled="!canSync(establishment)"
                :aria-label="`Sincronizar ${establishment.cnpj}`"
                @click="emit('sync', establishment)"
              />
            </div>
          </div>
        </div>
      </div>
      <UEmpty
        v-else
        icon="i-lucide-map-pin-plus"
        title="Nenhum estabelecimento"
        description="Adicione a matriz ou uma filial para continuar."
      />
    </UPageCard>

    <UModal
      v-if="canManageClients"
      v-model:open="createOpen"
      title="Adicionar estabelecimento"
      description="Informe o CNPJ completo do estabelecimento (14 caracteres)."
    >
      <template #body>
        <UForm
          :schema="createSchema"
          :state="createState"
          class="space-y-4"
          @submit="onCreate"
        >
          <UFormField
            label="CNPJ completo"
            name="cnpj"
            required
            :error="fieldErrors.cnpj?.[0]"
          >
            <UInput
              v-model="createState.cnpj"
              class="w-full"
              autocomplete="off"
            />
          </UFormField>
          <UFormField
            label="Nome fantasia"
            name="trade_name"
            :error="fieldErrors.trade_name?.[0]"
          >
            <UInput v-model="createState.trade_name" class="w-full" />
          </UFormField>
          <UCheckbox v-model="createState.is_matrix" label="Este estabelecimento é a matriz" name="is_matrix" />
          <div class="flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="subtle"
              type="button"
              label="Cancelar"
              :disabled="saving"
              @click="() => { createOpen = false }"
            />
            <UButton type="submit" label="Adicionar" :loading="saving" />
          </div>
        </UForm>
      </template>
    </UModal>

    <UModal
      v-if="canManageClients && editing"
      v-model:open="editOpen"
      title="Editar estabelecimento"
      description="CNPJ e raiz são imutáveis. NSU não é editável aqui."
    >
      <template #body>
        <UForm
          :schema="editSchema"
          :state="editState"
          class="space-y-4"
          @submit="onEdit"
        >
          <UFormField label="CNPJ" name="cnpj_readonly">
            <UInput
              :model-value="editing.cnpj"
              class="w-full font-mono"
              disabled
              readonly
            />
          </UFormField>
          <UFormField label="Nome fantasia" name="trade_name" :error="fieldErrors.trade_name?.[0]">
            <UInput v-model="editState.trade_name" class="w-full" />
          </UFormField>
          <UCheckbox v-model="editState.is_matrix" label="Matriz" name="is_matrix" />
          <UCheckbox v-model="editState.is_active" label="Estabelecimento ativo" name="is_active" />
          <UCheckbox v-model="editState.capture_enabled" label="Captura habilitada" name="capture_enabled" />
          <UFormField
            v-if="editState.capture_enabled && editing.registration_status && editing.registration_status !== 'ACTIVE' && !editing.capture_enabled"
            label="Motivo da habilitação excepcional"
            name="capture_enable_reason"
            required
            help="Obrigatório quando a situação cadastral externa não é ativa."
            :error="fieldErrors.capture_enable_reason?.[0]"
          >
            <UTextarea v-model="editState.capture_enable_reason" class="w-full" :rows="2" />
          </UFormField>
          <USeparator />
          <p class="text-sm font-medium">
            Contato público (CNPJ)
          </p>
          <UFormField label="E-mail público" name="public_email">
            <UInput v-model="editState.public_email" class="w-full" />
          </UFormField>
          <UFormField label="Telefone público" name="public_phone">
            <UInput v-model="editState.public_phone" class="w-full" />
          </UFormField>
          <USeparator />
          <p class="text-sm font-medium">
            Atividade e endereço
          </p>
          <UFormField label="CNAE código" name="main_cnae_code">
            <UInput v-model="editState.main_cnae_code" class="w-full" />
          </UFormField>
          <UFormField label="CNAE descrição" name="main_cnae_name">
            <UInput v-model="editState.main_cnae_name" class="w-full" />
          </UFormField>
          <UFormField label="Logradouro" name="address_street">
            <UInput v-model="editState.address_street" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-2 gap-2">
            <UFormField label="Número" name="address_number">
              <UInput v-model="editState.address_number" class="w-full" />
            </UFormField>
            <UFormField label="CEP" name="address_postal_code">
              <UInput v-model="editState.address_postal_code" class="w-full" />
            </UFormField>
          </div>
          <UFormField label="Bairro" name="address_district">
            <UInput v-model="editState.address_district" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-2 gap-2">
            <UFormField label="Município" name="address_city">
              <UInput v-model="editState.address_city" class="w-full" />
            </UFormField>
            <UFormField label="UF" name="address_state">
              <UInput v-model="editState.address_state" class="w-full" maxlength="2" />
            </UFormField>
          </div>
          <div class="flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="subtle"
              type="button"
              label="Cancelar"
              @click="() => { editOpen = false }"
            />
            <UButton type="submit" label="Salvar" :loading="saving" />
          </div>
        </UForm>
      </template>
    </UModal>
  </div>
</template>
