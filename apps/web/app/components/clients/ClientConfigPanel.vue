<script setup lang="ts">
/**
 * Configuração específica do cliente — estado, certificado, campos, captura.
 */
import type { AccordionItem } from '@nuxt/ui'
import type { Client, ClientCredential, ClientCustomField } from '~/types/api'

const props = defineProps<{
  client: Client
  credential: ClientCredential | null
  canManageClients: boolean
  canManageCredentials: boolean
}>()

const emit = defineEmits<{
  updated: []
  credentialActivated: [value: ClientCredential]
}>()

const api = useApi()
const toast = useToast()

const openItems = ref<string | string[]>(['estado'])
const savingCapture = ref(false)
const savingFieldId = ref<number | null>(null)

const primaryEstablishment = computed(() =>
  props.client.establishments?.find(e => e.is_matrix)
  || props.client.establishments?.[0]
  || null
)

const captureEnabled = computed(() => Boolean(primaryEstablishment.value?.capture_enabled))

const customFields = computed(() => props.client.custom_fields || [])

const credentialStatus = computed(() =>
  props.credential?.status
  || props.client.credential_summary?.status
  || 'missing'
)

const accordionItems = computed((): AccordionItem[] => [
  {
    label: 'Estado e canais',
    icon: 'i-lucide-activity',
    value: 'estado',
    slot: 'estado' as const
  },
  {
    label: 'Certificado A1',
    icon: 'i-lucide-badge-check',
    value: 'certificado',
    slot: 'certificado' as const
  },
  {
    label: 'Campos personalizados',
    icon: 'i-lucide-list',
    value: 'campos',
    slot: 'campos' as const
  }
])

function openCertificado() {
  openItems.value = ['estado', 'certificado']
}

async function toggleCapture(enabled: boolean) {
  const est = primaryEstablishment.value
  if (!est || !props.canManageClients || savingCapture.value) return
  savingCapture.value = true
  try {
    await api.establishments.update(est.id, {
      capture_enabled: enabled,
      ...(enabled ? { capture_enable_reason: 'Habilitado na configuração do cliente.' } : {})
    })
    toast.add({
      title: enabled ? 'Captura de documentos habilitada.' : 'Captura de documentos desabilitada.',
      color: 'success'
    })
    emit('updated')
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível alterar a captura.'), color: 'error' })
  } finally {
    savingCapture.value = false
  }
}

async function toggleFieldActive(field: ClientCustomField, active: boolean) {
  if (!props.canManageClients) return
  savingFieldId.value = field.id
  try {
    await api.clients.updateCustomField(props.client.id, field.id, { is_active: active })
    toast.add({ title: active ? 'Campo ativado.' : 'Campo desativado.', color: 'success' })
    emit('updated')
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível atualizar o campo.'), color: 'error' })
  } finally {
    savingFieldId.value = null
  }
}

function procuracaoLabel(status?: string | null) {
  switch (status) {
    case 'authorized': return 'Autorizada'
    case 'expiring': return 'Expirando'
    case 'expired': return 'Expirada'
    case 'missing': return 'Ausente'
    case 'unverified': return 'Não verificada'
    default: return 'Desconhecida'
  }
}

function credentialLabel(status: string) {
  switch (status) {
    case 'active': return 'Ativo'
    case 'expired': return 'Expirado'
    case 'revoked': return 'Revogado'
    default: return 'Não cadastrado'
  }
}
</script>

<template>
  <ShellPanelAccordion
    v-model="openItems"
    :items="accordionItems"
    type="multiple"
    :default-value="['estado']"
    test-id="client-config-accordion"
  >
    <template #estado-body>
      <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <UPageCard
            title="Certificado A1"
            :description="credentialLabel(credentialStatus)"
            icon="i-lucide-badge-check"
            variant="subtle"
          >
            <template #footer>
              <div class="flex flex-wrap items-center gap-2">
                <UBadge
                  :color="credentialStatus === 'active' ? 'success' : 'neutral'"
                  variant="subtle"
                >
                  {{ credentialLabel(credentialStatus) }}
                </UBadge>
                <UButton
                  size="sm"
                  color="neutral"
                  variant="ghost"
                  label="Gerenciar"
                  trailing-icon="i-lucide-chevron-down"
                  @click="openCertificado"
                />
              </div>
            </template>
          </UPageCard>

          <UPageCard
            title="Procuração e-CAC"
            :description="procuracaoLabel(client.procuracao_status)"
            icon="i-lucide-file-key-2"
            variant="subtle"
          >
            <template #footer>
              <UBadge
                color="neutral"
                variant="subtle"
              >
                {{ procuracaoLabel(client.procuracao_status) }}
              </UBadge>
            </template>
          </UPageCard>
        </div>

        <div class="space-y-3 rounded-lg border border-default p-4">
          <UFormField
            label="Captura de documentos (ADN)"
            description="Habilita sincronização de documentos de entrada para o estabelecimento principal."
            class="flex items-center justify-between gap-4"
          >
            <USwitch
              :model-value="captureEnabled"
              :disabled="!canManageClients || !primaryEstablishment || savingCapture"
              @update:model-value="toggleCapture"
            />
          </UFormField>
        </div>
      </div>
    </template>

    <template #certificado-body>
      <ClientsClientCredentialPanel
        :client-id="client.id"
        :credential="credential"
        :credential-summary="client.credential_summary"
        :can-manage-credentials="canManageCredentials"
        :show-header="false"
        @activated="emit('credentialActivated', $event)"
      />
    </template>

    <template #campos-body>
      <div
        class="space-y-3"
        data-testid="client-custom-fields"
      >
        <div
          v-if="customFields.length"
          class="space-y-3"
        >
          <div
            v-for="field in customFields"
            :key="field.id"
            class="flex flex-col gap-3 rounded-lg bg-elevated/50 px-3 py-3 ring ring-inset ring-default sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="min-w-0">
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
            <UFormField
              label="Ativo"
              class="shrink-0"
            >
              <USwitch
                :model-value="field.is_active !== false"
                :disabled="!canManageClients || savingFieldId === field.id"
                @update:model-value="toggleFieldActive(field, $event)"
              />
            </UFormField>
          </div>
        </div>
        <UEmpty
          v-else
          icon="i-lucide-list"
          title="Nenhum campo personalizado"
          description="Campos extras (ex.: acesso a portal) são definidos na criação do cliente."
        />
      </div>
    </template>
  </ShellPanelAccordion>
</template>
