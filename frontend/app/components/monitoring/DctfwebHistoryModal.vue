<script setup lang="ts">
import type {
  DctfwebEvidenceDescriptor,
  DctfwebHistoryPayload,
  DctfwebHistoryPeriod
} from '~/types/fiscal-modules'
import { useDctfwebMonitoring } from '~/composables/useDctfwebMonitoring'
import { apiErrorMessage } from '~/utils/api-error'
import { formatBytes, formatDateTime } from '~/utils/format'
import {
  dctfwebDeclarationMeta,
  dctfwebHistoryPeriods,
  formatDctfwebPeriod
} from '~/utils/dctfweb'

const props = defineProps<{
  open: boolean
  clientId: number | null
  clientName?: string | null
  cnpjMasked?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { fetchHistory, evidenceDownloadUrl } = useDctfwebMonitoring()
const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const history = ref<DctfwebHistoryPayload | null>(null)
let requestGeneration = 0

const payload = computed(() => history.value || {})
const periods = computed(() =>
  [...dctfwebHistoryPeriods(history.value)].sort((a, b) =>
    String(b.period_key || '').localeCompare(String(a.period_key || ''))
  )
)
const stateMeta = computed(() => dctfwebDeclarationMeta(payload.value.declaration_state))
const serproCalled = computed(() => payload.value.provenance?.serpro_called === true)

async function loadHistory() {
  const clientId = props.clientId
  if (!props.open || !clientId) return
  const generation = ++requestGeneration
  loading.value = true
  error.value = null
  try {
    const response = await fetchHistory(clientId)
    if (generation === requestGeneration) history.value = response
  } catch (caught) {
    if (generation !== requestGeneration) return
    error.value = apiErrorMessage(caught, 'Não foi possível carregar o histórico local.')
    history.value = null
  } finally {
    if (generation === requestGeneration) loading.value = false
  }
}

watch(
  () => [props.open, props.clientId] as const,
  ([open]) => {
    if (open) {
      void loadHistory()
      return
    }
    requestGeneration += 1
    history.value = null
    error.value = null
    loading.value = false
  },
  { immediate: true }
)

function docsOf(period: DctfwebHistoryPeriod): DctfwebEvidenceDescriptor[] {
  return period.documents || period.artifacts || []
}

function downloadDoc(doc: DctfwebEvidenceDescriptor) {
  if (!props.clientId || !doc.id) return
  const url = evidenceDownloadUrl(props.clientId, doc.id)
  window.open(url, '_blank', 'noopener,noreferrer')
  toast.add({
    title: 'Download iniciado',
    description: 'Documento lido do cofre com autorização do escritório.',
    color: 'success'
  })
}
</script>

<template>
  <UModal
    :open="open"
    :ui="{ content: 'sm:max-w-3xl' }"
    data-testid="dctfweb-history-modal"
    @update:open="emit('update:open', $event)"
  >
    <template #content>
      <UCard>
        <template #header>
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-highlighted font-semibold">
                Histórico de busca DCTFWeb
              </h3>
              <p class="text-muted text-sm mt-0.5">
                {{ clientName || 'Cliente' }}
                <span v-if="cnpjMasked"> · {{ cnpjMasked }}</span>
              </p>
              <p class="text-muted text-xs mt-1">
                Dados locais ·
                <span :class="serproCalled ? 'text-error' : 'text-success'">
                  serpro_called: {{ serproCalled ? 'true' : 'false' }}
                </span>
              </p>
            </div>
            <UButton
              icon="i-lucide-x"
              color="neutral"
              variant="ghost"
              size="sm"
              aria-label="Fechar histórico"
              @click="emit('update:open', false)"
            />
          </div>
        </template>

        <div
          v-if="loading"
          class="flex justify-center py-10"
        >
          <UIcon
            name="i-lucide-loader-circle"
            class="size-6 animate-spin text-muted"
          />
        </div>

        <UAlert
          v-else-if="error"
          color="error"
          icon="i-lucide-triangle-alert"
          :title="error"
        />

        <div
          v-else
          class="space-y-4"
        >
          <div class="flex flex-wrap items-center gap-2">
            <UBadge
              :label="stateMeta.label"
              :color="stateMeta.color"
              :icon="stateMeta.icon"
              variant="subtle"
              class="rounded-sm"
            />
            <span class="text-muted text-sm">
              PA esperado: {{ formatDctfwebPeriod(payload.expected_period_key) }}
            </span>
            <span
              v-if="payload.last_valid_query_at"
              class="text-muted text-sm"
            >
              · Última busca: {{ formatDateTime(payload.last_valid_query_at) }}
            </span>
          </div>

          <div
            v-if="periods.length === 0"
            class="text-muted text-sm py-6 text-center"
          >
            Nenhum histórico local para este cliente.
          </div>

          <div
            v-for="period in periods"
            :key="String(period.period_key)"
            class="border border-default rounded-lg p-3 space-y-2"
            data-testid="dctfweb-history-period"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="font-medium">
                {{ formatDctfwebPeriod(period.period_key) }}
              </div>
              <UBadge
                v-if="period.declaration_state"
                :label="dctfwebDeclarationMeta(period.declaration_state).label"
                :color="dctfwebDeclarationMeta(period.declaration_state).color"
                variant="subtle"
                size="sm"
                class="rounded-sm"
              />
            </div>

            <div
              v-if="(period.observations || []).length"
              class="text-sm text-muted"
            >
              {{ (period.observations || []).length }} observação(ões) de consulta
            </div>

            <div
              v-if="docsOf(period).length"
              class="flex flex-wrap gap-2"
            >
              <UButton
                v-for="doc in docsOf(period)"
                :key="doc.id"
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-download"
                :label="`${doc.kind || 'PDF'}${doc.byte_size ? ` · ${formatBytes(doc.byte_size)}` : ''}`"
                data-testid="dctfweb-download-evidence"
                @click="downloadDoc(doc)"
              />
            </div>
          </div>
        </div>
      </UCard>
    </template>
  </UModal>
</template>
