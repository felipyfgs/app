<script setup lang="ts">
import type {
  PgdasdCommunicationChannel,
  PgdasdCommunicationPreference,
  PgdasdCommunicationPreview,
  PgdasdCommunicationTracking
} from '~/types/fiscal-modules'
import { usePgmeiMonitoring } from '~/composables/usePgmeiMonitoring'
import { apiErrorMessage } from '~/utils/api-error'
import { formatDateTime } from '~/utils/format'
import { pgdasdTrackingMeta } from '~/utils/pgdasd'

const props = defineProps<{
  previewOpen: boolean
  trackingOpen: boolean
  prefsOpen: boolean
  clientId: number | null
  clientName?: string | null
  preference?: PgdasdCommunicationPreference | null
  canManage?: boolean
}>()

const emit = defineEmits<{
  'update:previewOpen': [value: boolean]
  'update:trackingOpen': [value: boolean]
  'update:prefsOpen': [value: boolean]
  'saved': [preference: PgdasdCommunicationPreference]
}>()

const { fetchPreview, fetchTracking, updatePreferences } = usePgmeiMonitoring()
const toast = useToast()

const previewLoading = ref(false)
const previewError = ref<string | null>(null)
const preview = ref<PgdasdCommunicationPreview | null>(null)
const trackingLoading = ref(false)
const trackingError = ref<string | null>(null)
const tracking = ref<PgdasdCommunicationTracking | null>(null)
const saving = ref(false)
const preferenceError = ref<string | null>(null)
const form = reactive({
  automatic_requested: false,
  email_enabled: false,
  whatsapp_enabled: false,
  lock_version: 1
})
let previewGeneration = 0
let trackingGeneration = 0

function hydrateForm(preference?: PgdasdCommunicationPreference | null) {
  form.automatic_requested = preference?.automatic_requested === true
  form.email_enabled = preference?.email_enabled === true
  form.whatsapp_enabled = preference?.whatsapp_enabled === true
  const lv = Number(preference?.lock_version)
  form.lock_version = Number.isFinite(lv) && lv >= 1 ? lv : 1
}

function channelLabel(channel?: string | null): string {
  return channel === 'WHATSAPP' ? 'WhatsApp' : channel === 'EMAIL' ? 'E-mail' : channel || 'Canal'
}

function channelIsEligible(channel: PgdasdCommunicationChannel): boolean {
  return Boolean(preview.value?.channels?.some(item =>
    item.channel === channel && item.eligible === true && (item.recipients?.length || 0) > 0
  ))
}

const automaticConfigurationValid = computed(() => {
  if (!form.automatic_requested) return true
  return (form.email_enabled && channelIsEligible('EMAIL'))
    || (form.whatsapp_enabled && channelIsEligible('WHATSAPP'))
})

async function loadPreview() {
  const clientId = props.clientId
  if (!props.previewOpen || !clientId) return
  const generation = ++previewGeneration
  previewLoading.value = true
  previewError.value = null
  try {
    const data = await fetchPreview(clientId)
    if (generation === previewGeneration) preview.value = data
  } catch (caught) {
    if (generation !== previewGeneration) return
    previewError.value = apiErrorMessage(caught, 'Falha ao carregar prévia.')
    preview.value = null
  } finally {
    if (generation === previewGeneration) previewLoading.value = false
  }
}

async function loadTracking() {
  const clientId = props.clientId
  if (!props.trackingOpen || !clientId) return
  const generation = ++trackingGeneration
  trackingLoading.value = true
  trackingError.value = null
  try {
    const data = await fetchTracking(clientId)
    if (generation === trackingGeneration) tracking.value = data
  } catch (caught) {
    if (generation !== trackingGeneration) return
    trackingError.value = apiErrorMessage(caught, 'Falha ao carregar rastreio.')
    tracking.value = null
  } finally {
    if (generation === trackingGeneration) trackingLoading.value = false
  }
}

watch(
  () => [props.previewOpen, props.clientId] as const,
  ([open]) => {
    if (open) void loadPreview()
    else {
      previewGeneration += 1
      preview.value = null
      previewError.value = null
    }
  },
  { immediate: true }
)

watch(
  () => [props.trackingOpen, props.clientId] as const,
  ([open]) => {
    if (open) void loadTracking()
    else {
      trackingGeneration += 1
      tracking.value = null
      trackingError.value = null
    }
  },
  { immediate: true }
)

watch(
  () => [props.prefsOpen, props.preference] as const,
  ([open]) => {
    if (open) {
      hydrateForm(props.preference)
      preferenceError.value = null
    }
  },
  { immediate: true }
)

async function savePreferences() {
  if (!props.canManage || !props.clientId || saving.value) return
  if (!automaticConfigurationValid.value) {
    preferenceError.value = 'Ativação exige canal habilitado com destinatário elegível.'
    return
  }
  saving.value = true
  preferenceError.value = null
  try {
    const saved = await updatePreferences(props.clientId, {
      automatic_requested: form.automatic_requested,
      email_enabled: form.email_enabled,
      whatsapp_enabled: form.whatsapp_enabled,
      lock_version: form.lock_version
    })
    emit('saved', saved)
    emit('update:prefsOpen', false)
    toast.add({
      title: 'Preferências PGMEI salvas.',
      description: 'Modo template: nenhum envio foi realizado.',
      color: 'success'
    })
  } catch (caught) {
    preferenceError.value = apiErrorMessage(caught, 'Não foi possível salvar.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <ShellScrollableModal
    :open="previewOpen"
    title="Prévia de envio PGMEI"
    :description="`${clientName || 'Cliente'} · TEMPLATE_ONLY · can_send=false`"
    test-id="pgmei-preview-modal"
    :show-default-footer="false"
    @update:open="emit('update:previewOpen', $event)"
    @cancel="emit('update:previewOpen', false)"
  >
    <template #body>
      <ShellLoadingModalBody v-if="previewLoading" />
      <UAlert
        v-else-if="previewError"
        color="error"
        :title="previewError"
      />
      <div
        v-else-if="preview"
        class="space-y-3 text-sm"
      >
        <UAlert
          color="info"
          icon="i-lucide-info"
          title="Nenhum envio real será disparado."
        >
          <template #description>
            Destinatários mascarados; execução apenas em modo template.
          </template>
        </UAlert>
        <div
          v-for="channel in preview.channels || []"
          :key="String(channel.channel)"
          class="border-default rounded-md border p-3"
        >
          <div class="font-medium">
            {{ channelLabel(channel.channel) }}
            <UBadge
              class="ml-2"
              size="sm"
              :label="channel.enabled ? 'Habilitado' : 'Off'"
              :color="channel.enabled ? 'success' : 'neutral'"
              variant="subtle"
            />
          </div>
          <ul class="text-muted mt-1 list-inside list-disc">
            <li
              v-for="(recipient, idx) in channel.recipients || []"
              :key="idx"
            >
              {{ recipient.masked || recipient.name || '***' }}
            </li>
            <li v-if="!(channel.recipients?.length)">
              Sem destinatário elegível
            </li>
          </ul>
        </div>
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Fechar"
        :show-submit="false"
        @cancel="emit('update:previewOpen', false)"
      />
    </template>
  </ShellScrollableModal>

  <ShellScrollableModal
    :open="trackingOpen"
    title="Rastreio PGMEI"
    description="Somente leitura; abrir este modal não altera o estado de entrega."
    test-id="pgmei-tracking-modal"
    :show-default-footer="false"
    @update:open="emit('update:trackingOpen', $event)"
    @cancel="emit('update:trackingOpen', false)"
  >
    <template #body>
      <ShellLoadingModalBody v-if="trackingLoading" />
      <UAlert
        v-else-if="trackingError"
        color="error"
        :title="trackingError"
      />
      <div
        v-else-if="tracking"
        class="space-y-3 text-sm"
      >
        <UBadge
          :label="pgdasdTrackingMeta(tracking.status).label"
          :color="pgdasdTrackingMeta(tracking.status).color"
          variant="subtle"
        />
        <div
          v-for="channel in tracking.channels || []"
          :key="String(channel.channel)"
          class="border-default rounded-md border p-3"
        >
          <div class="font-medium">
            {{ channelLabel(channel.channel) }} · {{ channel.status }}
          </div>
          <ul
            v-if="channel.dispatches?.length"
            class="text-muted mt-1 space-y-1"
          >
            <li
              v-for="(dispatch, idx) in channel.dispatches"
              :key="dispatch.id ?? idx"
            >
              {{ dispatch.recipient_masked || '***' }}
              · {{ dispatch.status }}
              <span v-if="dispatch.queued_at"> · {{ formatDateTime(dispatch.queued_at) }}</span>
            </li>
          </ul>
          <p
            v-else
            class="text-muted mt-1"
          >
            Sem histórico de envio.
          </p>
        </div>
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Fechar"
        :show-submit="false"
        @cancel="emit('update:trackingOpen', false)"
      />
    </template>
  </ShellScrollableModal>

  <ShellScrollableModal
    :open="prefsOpen"
    title="Comunicação PGMEI"
    description="Modo template; automatic_effective permanece false."
    test-id="pgmei-prefs-modal"
    :show-default-footer="false"
    @update:open="emit('update:prefsOpen', $event)"
    @cancel="emit('update:prefsOpen', false)"
  >
    <template #body>
      <div class="space-y-4">
        <UAlert
          color="info"
          title="Modo template"
        >
          <template #description>
            automatic_effective permanece false; nenhum job ou e-mail é criado.
          </template>
        </UAlert>
        <UFormField label="E-mail">
          <USwitch v-model="form.email_enabled" />
        </UFormField>
        <UFormField label="WhatsApp">
          <USwitch v-model="form.whatsapp_enabled" />
        </UFormField>
        <UFormField label="Envio automático (intenção)">
          <USwitch v-model="form.automatic_requested" />
        </UFormField>
        <UAlert
          v-if="preferenceError"
          color="error"
          :title="preferenceError"
        />
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Cancelar"
        submit-label="Salvar"
        :loading="saving"
        :disabled="!canManage"
        :show-submit="Boolean(canManage)"
        @cancel="emit('update:prefsOpen', false)"
        @submit="savePreferences"
      />
    </template>
  </ShellScrollableModal>
</template>
