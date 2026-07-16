<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Client, CnpjLookupResult } from '~/types/api'
import { registrationStatusLabel } from '~/utils/registrationLabels'

const props = defineProps<{
  /** null/undefined = criar; Client = editar o mesmo formulário */
  client?: Client | null
  canManageCredentials?: boolean
  canManageClients?: boolean
  formId?: string
  /**
   * Ao criar filial a partir da matriz: ID da matriz pré-selecionado.
   * O cadastro continua independente; só o vínculo é gravado.
   */
  matrixClientId?: number | null
  matrixLabel?: string | null
  /**
   * Bloqueia edição dos campos (visualização na aba Cadastro).
   * Liberar só após o usuário clicar em Editar no pai.
   */
  locked?: boolean
  /** Esconde rodapé Cancelar/Salvar (pai controla ações) */
  hideActions?: boolean
}>()

const emit = defineEmits<{
  saved: [payload: { id: number, mode: 'create' | 'edit', section?: 'resumo' | 'certificado' }]
  cancel: []
  openExisting: [id: number]
}>()

const isEdit = computed(() => !!props.client?.id)
/** Omitir a prop = sem permissão (fail-closed). Pais que passam a prop continuam ok. */
const canEdit = computed(() => props.canManageClients === true)
/** Campos mutáveis: só quando não está locked e há permissão */
const fieldsLocked = computed(() => props.locked === true || !canEdit.value)
/** CNPJ/raiz: sempre imutável em edição */
const cnpjLocked = computed(() => isEdit.value || fieldsLocked.value)

const optionalEmail = z.string().refine(
  value => !value || z.string().email().safeParse(value).success,
  'Informe um e-mail válido.'
).optional()

const customFieldSchema = z.object({
  label: z.string().min(1, 'Informe o nome do campo.'),
  type: z.enum(['TEXT', 'SECRET']),
  value: z.string().optional()
})

const schema = z.object({
  legal_name: z.string().min(2, 'Informe a razão social.'),
  display_name: z.string().optional(),
  cnpj: z.string().optional(),
  trade_name: z.string().optional(),
  contact_name: z.string().optional(),
  contact_email: optionalEmail,
  contact_phone: z.string().optional(),
  contact_is_whatsapp: z.boolean(),
  notes: z.string().optional(),
  is_active: z.boolean(),
  inactive_reason: z.string().optional(),
  legal_nature_code: z.string().optional(),
  legal_nature_name: z.string().optional(),
  company_size_code: z.string().optional(),
  company_size_name: z.string().optional(),
  tax_regime: z.string().optional(),
  credential_password: z.string().optional(),
  custom_fields: z.array(customFieldSchema).max(20, 'Use no máximo 20 campos adicionais.')
}).superRefine((data, context) => {
  if (!isEdit.value) {
    const cnpj = normalizeCnpj(data.cnpj || '')
    if (!/^[A-Z0-9]{14}$/.test(cnpj)) {
      context.addIssue({
        code: 'custom',
        path: ['cnpj'],
        message: 'Informe o CNPJ completo com 14 caracteres.'
      })
    }
  }

  const hasContact = Boolean(data.contact_name || data.contact_email || data.contact_phone)
  if (!hasContact || isEdit.value) return

  if (!data.contact_name || data.contact_name.trim().length < 2) {
    context.addIssue({
      code: 'custom',
      path: ['contact_name'],
      message: 'Informe o nome do contato.'
    })
  }
  if (!data.contact_email && !data.contact_phone) {
    context.addIssue({
      code: 'custom',
      path: ['contact_email'],
      message: 'Informe ao menos e-mail ou telefone.'
    })
  }
})

type Schema = z.output<typeof schema>

const api = useApi()
const toast = useToast()
const saving = ref(false)
const lookingUp = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const preview = ref<CnpjLookupResult | null>(null)
const lookupWarning = ref<string | null>(null)
const existingClientId = ref<number | null>(null)
const credentialFile = ref<File | null>(null)
const fileInputKey = ref(0)

const state = reactive<Schema>({
  legal_name: '',
  display_name: '',
  cnpj: '',
  trade_name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  contact_is_whatsapp: false,
  notes: '',
  is_active: true,
  inactive_reason: '',
  legal_nature_code: '',
  legal_nature_name: '',
  company_size_code: '',
  company_size_name: '',
  // Reka/USelect proíbe value ''; 'none' = placeholder “Não informado”
  tax_regime: 'none',
  credential_password: '',
  custom_fields: []
})

const normalizedCnpj = computed(() => normalizeCnpj(state.cnpj || ''))
const canLookup = computed(() => !isEdit.value && /^\d{14}$/.test(normalizedCnpj.value))
const hasContact = computed(() => Boolean(state.contact_name || state.contact_email || state.contact_phone))

function normalizeCnpj(value: string): string {
  return value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
}

function emptyState() {
  state.legal_name = ''
  state.display_name = ''
  state.cnpj = ''
  state.trade_name = ''
  state.contact_name = ''
  state.contact_email = ''
  state.contact_phone = ''
  state.contact_is_whatsapp = false
  state.notes = ''
  state.is_active = true
  state.inactive_reason = ''
  state.legal_nature_code = ''
  state.legal_nature_name = ''
  state.company_size_code = ''
  state.company_size_name = ''
  state.tax_regime = 'none'
  state.credential_password = ''
  state.custom_fields.splice(0)
  credentialFile.value = null
  fileInputKey.value += 1
  fieldErrors.value = {}
  preview.value = null
  lookupWarning.value = null
  existingClientId.value = null
}

function hydrateFromClient(client: Client) {
  state.legal_name = client.legal_name || client.name || ''
  state.display_name = client.display_name || ''
  const est = client.establishments?.find(e => e.is_matrix) || client.establishments?.[0]
  // Mostra CNPJ completo no input; na edição permanece bloqueado
  state.cnpj = client.cnpj || est?.cnpj || client.root_cnpj || ''
  state.trade_name = client.trade_name || est?.trade_name || ''
  state.notes = client.notes || ''
  state.is_active = client.is_active
  state.inactive_reason = client.inactive_reason || ''
  state.legal_nature_code = client.legal_nature_code || ''
  state.legal_nature_name = client.legal_nature_name || ''
  state.company_size_code = client.company_size_code || ''
  state.company_size_name = client.company_size_name || ''
  state.tax_regime = client.tax_regime || 'none'
  state.contact_name = ''
  state.contact_email = ''
  state.contact_phone = ''
  state.contact_is_whatsapp = false
  state.credential_password = ''
  state.custom_fields.splice(0)
  credentialFile.value = null
  fileInputKey.value += 1
  fieldErrors.value = {}
  preview.value = null
  lookupWarning.value = null
  existingClientId.value = null
}

function reset() {
  if (props.client?.id) {
    hydrateFromClient(props.client)
  } else {
    emptyState()
  }
}

/** Limpa PFX, senha do cert e valores SECRET em memória (pós-create / fechar modal). */
function clearSensitive() {
  state.credential_password = ''
  credentialFile.value = null
  fileInputKey.value += 1
  for (const field of state.custom_fields) {
    if (field.type === 'SECRET') {
      field.value = ''
    }
  }
}

function addCustomField() {
  if (state.custom_fields.length >= 20) return
  state.custom_fields.push({ label: '', type: 'TEXT', value: '' })
}

function removeCustomField(index: number) {
  state.custom_fields.splice(index, 1)
}

function selectCredentialFile(event: Event) {
  const input = event.target as HTMLInputElement
  credentialFile.value = input.files?.[0] || null
}

async function lookupCnpj() {
  if (lookingUp.value || isEdit.value) return

  if (!canLookup.value) {
    if (/^[A-Z0-9]{14}$/.test(normalizedCnpj.value) && /[A-Z]/.test(normalizedCnpj.value)) {
      lookupWarning.value = 'A consulta pública ainda não aceita CNPJ alfanumérico. Preencha os nomes manualmente.'
    }
    return
  }

  lookingUp.value = true
  lookupWarning.value = null
  try {
    const response = await api.cnpj.lookup(normalizedCnpj.value)
    preview.value = response.data
    state.cnpj = response.data.establishment.cnpj
    state.legal_name = response.data.client.legal_name
    state.trade_name = response.data.establishment.trade_name || ''
    state.legal_nature_code = response.data.client.legal_nature_code || ''
    state.legal_nature_name = response.data.client.legal_nature_name || ''
    state.company_size_code = response.data.client.company_size_code || ''
    state.company_size_name = response.data.client.company_size_name || ''
    toast.add({ title: 'Dados encontrados. Revise antes de salvar.', color: 'success' })
  } catch (caught) {
    preview.value = null
    lookupWarning.value = apiErrorMessage(
      caught,
      'Não foi possível consultar o CNPJ. Continue o cadastro manualmente.'
    )
    toast.add({ title: lookupWarning.value, color: 'warning' })
  } finally {
    lookingUp.value = false
  }
}

function extractExistingClientId(caught: unknown): number | null {
  const error = caught as {
    data?: { data?: { existing_client_id?: number }, existing_client_id?: number }
    response?: { _data?: { data?: { existing_client_id?: number } } }
  }

  return error.data?.data?.existing_client_id
    ?? error.data?.existing_client_id
    ?? error.response?._data?.data?.existing_client_id
    ?? null
}

async function onSubmit(event: FormSubmitEvent<Schema>) {
  if (!canEdit.value || fieldsLocked.value) return
  fieldErrors.value = {}
  existingClientId.value = null

  if (!isEdit.value && credentialFile.value && !event.data.credential_password) {
    fieldErrors.value = { credential_password: ['Informe a senha do certificado.'] }
    return
  }

  saving.value = true
  try {
    if (isEdit.value && props.client) {
      await api.clients.update(props.client.id, {
        legal_name: event.data.legal_name.trim(),
        display_name: event.data.display_name?.trim() || null,
        notes: event.data.notes?.trim() || null,
        is_active: event.data.is_active,
        inactive_reason: event.data.inactive_reason?.trim() || null,
        legal_nature_code: event.data.legal_nature_code?.trim() || null,
        legal_nature_name: event.data.legal_nature_name?.trim() || null,
        company_size_code: event.data.company_size_code?.trim() || null,
        company_size_name: event.data.company_size_name?.trim() || null,
        tax_regime: (() => {
          const raw = event.data.tax_regime?.trim()
          return !raw || raw === 'none' ? null : raw
        })()
      })
      toast.add({ title: 'Cadastro atualizado.', color: 'success' })
      emit('saved', { id: props.client.id, mode: 'edit' })
      return
    }

    const establishment = preview.value?.establishment
    const clientData = preview.value?.client
    const response = await api.clients.create({
      legal_name: event.data.legal_name.trim(),
      display_name: event.data.display_name?.trim() || null,
      cnpj: normalizeCnpj(event.data.cnpj || ''),
      trade_name: event.data.trade_name?.trim() || null,
      notes: event.data.notes?.trim() || null,
      matrix_client_id: props.matrixClientId || null,
      is_matrix: props.matrixClientId
        ? false
        : (establishment?.is_matrix ?? true),
      registration_status: establishment?.registration_status ?? 'UNKNOWN',
      registration_status_at: establishment?.registration_status_at ?? null,
      registration_status_reason: establishment?.registration_status_reason ?? null,
      activity_started_at: establishment?.activity_started_at ?? null,
      main_cnae_code: establishment?.main_cnae_code ?? null,
      main_cnae_name: establishment?.main_cnae_name ?? null,
      public_email: establishment?.public_email ?? null,
      public_phone: establishment?.public_phone ?? null,
      legal_nature_code: event.data.legal_nature_code?.trim() || clientData?.legal_nature_code || null,
      legal_nature_name: event.data.legal_nature_name?.trim() || clientData?.legal_nature_name || null,
      company_size_code: event.data.company_size_code?.trim() || clientData?.company_size_code || null,
      company_size_name: event.data.company_size_name?.trim() || clientData?.company_size_name || null,
      tax_regime: (() => {
        const raw = event.data.tax_regime?.trim()
        return !raw || raw === 'none' ? null : raw
      })(),
      address: establishment?.address ?? null,
      initial_contact: hasContact.value
        ? {
            name: event.data.contact_name?.trim() || '',
            email: event.data.contact_email?.trim() || null,
            phone: event.data.contact_phone?.trim() || null,
            is_whatsapp: event.data.contact_is_whatsapp,
            is_primary: true,
            receives_alerts: false
          }
        : null,
      custom_fields: event.data.custom_fields.map(field => ({
        label: field.label.trim(),
        type: field.type,
        value: field.value || null
      }))
    })

    const clientId = response.data.client.id
    let section: 'resumo' | 'certificado' = 'resumo'

    if (credentialFile.value) {
      try {
        await api.credentials.activate(clientId, credentialFile.value, event.data.credential_password || '')
        toast.add({ title: 'Cliente e certificado A1 cadastrados.', color: 'success' })
      } catch (caught) {
        section = 'certificado'
        toast.add({
          title: 'Cliente criado, mas o certificado não foi ativado.',
          description: apiErrorMessage(caught, 'Revise o PFX e tente novamente na seção Certificado A1.'),
          color: 'warning'
        })
      }
    } else {
      toast.add({ title: 'Cliente cadastrado.', color: 'success' })
    }

    emit('saved', { id: clientId, mode: 'create', section })
  } catch (caught) {
    fieldErrors.value = apiFieldErrors(caught)
    existingClientId.value = extractExistingClientId(caught)
    toast.add({
      title: existingClientId.value
        ? 'Este CNPJ já pertence a um cliente deste escritório.'
        : apiErrorMessage(caught, isEdit.value ? 'Falha ao atualizar cadastro.' : 'Falha ao criar cliente.'),
      color: existingClientId.value ? 'warning' : 'error'
    })
  } finally {
    saving.value = false
    // Sempre remove PFX/senha/SECRET da memória após tentativa de create.
    if (!isEdit.value) {
      clearSensitive()
    } else {
      state.credential_password = ''
    }
  }
}

watch(
  () => props.client,
  (client) => {
    if (client?.id) {
      hydrateFromClient(client)
    } else {
      emptyState()
    }
  },
  { immediate: true, deep: true }
)

defineExpose({ reset, clearSensitive, saving })
</script>

<template>
  <UForm
    :id="formId || 'client-form'"
    :schema="schema"
    :state="state"
    class="flex min-h-0 flex-1 flex-col"
    @submit="onSubmit"
  >
    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-0.5 pr-1">
      <UAlert
        v-if="!isEdit && matrixClientId"
        color="primary"
        variant="subtle"
        icon="i-lucide-link"
        title="Filial vinculada à matriz"
      />

      <!-- Identidade / CNPJ -->
      <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
        <UFormField
          :label="isEdit ? 'CNPJ' : 'CNPJ completo'"
          name="cnpj"
          :required="!isEdit"
          :help="isEdit ? 'Identificador imutável — não pode ser alterado.' : 'Numérico ou alfanumérico, com ou sem máscara.'"
          :error="fieldErrors.cnpj?.[0]"
        >
          <UInput
            v-model="state.cnpj"
            class="w-full font-mono"
            autocomplete="off"
            :disabled="cnpjLocked"
            :autofocus="!isEdit && !fieldsLocked"
            @blur="lookupCnpj"
            @keydown.enter.prevent="lookupCnpj"
          />
        </UFormField>
        <UButton
          v-if="!isEdit && !fieldsLocked"
          type="button"
          color="neutral"
          variant="subtle"
          label="Buscar"
          icon="i-lucide-search"
          class="self-end"
          :loading="lookingUp"
          :disabled="!canLookup"
          @mousedown.prevent
          @click="lookupCnpj"
        />
      </div>

      <UFormField
        v-if="isEdit && client?.root_cnpj"
        label="Raiz CNPJ"
        name="root_cnpj"
        help="Derivada do CNPJ — somente leitura."
      >
        <UInput
          :model-value="client.root_cnpj"
          class="w-full font-mono"
          disabled
          readonly
        />
      </UFormField>

      <UAlert
        v-if="preview && !isEdit"
        color="success"
        variant="subtle"
        icon="i-lucide-database-zap"
        :title="`Dados sugeridos: ${registrationStatusLabel(preview.establishment.registration_status)}`"
        data-testid="cnpj-lookup-preview"
      />
      <UAlert
        v-if="lookupWarning && !isEdit"
        color="warning"
        variant="subtle"
        icon="i-lucide-pencil-line"
        :title="lookupWarning"
      />
      <UAlert
        v-if="existingClientId && !isEdit"
        color="warning"
        variant="subtle"
        icon="i-lucide-link"
        title="Cliente já existe"
      >
        <template #actions>
          <UButton
            size="sm"
            label="Abrir cliente"
            @click="emit('openExisting', existingClientId)"
          />
        </template>
      </UAlert>

      <div class="grid gap-4 sm:grid-cols-2">
        <UFormField
          label="Razão social"
          name="legal_name"
          required
          :error="fieldErrors.legal_name?.[0]"
        >
          <UInput
            v-model="state.legal_name"
            class="w-full"
            autocomplete="organization"
            :disabled="fieldsLocked"
          />
        </UFormField>
        <UFormField
          label="Nome interno"
          name="display_name"
          help="Opcional. Rótulo curto no painel."
          :error="fieldErrors.display_name?.[0]"
        >
          <UInput v-model="state.display_name" class="w-full" :disabled="fieldsLocked" />
        </UFormField>
        <UFormField
          label="Nome fantasia"
          name="trade_name"
          :help="isEdit ? 'Informativo do estabelecimento (alterável no cadastro inicial).' : undefined"
          :error="fieldErrors.trade_name?.[0]"
        >
          <UInput
            v-model="state.trade_name"
            class="w-full"
            :disabled="fieldsLocked || isEdit"
          />
        </UFormField>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <UFormField
          label="Natureza jurídica (cód.)"
          name="legal_nature_code"
          :error="fieldErrors.legal_nature_code?.[0]"
        >
          <UInput v-model="state.legal_nature_code" class="w-full" :disabled="fieldsLocked" />
        </UFormField>
        <UFormField
          label="Natureza jurídica"
          name="legal_nature_name"
          :error="fieldErrors.legal_nature_name?.[0]"
        >
          <UInput v-model="state.legal_nature_name" class="w-full" :disabled="fieldsLocked" />
        </UFormField>
        <UFormField
          label="Porte (cód.)"
          name="company_size_code"
          :error="fieldErrors.company_size_code?.[0]"
        >
          <UInput v-model="state.company_size_code" class="w-full" :disabled="fieldsLocked" />
        </UFormField>
        <UFormField
          label="Porte"
          name="company_size_name"
          :error="fieldErrors.company_size_name?.[0]"
        >
          <UInput v-model="state.company_size_name" class="w-full" :disabled="fieldsLocked" />
        </UFormField>
        <UFormField
          label="Regime tributário"
          name="tax_regime"
          class="sm:col-span-2"
          :error="fieldErrors.tax_regime?.[0]"
        >
          <USelect
            v-model="state.tax_regime"
            :items="[
              { label: 'Não informado', value: 'none' },
              { label: 'Simples Nacional', value: 'Simples Nacional' },
              { label: 'Lucro Presumido', value: 'Lucro Presumido' },
              { label: 'Lucro Real', value: 'Lucro Real' },
              { label: 'MEI', value: 'MEI' },
              { label: 'Imune / Isento', value: 'Imune / Isento' }
            ]"
            class="w-full"
            :disabled="fieldsLocked"
            placeholder="Selecione o regime"
          />
        </UFormField>
      </div>

      <template v-if="isEdit">
        <USeparator label="Estado no escritório" />
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField name="is_active" label="Cliente ativo">
            <USwitch v-model="state.is_active" :disabled="fieldsLocked" />
          </UFormField>
          <UFormField
            name="inactive_reason"
            label="Motivo de inativação"
            :error="fieldErrors.inactive_reason?.[0]"
          >
            <UTextarea
              v-model="state.inactive_reason"
              class="w-full"
              :rows="2"
              :disabled="fieldsLocked"
            />
          </UFormField>
        </div>
      </template>

      <!-- Contato e campos extras: só na criação (primeiro cadastro) -->
      <template v-if="!isEdit">
        <USeparator label="Contato (opcional)" />
        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="Nome do contato" name="contact_name" :error="fieldErrors['initial_contact.name']?.[0]">
            <UInput
              v-model="state.contact_name"
              class="w-full"
              autocomplete="name"
              :disabled="fieldsLocked"
            />
          </UFormField>
          <UFormField label="E-mail" name="contact_email" :error="fieldErrors['initial_contact.email']?.[0]">
            <UInput
              v-model="state.contact_email"
              type="email"
              class="w-full"
              autocomplete="email"
              :disabled="fieldsLocked"
            />
          </UFormField>
          <UFormField label="Telefone / WhatsApp" name="contact_phone" :error="fieldErrors['initial_contact.phone']?.[0]">
            <UInput
              v-model="state.contact_phone"
              type="tel"
              class="w-full"
              autocomplete="tel"
              :disabled="fieldsLocked"
            />
          </UFormField>
          <UCheckbox
            v-model="state.contact_is_whatsapp"
            label="Este número usa WhatsApp"
            name="contact_is_whatsapp"
            class="self-end pb-2"
            :disabled="fieldsLocked"
          />
        </div>

        <USeparator label="Informações adicionais" />
        <div class="space-y-3">
          <div
            v-for="(field, index) in state.custom_fields"
            :key="index"
            class="grid gap-2 rounded-lg border border-default p-3 sm:grid-cols-[1fr_9rem_1fr_auto]"
          >
            <UFormField
              :name="`custom_fields.${index}.label`"
              label="Nome do campo"
              :error="fieldErrors[`custom_fields.${index}.label`]?.[0] || fieldErrors['custom_fields']?.[0]"
            >
              <UInput
                v-model="field.label"
                class="w-full"
                placeholder="Ex.: Acesso prefeitura"
                :disabled="fieldsLocked"
              />
            </UFormField>
            <UFormField
              :name="`custom_fields.${index}.type`"
              label="Tipo"
              :error="fieldErrors[`custom_fields.${index}.type`]?.[0]"
            >
              <USelect
                v-model="field.type"
                :items="canManageCredentials
                  ? [{ label: 'Texto', value: 'TEXT' }, { label: 'Segredo', value: 'SECRET' }]
                  : [{ label: 'Texto', value: 'TEXT' }]"
                class="w-full"
                :disabled="fieldsLocked"
              />
            </UFormField>
            <UFormField
              :name="`custom_fields.${index}.value`"
              label="Valor"
              :error="fieldErrors[`custom_fields.${index}.value`]?.[0]"
            >
              <UInput
                v-model="field.value"
                :type="field.type === 'SECRET' ? 'password' : 'text'"
                class="w-full"
                :autocomplete="field.type === 'SECRET' ? 'new-password' : 'off'"
                :disabled="fieldsLocked"
              />
            </UFormField>
            <UButton
              type="button"
              color="neutral"
              variant="ghost"
              icon="i-lucide-trash-2"
              square
              class="self-end"
              :aria-label="`Remover campo ${index + 1}`"
              :disabled="fieldsLocked"
              @click="removeCustomField(index)"
            />
          </div>
          <UButton
            type="button"
            color="neutral"
            variant="subtle"
            icon="i-lucide-plus"
            label="Adicionar campo"
            :disabled="fieldsLocked || state.custom_fields.length >= 20"
            @click="addCustomField"
          />
        </div>

        <template v-if="canManageCredentials">
          <USeparator label="Certificado A1 (opcional)" />
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="Arquivo PFX" name="pfx" help=".pfx ou .p12, máximo de 5 MB.">
              <input
                :key="fileInputKey"
                type="file"
                accept=".pfx,.p12,application/x-pkcs12"
                class="block w-full rounded-md border border-default bg-default px-3 py-2 text-sm"
                :disabled="fieldsLocked"
                @change="selectCredentialFile"
              >
            </UFormField>
            <UFormField
              label="Senha do certificado"
              name="credential_password"
              :error="fieldErrors.credential_password?.[0]"
            >
              <UInput
                v-model="state.credential_password"
                type="password"
                class="w-full"
                autocomplete="new-password"
                :disabled="fieldsLocked || !credentialFile"
              />
            </UFormField>
          </div>
        </template>
      </template>

      <USeparator label="Notas" />
      <UFormField
        label="Observações"
        name="notes"
        help="Informações gerais sem senhas, tokens ou material do certificado."
        :error="fieldErrors.notes?.[0]"
      >
        <UTextarea
          v-model="state.notes"
          class="w-full"
          :rows="3"
          :disabled="fieldsLocked"
        />
      </UFormField>
    </div>

    <div
      v-if="!hideActions && !fieldsLocked"
      class="mt-4 flex shrink-0 justify-end gap-2 border-t border-default pt-4"
    >
      <UButton
        color="neutral"
        variant="subtle"
        type="button"
        label="Cancelar"
        :disabled="saving"
        @click="emit('cancel')"
      />
      <UButton
        v-if="canEdit"
        type="submit"
        color="primary"
        :label="isEdit ? 'Salvar alterações' : 'Salvar cliente'"
        icon="i-lucide-save"
        :loading="saving"
      />
    </div>
  </UForm>
</template>
