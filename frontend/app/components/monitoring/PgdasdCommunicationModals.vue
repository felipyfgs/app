<script setup lang="ts">
import type {
  PgdasdCommunicationChannel,
  PgdasdCommunicationPreference,
  PgdasdCommunicationPreview,
  PgdasdCommunicationTracking
} from '~/types/fiscal-modules'
import { usePgdasdMonitoring } from '~/composables/usePgdasdMonitoring'
import { usePgmeiMonitoring } from '~/composables/usePgmeiMonitoring'
import { apiErrorMessage } from '~/utils/api-error'
import { formatDateTime } from '~/utils/format'
import { formatPgdasdPeriod, pgdasdTrackingMeta } from '~/utils/pgdasd'

const props = defineProps<{
  previewOpen: boolean
  trackingOpen: boolean
  prefsOpen: boolean
  clientId: number | null
  clientName?: string | null
  preference?: PgdasdCommunicationPreference | null
  canManage?: boolean
  /** Mantém o mesmo shell TEMPLATE_ONLY, isolando as APIs por domínio. */
  context?: 'PGDASD' | 'PGMEI'
  year?: number | null
}>()

const emit = defineEmits<{
  'update:previewOpen': [value: boolean]
  'update:trackingOpen': [value: boolean]
  'update:prefsOpen': [value: boolean]
  'saved': [preference: PgdasdCommunicationPreference]
}>()

const pgdasdMonitoring = usePgdasdMonitoring()
const pgmeiMonitoring = usePgmeiMonitoring()
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
  // A preferência ainda inexistente é representada pelo backend com versão 0.
  lock_version: 0
})
let previewGeneration = 0
let trackingGeneration = 0

const isPgmei = computed(() => props.context === 'PGMEI')

function fetchPreview(clientId: number) {
  return isPgmei.value
    ? pgmeiMonitoring.fetchPreview(clientId)
    : pgdasdMonitoring.fetchPreview(clientId)
}

function fetchTracking(clientId: number) {
  return isPgmei.value
    ? pgmeiMonitoring.fetchTracking(clientId)
    : pgdasdMonitoring.fetchTracking(clientId)
}

function updatePreferences(
  clientId: number,
  body: {
    automatic_requested: boolean
    email_enabled: boolean
    whatsapp_enabled: boolean
    lock_version: number
  }
) {
  return isPgmei.value
    ? pgmeiMonitoring.updatePreferences(clientId, body)
    : pgdasdMonitoring.updatePreferences(clientId, body)
}

function documentDownloadHref(document: { id: number, download_href?: string | null }): string {
  if (isPgmei.value) return document.download_href || '#'
  return pgdasdMonitoring.artifactDownloadUrl(document.id)
}

function hydrateForm(preference?: PgdasdCommunicationPreference | null) {
  form.automatic_requested = preference?.automatic_requested === true
  form.email_enabled = preference?.email_enabled === true
  form.whatsapp_enabled = preference?.whatsapp_enabled === true
  const lv = Number(preference?.lock_version)
  form.lock_version = Number.isFinite(lv) && lv >= 0 ? lv : 0
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
  if (!clientId) return
  const generation = ++previewGeneration
  previewLoading.value = true
  previewError.value = null
  try {
    const response = await fetchPreview(clientId)
    if (generation !== previewGeneration) return
    preview.value = response
    hydrateForm(response.preferences || props.preference)
  } catch (caught) {
    if (generation !== previewGeneration) return
    previewError.value = apiErrorMessage(caught, 'Não foi possível carregar a prévia local.')
    preview.value = null
    hydrateForm(props.preference)
  } finally {
    if (generation === previewGeneration) previewLoading.value = false
  }
}

async function loadTracking() {
  const clientId = props.clientId
  if (!clientId) return
  const generation = ++trackingGeneration
  trackingLoading.value = true
  trackingError.value = null
  try {
    const response = await fetchTracking(clientId)
    if (generation === trackingGeneration) tracking.value = response
  } catch (caught) {
    if (generation !== trackingGeneration) return
    trackingError.value = apiErrorMessage(caught, 'Não foi possível carregar o rastreio local.')
    tracking.value = null
  } finally {
    if (generation === trackingGeneration) trackingLoading.value = false
  }
}

watch(
  () => [props.previewOpen, props.prefsOpen, props.clientId] as const,
  ([previewOpen, prefsOpen]) => {
    if (previewOpen || prefsOpen) {
      hydrateForm(props.preference)
      void loadPreview()
    } else {
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
    if (open) {
      void loadTracking()
    } else {
      trackingGeneration += 1
      tracking.value = null
      trackingError.value = null
    }
  },
  { immediate: true }
)

async function savePreferences() {
  if (!props.clientId || !props.canManage || saving.value) return
  preferenceError.value = null
  if (!automaticConfigurationValid.value) {
    preferenceError.value = 'Para ligar o automático, habilite um canal com contato ativo e elegível.'
    return
  }

  saving.value = true
  try {
    const saved = await updatePreferences(props.clientId, {
      automatic_requested: form.automatic_requested,
      email_enabled: form.email_enabled,
      whatsapp_enabled: form.whatsapp_enabled,
      lock_version: form.lock_version
    })
    hydrateForm(saved)
    emit('saved', saved)
    emit('update:prefsOpen', false)
    toast.add({
      title: 'Preferências salvas.',
      description: 'Apenas a intenção foi registrada; nenhum envio foi realizado.',
      color: 'success'
    })
  } catch (caught) {
    preferenceError.value = apiErrorMessage(
      caught,
      'Não foi possível salvar. Recarregue para obter a versão mais recente.'
    )
  } finally {
    saving.value = false
  }
}

function openPreferences() {
  emit('update:previewOpen', false)
  emit('update:prefsOpen', true)
}
</script>

<template>
  <UModal
    :open="previewOpen"
    title="Prévia de comunicação"
    description="Somente visualização; nenhum envio será realizado."
    scrollable
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-3xl', body: 'max-h-[70vh] overflow-y-auto' }"
    @update:open="emit('update:previewOpen', $event)"
  >
    <template #body>
      <div
        class="space-y-4"
        :data-testid="isPgmei ? 'pgmei-communication-preview' : 'pgdasd-communication-preview'"
      >
        <div>
          <p class="font-medium text-highlighted">
            {{ preview?.client?.legal_name || clientName || `Cliente #${clientId || '—'}` }}
          </p>
          <p class="text-xs text-muted">
            {{ isPgmei ? `Ano ${year || '—'}` : `PA ${formatPgdasdPeriod(preview?.period_key)}` }}
          </p>
        </div>

        <UAlert
          color="warning"
          variant="subtle"
          icon="i-lucide-construction"
          title="Modo template"
          description="E-mail e WhatsApp ainda não possuem execução nesta capacidade."
        />

        <UAlert v-if="previewError" color="error" :title="previewError">
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar novamente"
              @click="loadPreview"
            />
          </template>
        </UAlert>
        <div v-if="previewLoading" class="space-y-3" aria-label="Carregando prévia">
          <USkeleton class="h-20 w-full" />
          <USkeleton class="h-28 w-full" />
        </div>

        <template v-else-if="preview">
          <section>
            <h3 class="mb-2 text-sm font-medium">
              Canais e destinatários
            </h3>
            <div v-if="preview.channels?.length" class="grid gap-2 sm:grid-cols-2">
              <div
                v-for="channel in preview.channels"
                :key="channel.channel"
                class="rounded-md border border-default p-3"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="font-medium text-highlighted">{{ channelLabel(channel.channel) }}</span>
                  <UBadge
                    :color="channel.eligible ? 'success' : 'warning'"
                    :label="channel.eligible ? 'Elegível' : 'Não configurado'"
                    variant="subtle"
                  />
                </div>
                <ul v-if="channel.recipients?.length" class="mt-2 space-y-1 text-xs text-muted">
                  <li v-for="recipient in channel.recipients" :key="`${channel.channel}-${recipient.contact_id}-${recipient.masked}`">
                    {{ recipient.name || 'Contato' }} · {{ recipient.masked || 'destinatário protegido' }}
                  </li>
                </ul>
                <p v-else class="mt-2 text-xs text-muted">
                  Nenhum contato elegível.
                </p>
              </div>
            </div>
            <p v-else class="text-sm text-muted">
              Nenhum canal configurado.
            </p>
          </section>

          <section>
            <h3 class="mb-2 text-sm font-medium">
              Documentos locais
            </h3>
            <ul v-if="preview.documents?.length" class="space-y-2">
              <li
                v-for="document in preview.documents"
                :key="document.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-default p-2 text-sm"
              >
                <span>{{ document.filename || document.kind || `Documento #${document.id}` }}</span>
                <UButton
                  size="xs"
                  color="neutral"
                  variant="outline"
                  icon="i-lucide-download"
                  label="Baixar"
                  :to="documentDownloadHref(document)"
                  external
                  target="_blank"
                  rel="noopener noreferrer"
                />
              </li>
            </ul>
            <p v-else class="text-sm text-muted">
              Nenhum documento local disponível para a prévia.
            </p>
          </section>

          <UAlert
            v-for="warning in preview.warnings || []"
            :key="warning"
            color="warning"
            variant="subtle"
            :title="warning"
          />
        </template>
      </div>
    </template>
    <template #footer>
      <div class="flex w-full flex-wrap justify-end gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          label="Fechar"
          @click="emit('update:previewOpen', false)"
        />
        <UButton
          v-if="canManage"
          color="neutral"
          variant="outline"
          icon="i-lucide-settings-2"
          label="Preferências"
          @click="openPreferences"
        />
        <UTooltip text="Envio real não implementado nesta etapa">
          <UButton
            color="primary"
            icon="i-lucide-send"
            label="Enviar agora"
            :disabled="!preview?.can_send"
          />
        </UTooltip>
      </div>
    </template>
  </UModal>

  <UModal
    :open="prefsOpen"
    title="Preferências de comunicação"
    description="Registra intenção de uso; o envio automático permanece inativo."
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-xl' }"
    @update:open="emit('update:prefsOpen', $event)"
  >
    <template #body>
      <div
        class="space-y-4"
        :data-testid="isPgmei ? 'pgmei-communication-preferences' : 'pgdasd-communication-preferences'"
      >
        <UAlert
          color="warning"
          variant="subtle"
          icon="i-lucide-construction"
          title="TEMPLATE_ONLY · automatic_effective = false"
        />
        <UAlert v-if="preferenceError || previewError" color="error" :title="preferenceError || previewError || ''" />

        <UFormField
          label="E-mail"
          description="Usar contatos ativos marcados para receber alertas."
        >
          <USwitch v-model="form.email_enabled" :disabled="!canManage || saving" aria-label="Habilitar canal e-mail" />
        </UFormField>
        <UFormField
          label="WhatsApp"
          description="Usar somente contatos ativos identificados como WhatsApp."
        >
          <USwitch v-model="form.whatsapp_enabled" :disabled="!canManage || saving" aria-label="Habilitar canal WhatsApp" />
        </UFormField>
        <USeparator />
        <UFormField
          label="Automático"
          description="Salva a intenção, mas não ativa qualquer provider ou envio."
        >
          <USwitch v-model="form.automatic_requested" :disabled="!canManage || saving" aria-label="Solicitar comunicação automática" />
        </UFormField>

        <UAlert
          v-if="form.automatic_requested && !automaticConfigurationValid"
          color="warning"
          variant="subtle"
          title="Selecione um canal com destinatário elegível."
        />
        <p v-if="!canManage" class="text-xs text-muted">
          VIEWER possui acesso somente leitura.
        </p>
      </div>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          color="neutral"
          variant="ghost"
          label="Cancelar"
          @click="emit('update:prefsOpen', false)"
        />
        <UButton
          v-if="canManage"
          color="primary"
          label="Salvar preferências"
          :loading="saving"
          :disabled="previewLoading"
          @click="savePreferences"
        />
      </div>
    </template>
  </UModal>

  <UModal
    :open="trackingOpen"
    title="Rastreio de comunicação"
    description="Somente leitura; abrir este modal não altera o estado de entrega."
    scrollable
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-3xl', body: 'max-h-[70vh] overflow-y-auto' }"
    @update:open="emit('update:trackingOpen', $event)"
  >
    <template #body>
      <div
        class="space-y-4"
        :data-testid="isPgmei ? 'pgmei-communication-tracking' : 'pgdasd-communication-tracking'"
      >
        <UAlert v-if="trackingError" color="error" :title="trackingError">
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar novamente"
              @click="loadTracking"
            />
          </template>
        </UAlert>
        <div v-if="trackingLoading" class="space-y-3" aria-label="Carregando rastreio">
          <USkeleton class="h-16 w-full" />
          <USkeleton class="h-28 w-full" />
        </div>

        <template v-else-if="tracking">
          <UBadge
            :color="pgdasdTrackingMeta(tracking.status).color"
            :icon="pgdasdTrackingMeta(tracking.status).icon"
            :label="pgdasdTrackingMeta(tracking.status).label"
            variant="subtle"
          />

          <div v-if="tracking.channels?.length" class="space-y-3">
            <UCard v-for="channel in tracking.channels" :key="channel.channel">
              <template #header>
                <div class="flex items-center justify-between gap-2">
                  <span class="font-medium text-highlighted">{{ channelLabel(channel.channel) }}</span>
                  <UBadge
                    :color="pgdasdTrackingMeta(channel.status).color"
                    :label="pgdasdTrackingMeta(channel.status).label"
                    variant="subtle"
                  />
                </div>
              </template>

              <div v-if="channel.dispatches?.length" class="space-y-3">
                <div
                  v-for="dispatch in channel.dispatches"
                  :key="dispatch.id || `${dispatch.period_key}-${dispatch.recipient_masked}`"
                  class="rounded-md border border-default p-3 text-sm"
                >
                  <p class="font-medium text-highlighted">
                    {{ dispatch.recipient_masked || 'Destinatário protegido' }} ·
                    {{ isPgmei ? `Ano ${dispatch.period_key || '—'}` : `PA ${formatPgdasdPeriod(dispatch.period_key)}` }}
                  </p>
                  <p class="mt-1 text-xs text-muted">
                    {{ pgdasdTrackingMeta(dispatch.status).label }} ·
                    {{ formatDateTime(dispatch.read_at || dispatch.delivered_at || dispatch.sent_at || dispatch.queued_at || dispatch.failed_at || dispatch.canceled_at) }}
                  </p>
                  <ul v-if="dispatch.events?.length" class="mt-2 border-l border-default pl-3 text-xs text-muted">
                    <li v-for="event in dispatch.events" :key="`${event.status}-${event.occurred_at}`">
                      {{ pgdasdTrackingMeta(event.status).label }} · {{ formatDateTime(event.occurred_at) }}
                    </li>
                  </ul>
                </div>
              </div>
              <p v-else class="text-sm text-muted">
                Nenhum envio registrado neste canal.
              </p>
            </UCard>
          </div>

          <div v-else class="py-10 text-center">
            <UIcon name="i-lucide-message-square-dashed" class="mx-auto mb-2 size-8 text-dimmed" />
            <p class="font-medium text-highlighted">
              Nenhum envio registrado
            </p>
            <p class="text-sm text-muted">
              O modo template não fabrica eventos de entrega ou leitura.
            </p>
          </div>
        </template>
      </div>
    </template>
  </UModal>
</template>
