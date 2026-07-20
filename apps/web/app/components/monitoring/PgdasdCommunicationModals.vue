<script setup lang="ts">
import type {
  PgdasdCommunicationPreference,
  PgdasdCommunicationPreview,
  PgdasdCommunicationTracking
} from '~/types/fiscal-modules'
import { useDctfwebMonitoring } from '~/composables/useDctfwebMonitoring'
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
  /** Mantém o mesmo shell TEMPLATE_ONLY, isolando as APIs por domínio. */
  context?: 'PGDASD' | 'PGMEI' | 'DCTFWEB'
  year?: number | null
}>()

const emit = defineEmits<{
  'update:previewOpen': [value: boolean]
  'update:trackingOpen': [value: boolean]
  'update:prefsOpen': [value: boolean]
}>()

const pgdasdMonitoring = usePgdasdMonitoring()
const pgmeiMonitoring = usePgmeiMonitoring()
const dctfwebMonitoring = useDctfwebMonitoring()

const previewLoading = ref(false)
const previewError = ref<string | null>(null)
const preview = ref<PgdasdCommunicationPreview | null>(null)
const trackingLoading = ref(false)
const trackingError = ref<string | null>(null)
const tracking = ref<PgdasdCommunicationTracking | null>(null)
let previewGeneration = 0
let trackingGeneration = 0

const isPgmei = computed(() => props.context === 'PGMEI')
const isDctfweb = computed(() => props.context === 'DCTFWEB')

function fetchPreview(clientId: number) {
  if (isPgmei.value) return pgmeiMonitoring.fetchPreview(clientId)
  if (isDctfweb.value) return dctfwebMonitoring.fetchPreview(clientId)
  return pgdasdMonitoring.fetchPreview(clientId)
}

function fetchTracking(clientId: number) {
  if (isPgmei.value) return pgmeiMonitoring.fetchTracking(clientId)
  if (isDctfweb.value) return dctfwebMonitoring.fetchTracking(clientId)
  return pgdasdMonitoring.fetchTracking(clientId)
}

function documentDownloadHref(document: { id: number, download_href?: string | null }): string | undefined {
  if (isPgmei.value) return document.download_href?.trim() || undefined
  if (isDctfweb.value && props.clientId) {
    return dctfwebMonitoring.evidenceDownloadUrl(props.clientId, document.id)
  }
  return pgdasdMonitoring.artifactDownloadUrl(document.id)
}

function channelLabel(channel?: string | null): string {
  return channel === 'WHATSAPP' ? 'WhatsApp' : channel === 'EMAIL' ? 'E-mail' : channel || 'Canal'
}

const displayedPreference = computed(() => preview.value?.preferences || props.preference || null)

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
  } catch (caught) {
    if (generation !== previewGeneration) return
    previewError.value = apiErrorMessage(caught, 'Não foi possível carregar a prévia local.')
    preview.value = null
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

function openPreferences() {
  emit('update:previewOpen', false)
  emit('update:prefsOpen', true)
}
</script>

<template>
  <ShellScrollableModal
    :open="previewOpen"
    title="Informações de comunicação"
    description="Destinatários, documentos e preferências locais em modo somente leitura."
    content-class="w-[calc(100vw-1rem)] sm:max-w-3xl"
    :test-id="isPgmei ? 'pgmei-communication-preview' : 'pgdasd-communication-preview'"
    :show-default-footer="false"
    @update:open="emit('update:previewOpen', $event)"
    @cancel="emit('update:previewOpen', false)"
  >
    <template #body>
      <div class="space-y-4">
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
          icon="i-lucide-info"
          title="Comunicação externa indisponível"
        >
          <template #description>
            Nenhum provider de e-mail ou WhatsApp está instalado nesta capacidade. Esta tela não envia mensagens.
          </template>
        </UAlert>

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
        <ShellLoadingModalBody v-if="previewLoading" :rows="2" />

        <template v-else-if="preview">
          <section>
            <h3 class="mb-2 text-sm font-medium">
              Canais cadastrados e destinatários protegidos
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
                    :label="channel.eligible ? 'Contato disponível' : 'Sem contato disponível'"
                    variant="subtle"
                  />
                </div>
                <ul v-if="channel.recipients?.length" class="mt-2 space-y-1 text-xs text-muted">
                  <li v-for="recipient in channel.recipients" :key="`${channel.channel}-${recipient.contact_id}-${recipient.masked}`">
                    {{ recipient.name || 'Contato' }} · {{ recipient.masked || 'destinatário protegido' }}
                  </li>
                </ul>
                <p v-else class="mt-2 text-xs text-muted">
                  Nenhum contato disponível.
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
                  v-if="documentDownloadHref(document)"
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
          color="neutral"
          variant="outline"
          icon="i-lucide-settings-2"
          label="Ver preferências registradas"
          @click="openPreferences"
        />
      </div>
    </template>
  </ShellScrollableModal>

  <ShellScrollableModal
    :open="prefsOpen"
    title="Preferências registradas"
    description="Consulta somente leitura do cadastro legado de comunicação."
    content-class="w-[calc(100vw-1rem)] sm:max-w-xl"
    :test-id="isPgmei ? 'pgmei-communication-preferences' : 'pgdasd-communication-preferences'"
    :show-default-footer="false"
    @update:open="emit('update:prefsOpen', $event)"
    @cancel="emit('update:prefsOpen', false)"
  >
    <template #body>
      <div class="space-y-4">
        <UAlert
          color="warning"
          variant="subtle"
          icon="i-lucide-info"
          title="Nenhuma preferência ativa envio"
          description="Os valores abaixo são informativos. O workspace não oferece envio imediato ou automático."
        />
        <UAlert v-if="previewError" color="error" :title="previewError" />

        <dl class="divide-y divide-default rounded-lg border border-default">
          <div class="flex items-center justify-between gap-3 p-3">
            <dt class="text-sm text-highlighted">
              E-mail
            </dt>
            <dd><UBadge color="neutral" variant="soft" :label="displayedPreference?.email_enabled ? 'Preferência registrada' : 'Não registrado'" /></dd>
          </div>
          <div class="flex items-center justify-between gap-3 p-3">
            <dt class="text-sm text-highlighted">
              WhatsApp
            </dt>
            <dd><UBadge color="neutral" variant="soft" :label="displayedPreference?.whatsapp_enabled ? 'Preferência registrada' : 'Não registrado'" /></dd>
          </div>
          <div class="flex items-center justify-between gap-3 p-3">
            <dt class="text-sm text-highlighted">
              Intenção automática legada
            </dt>
            <dd><UBadge color="warning" variant="outline" :label="displayedPreference?.automatic_requested ? 'Registrada, porém inativa' : 'Não registrada'" /></dd>
          </div>
        </dl>
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Fechar"
        :show-submit="false"
        @cancel="emit('update:prefsOpen', false)"
      />
    </template>
  </ShellScrollableModal>

  <ShellScrollableModal
    :open="trackingOpen"
    title="Histórico local de comunicação"
    description="Registros já existentes; abrir este modal não envia nem altera entregas."
    content-class="w-[calc(100vw-1rem)] sm:max-w-3xl"
    :test-id="isPgmei ? 'pgmei-communication-tracking' : 'pgdasd-communication-tracking'"
    :show-default-footer="false"
    @update:open="emit('update:trackingOpen', $event)"
    @cancel="emit('update:trackingOpen', false)"
  >
    <template #body>
      <div class="space-y-4">
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
        <ShellLoadingModalBody v-if="trackingLoading" :rows="2" />

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
                Nenhum registro histórico neste canal.
              </p>
            </UCard>
          </div>

          <div v-else class="py-10 text-center">
            <UIcon name="i-lucide-message-square-dashed" class="mx-auto mb-2 size-8 text-dimmed" />
            <p class="font-medium text-highlighted">
              Nenhum histórico registrado
            </p>
            <p class="text-sm text-muted">
              O workspace não fabrica eventos de entrega ou leitura.
            </p>
          </div>
        </template>
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
</template>
