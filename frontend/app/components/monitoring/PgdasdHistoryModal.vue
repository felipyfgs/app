<script setup lang="ts">
import type {
  PgdasdArtifactDescriptor,
  PgdasdHistoryDeclaration,
  PgdasdHistoryPayload,
  PgdasdHistoryPeriod
} from '~/types/fiscal-modules'

const props = defineProps<{
  open: boolean
  clientId: number | null
  clientName?: string | null
  cnpjMasked?: string | null
  canCollectDocuments?: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { fetchHistory, collectDocuments, artifactDownloadUrl } = usePgdasdMonitoring()
const toast = useToast()

const loading = ref(false)
const collectingPeriod = ref<string | null>(null)
const error = ref<string | null>(null)
const history = ref<PgdasdHistoryPayload | PgdasdHistoryPeriod[] | null>(null)
let requestGeneration = 0

const payload = computed<PgdasdHistoryPayload>(() =>
  Array.isArray(history.value) ? { periods: history.value } : history.value || {}
)
const periods = computed(() =>
  [...pgdasdHistoryPeriods(history.value)].sort((a, b) =>
    String(b.period_key || '').localeCompare(String(a.period_key || ''))
  )
)
const stateMeta = computed(() => pgdasdDeclarationMeta(payload.value.declaration_state))

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

function latestDeclaration(period: PgdasdHistoryPeriod): PgdasdHistoryDeclaration | null {
  return [...(period.declarations || [])].sort((a, b) => {
    const byDate = String(b.transmitted_at || '').localeCompare(String(a.transmitted_at || ''))
    if (byDate !== 0) return byDate
    return String(b.declaration_number || b.number || '')
      .localeCompare(String(a.declaration_number || a.number || ''))
  })[0] || null
}

function artifacts(period: PgdasdHistoryPeriod): PgdasdArtifactDescriptor[] {
  const all = [
    ...(period.artifacts || period.documents || []),
    ...(period.declarations || []).flatMap(item => item.documents || []),
    ...(period.das || []).flatMap(item => item.documents || [])
  ]
  return [...new Map(all.map(item => [item.id, item])).values()]
}

function paymentLabel(value?: boolean | null): string {
  if (value === true) return 'Pagamento localizado até a consulta'
  if (value === false) return 'Pagamento não localizado até a consulta'
  return 'Sem observação de pagamento'
}

async function requestDocuments(period: PgdasdHistoryPeriod) {
  if (!props.canCollectDocuments || !props.clientId || !period.period_key) return
  const declaration = latestDeclaration(period)
  collectingPeriod.value = period.period_key
  try {
    await collectDocuments(props.clientId, {
      period_key: period.period_key,
      declaration_number: declaration?.declaration_number || declaration?.number || null
    })
    toast.add({
      title: 'Consulta de documentos enfileirada.',
      description: 'Esta foi uma ação explícita. O histórico será atualizado após o processamento.',
      color: 'success'
    })
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar os documentos.'),
      color: 'error'
    })
  } finally {
    collectingPeriod.value = null
  }
}
</script>

<template>
  <UModal
    :open="open"
    title="Histórico PGDAS-D"
    description="Dados armazenados localmente; abrir ou baixar um documento existente não consulta a SERPRO."
    scrollable
    :ui="{
      content: 'w-[calc(100vw-1rem)] sm:max-w-5xl',
      body: 'max-h-[72vh] overflow-y-auto'
    }"
    @update:open="emit('update:open', $event)"
  >
    <template #body>
      <div class="space-y-4" data-testid="pgdasd-history-modal">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate font-medium text-highlighted">
              {{ payload.client?.legal_name || clientName || `Cliente #${clientId || '—'}` }}
            </p>
            <p class="font-mono text-xs text-muted">
              {{ payload.client?.cnpj_masked || cnpjMasked || 'CNPJ não informado' }}
            </p>
          </div>
          <UBadge
            color="neutral"
            variant="outline"
            icon="i-lucide-database"
            label="Projeção local"
          />
        </div>

        <UAlert
          v-if="error"
          color="error"
          icon="i-lucide-circle-x"
          :title="error"
        >
          <template #actions>
            <UButton
              size="xs"
              color="neutral"
              variant="outline"
              label="Tentar novamente"
              @click="loadHistory"
            />
          </template>
        </UAlert>

        <div v-if="loading" class="space-y-3" aria-label="Carregando histórico local">
          <USkeleton class="h-16 w-full" />
          <USkeleton class="h-36 w-full" />
        </div>

        <template v-else-if="history">
          <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
            <UBadge
              :color="stateMeta.color"
              :icon="stateMeta.icon"
              :label="stateMeta.label"
              variant="subtle"
            />
            <span>PA esperado: {{ payload.expected_period_key || '—' }}</span>
            <span>Última consulta válida: {{ formatDateTime(payload.last_valid_query_at) }}</span>
          </div>

          <UAlert
            v-if="payload.declaration_state_reason"
            color="neutral"
            variant="subtle"
            :title="payload.declaration_state_reason"
          />

          <div v-if="periods.length" class="space-y-3">
            <UCard
              v-for="period in periods"
              :key="period.period_key || 'periodo-sem-chave'"
              :ui="{ body: 'space-y-4' }"
            >
              <template #header>
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="font-semibold text-highlighted">
                      Período {{ period.period_key || '—' }}
                    </p>
                    <p class="text-xs text-muted">
                      Última observação: {{ formatDateTime(period.last_valid_query_at || payload.last_valid_query_at) }}
                    </p>
                  </div>
                  <UButton
                    v-if="canCollectDocuments"
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-cloud-download"
                    label="Consultar documentos"
                    :loading="collectingPeriod === period.period_key"
                    :disabled="!period.period_key || collectingPeriod != null"
                    title="Ação explícita e faturável via SERPRO"
                    @click="requestDocuments(period)"
                  />
                </div>
              </template>

              <section>
                <h3 class="mb-2 text-sm font-medium">Declarações</h3>
                <div v-if="period.declarations?.length" class="grid gap-2 lg:grid-cols-2">
                  <div
                    v-for="declaration in period.declarations"
                    :key="declaration.id || declaration.declaration_number || declaration.number || 'declaracao'"
                    class="rounded-md border border-default p-3 text-sm"
                  >
                    <div class="font-medium text-highlighted">
                      {{ declaration.normalized_operation_type || declaration.operation_type || 'Declaração' }}
                    </div>
                    <div class="mt-1 space-y-1 text-xs text-muted">
                      <p>Número: {{ declaration.declaration_number || declaration.number || '—' }}</p>
                      <p>Transmitida: {{ formatDateTime(declaration.transmitted_at) }}</p>
                      <p>Malha: {{ declaration.malha == null ? 'Sem indicação' : String(declaration.malha) }}</p>
                    </div>
                  </div>
                </div>
                <p v-else class="text-sm text-muted">Nenhuma declaração observada.</p>
              </section>

              <section>
                <h3 class="mb-2 text-sm font-medium">DAS observados</h3>
                <div v-if="period.das?.length" class="grid gap-2 lg:grid-cols-2">
                  <div
                    v-for="das in period.das"
                    :key="das.id || das.das_number || 'das'"
                    class="rounded-md border border-default p-3 text-sm"
                  >
                    <div class="font-medium text-highlighted">
                      DAS {{ das.das_number || '—' }}
                    </div>
                    <div class="mt-1 space-y-1 text-xs text-muted">
                      <p>Emitido: {{ formatDateTime(das.issued_at) }}</p>
                      <p>{{ paymentLabel(das.payment_located) }}</p>
                    </div>
                  </div>
                </div>
                <p v-else class="text-sm text-muted">Nenhum DAS observado.</p>
              </section>

              <section class="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 class="text-sm font-medium">RBT12</h3>
                  <p class="text-xs text-muted">Valor extraído do extrato oficial, sem estimativa.</p>
                </div>
                <MonitoringPgdasdRbt12Value :rbt12="period.rbt12" />
              </section>

              <section>
                <h3 class="mb-2 text-sm font-medium">Documentos locais</h3>
                <ul v-if="artifacts(period).length" class="space-y-2">
                  <li
                    v-for="artifact in artifacts(period)"
                    :key="artifact.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-default p-2 text-sm"
                  >
                    <div class="min-w-0">
                      <p class="truncate font-medium text-highlighted">
                        {{ artifact.filename || artifact.kind || `Documento #${artifact.id}` }}
                      </p>
                      <p class="text-xs text-muted">
                        {{ artifact.kind || 'Documento' }}
                        <template v-if="artifact.byte_size != null"> · {{ formatBytes(artifact.byte_size) }}</template>
                        · {{ formatDateTime(artifact.observed_at) }}
                      </p>
                    </div>
                    <UButton
                      size="xs"
                      color="neutral"
                      variant="outline"
                      icon="i-lucide-download"
                      label="Baixar"
                      :to="artifactDownloadUrl(artifact.id)"
                      external
                      target="_blank"
                      rel="noopener noreferrer"
                    />
                  </li>
                </ul>
                <p v-else class="text-sm text-muted">
                  Nenhum documento armazenado. Nenhuma guia será emitida por este modal.
                </p>
              </section>
            </UCard>
          </div>

          <div v-else class="py-10 text-center">
            <UIcon name="i-lucide-file-clock" class="mx-auto mb-2 size-8 text-dimmed" />
            <p class="font-medium text-highlighted">Nenhum histórico local</p>
            <p class="text-sm text-muted">Os dados aparecerão após uma consulta produtiva válida.</p>
          </div>
        </template>
      </div>
    </template>
  </UModal>
</template>
