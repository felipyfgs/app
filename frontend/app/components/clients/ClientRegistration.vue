<script setup lang="ts">
/**
 * Aba Cadastro: formulário geral do cliente nos próprios inputs.
 * Campos bloqueados até clicar em Editar; CNPJ permanece imutável.
 */
import type { Client, ClientContact } from '~/types/api'
import { formatSourceDate, registrationSourceLabel } from '~/utils/registrationLabels'

const props = defineProps<{
  client: Client
  canManageClients: boolean
  /** Abre já em modo edição por uma ação local do detalhe. */
  startEditing?: boolean
}>()

const emit = defineEmits<{
  updated: []
  editingChange: [value: boolean]
}>()

const api = useApi()
const toast = useToast()

const formRef = ref<{ reset: () => void, saving: { value: boolean } } | null>(null)
const editing = ref(false)

// contatos internos
const contactOpen = ref(false)
const contactSaving = ref(false)
const contactErrors = ref<Record<string, string[]>>({})
const editingContact = ref<ClientContact | null>(null)

const contactState = reactive({
  name: '',
  role: '',
  email: '',
  phone: '',
  is_whatsapp: false,
  is_primary: false,
  receives_alerts: false,
  notes: ''
})

const contacts = computed(() => props.client.contacts || [])
const customFields = computed(() => props.client.custom_fields || [])

function startEdit() {
  if (!props.canManageClients) return
  editing.value = true
}

function cancelEdit() {
  editing.value = false
  formRef.value?.reset()
}

function onSaved() {
  editing.value = false
  emit('updated')
}

function openNewContact() {
  editingContact.value = null
  contactState.name = ''
  contactState.role = ''
  contactState.email = ''
  contactState.phone = ''
  contactState.is_whatsapp = false
  contactState.is_primary = false
  contactState.receives_alerts = false
  contactState.notes = ''
  contactErrors.value = {}
  contactOpen.value = true
}

function openEditContact(contact: ClientContact) {
  editingContact.value = contact
  contactState.name = contact.name
  contactState.role = contact.role || ''
  contactState.email = contact.email || ''
  contactState.phone = contact.phone || ''
  contactState.is_whatsapp = contact.is_whatsapp
  contactState.is_primary = contact.is_primary
  contactState.receives_alerts = contact.receives_alerts
  contactState.notes = contact.notes || ''
  contactErrors.value = {}
  contactOpen.value = true
}

async function onContactSubmit() {
  if (!props.canManageClients) return
  contactErrors.value = {}
  contactSaving.value = true
  try {
    const body = {
      name: contactState.name,
      role: contactState.role || null,
      email: contactState.email || null,
      phone: contactState.phone || null,
      is_whatsapp: contactState.is_whatsapp,
      is_primary: contactState.is_primary,
      receives_alerts: contactState.receives_alerts,
      notes: contactState.notes || null
    }
    if (editingContact.value) {
      await api.contacts.update(props.client.id, editingContact.value.id, body)
      toast.add({ title: 'Contato atualizado.', color: 'success' })
    } else {
      await api.contacts.create(props.client.id, body)
      toast.add({ title: 'Contato criado.', color: 'success' })
    }
    contactOpen.value = false
    emit('updated')
  } catch (caught) {
    contactErrors.value = apiFieldErrors(caught)
    toast.add({ title: apiErrorMessage(caught, 'Falha ao salvar contato.'), color: 'error' })
  } finally {
    contactSaving.value = false
  }
}

async function removeContact(contact: ClientContact) {
  try {
    await api.contacts.remove(props.client.id, contact.id)
    toast.add({ title: 'Contato removido.', color: 'success' })
    emit('updated')
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao remover contato.'), color: 'error' })
  }
}

watch(
  () => props.startEditing,
  (start) => {
    if (props.canManageClients && start) {
      editing.value = true
    }
  },
  { immediate: true }
)

watch(editing, value => emit('editingChange', value))

// se o client recarregar e não estiver editando, re-hidrata
watch(
  () => props.client,
  () => {
    if (!editing.value) {
      nextTick(() => formRef.value?.reset())
    }
  },
  { deep: true }
)

defineExpose({ startEdit, cancelEdit, editing })
</script>

<template>
  <div class="space-y-6" data-testid="client-registration">
    <UPageCard
      title="Cadastro do cliente"
      description="Dados gerais nos campos do formulário. Edição liberada apenas ao clicar em Editar."
      variant="naked"
      orientation="horizontal"
      class="mb-2"
    >
      <div class="flex w-fit flex-wrap gap-2 lg:ms-auto">
        <template v-if="canManageClients && !editing">
          <UButton
            icon="i-lucide-pencil"
            label="Editar"
            color="primary"
            variant="soft"
            data-testid="client-registration-edit"
            @click="startEdit"
          />
        </template>
        <template v-else-if="canManageClients && editing">
          <UButton
            color="neutral"
            variant="subtle"
            label="Cancelar"
            @click="cancelEdit"
          />
          <UButton
            form="client-registration-form"
            type="submit"
            color="primary"
            icon="i-lucide-save"
            label="Salvar alterações"
          />
        </template>
      </div>
    </UPageCard>

    <UPageCard variant="subtle" :ui="{ body: 'sm:p-5' }">
      <ClientsClientForm
        ref="formRef"
        form-id="client-registration-form"
        :client="client"
        :can-manage-clients="canManageClients"
        :can-manage-credentials="false"
        :locked="!editing"
        hide-actions
        @saved="onSaved"
        @cancel="cancelEdit"
      />
    </UPageCard>

    <UPageCard
      title="Proveniência"
      description="Origem e data da última consulta cadastral (somente leitura)."
      variant="naked"
      class="mb-2 mt-4"
    />
    <UPageCard variant="subtle">
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <p class="text-sm text-muted">
            Fonte
          </p>
          <p class="font-medium">
            {{ registrationSourceLabel(client.registration_source) }}
          </p>
        </div>
        <div>
          <p class="text-sm text-muted">
            Atualizado em
          </p>
          <p class="font-medium">
            {{ formatSourceDate(client.registration_refreshed_at) }}
          </p>
        </div>
      </div>
    </UPageCard>

    <!-- Campos adicionais (somente leitura; SECRET nunca expõe valor) -->
    <UPageCard
      title="Informações adicionais"
      description="Campos extras do cadastro. Segredos mostram apenas se estão configurados."
      variant="naked"
      class="mb-2 mt-4"
    />
    <UPageCard variant="subtle" data-testid="client-custom-fields">
      <div v-if="customFields.length" class="grid gap-3 sm:grid-cols-2">
        <div
          v-for="field in customFields"
          :key="field.id"
          class="rounded-lg bg-elevated/50 px-3 py-3 ring ring-inset ring-default"
        >
          <div class="flex flex-wrap items-center gap-2">
            <p class="font-medium text-highlighted">
              {{ field.label }}
            </p>
            <UBadge
              :color="field.type === 'SECRET' ? 'warning' : 'neutral'"
              variant="subtle"
              size="sm"
            >
              {{ field.type === 'SECRET' ? 'Segredo' : 'Texto' }}
            </UBadge>
          </div>
          <p class="mt-1 text-sm text-muted">
            <template v-if="field.type === 'SECRET'">
              {{ field.has_value ? 'Configurado' : 'Não configurado' }}
            </template>
            <template v-else>
              {{ field.value || (field.has_value ? '—' : 'Vazio') }}
            </template>
          </p>
        </div>
      </div>
      <UEmpty
        v-else
        icon="i-lucide-list"
        title="Nenhum campo adicional"
        description="Campos extras são definidos na criação do cliente."
      />
    </UPageCard>

    <!-- Contatos internos -->
    <UPageCard
      title="Contatos internos"
      description="Pessoas/canais do escritório — distintos do contato público do CNPJ."
      variant="naked"
      orientation="horizontal"
      class="mb-2 mt-4"
    >
      <UButton
        v-if="canManageClients"
        icon="i-lucide-plus"
        label="Adicionar contato"
        color="primary"
        variant="soft"
        class="w-fit lg:ms-auto"
        @click="openNewContact"
      />
    </UPageCard>
    <UPageCard variant="subtle" data-testid="client-contacts">
      <div v-if="contacts.length" class="divide-y divide-default">
        <div
          v-for="contact in contacts"
          :key="contact.id"
          class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium">{{ contact.name }}</span>
              <UBadge v-if="contact.is_primary" color="primary" variant="subtle" icon="i-lucide-star">
                Principal
              </UBadge>
              <UBadge color="info" variant="subtle" icon="i-lucide-users">
                Interno
              </UBadge>
            </div>
            <p class="text-sm text-muted">
              {{ contact.role || 'Sem função' }}
              <template v-if="contact.email">
                · {{ contact.email }}
              </template>
              <template v-if="contact.phone">
                · {{ contact.phone }}
              </template>
            </p>
          </div>
          <div v-if="canManageClients" class="flex gap-2">
            <UButton
              size="sm"
              color="neutral"
              variant="ghost"
              icon="i-lucide-pencil"
              aria-label="Editar contato"
              @click="openEditContact(contact)"
            />
            <UButton
              size="sm"
              color="neutral"
              variant="ghost"
              icon="i-lucide-trash"
              aria-label="Remover contato"
              @click="removeContact(contact)"
            />
          </div>
        </div>
      </div>
      <UEmpty
        v-else
        icon="i-lucide-contact"
        title="Nenhum contato interno"
        description="Cadastre contatos operacionais do escritório."
      />
    </UPageCard>

    <UModal
      v-if="canManageClients"
      v-model:open="contactOpen"
      :title="editingContact ? 'Editar contato' : 'Novo contato interno'"
      description="Contato operacional do escritório."
    >
      <template #body>
        <form class="space-y-4" @submit.prevent="onContactSubmit">
          <UFormField label="Nome" name="name" required :error="contactErrors.name?.[0]">
            <UInput v-model="contactState.name" class="w-full" />
          </UFormField>
          <UFormField label="Função" name="role">
            <UInput v-model="contactState.role" class="w-full" />
          </UFormField>
          <UFormField label="E-mail" name="email" :error="contactErrors.email?.[0]">
            <UInput v-model="contactState.email" type="email" class="w-full" />
          </UFormField>
          <UFormField label="Telefone" name="phone">
            <UInput v-model="contactState.phone" class="w-full" />
          </UFormField>
          <UCheckbox v-model="contactState.is_whatsapp" label="WhatsApp" />
          <UCheckbox v-model="contactState.is_primary" label="Contato principal" />
          <UCheckbox v-model="contactState.receives_alerts" label="Recebe alertas (futuro)" />
          <UFormField label="Observações" name="notes">
            <UTextarea v-model="contactState.notes" class="w-full" :rows="2" />
          </UFormField>
          <div class="flex justify-end gap-2">
            <UButton color="neutral" variant="subtle" type="button" label="Cancelar" @click="() => { contactOpen = false }" />
            <UButton type="submit" color="primary" label="Salvar" :loading="contactSaving" />
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>
