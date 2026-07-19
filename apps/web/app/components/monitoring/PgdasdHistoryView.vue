<script setup lang="ts">
import type {
  PgdasdArtifactDescriptor,
  PgdasdHistoryDas,
  PgdasdHistoryDeclaration,
  PgdasdHistoryPayload,
  PgdasdHistoryPeriod
} from '~/types/fiscal-modules'
import { usePgdasdMonitoring } from '~/composables/usePgdasdMonitoring'
import { apiErrorMessage } from '~/utils/api-error'
import { resolveApiUrl } from '~/utils/api-url'
import { formatDateTime } from '~/utils/format'
import {
  formatPgdasdPeriod,
  pgdasdDeclarationMeta,
  pgdasdHistoryPeriods
} from '~/utils/pgdasd'

const props = defineProps<{
  clientId: number
  canCollectDocuments?: boolean
}>()

const { fetchHistory, collectDocuments, artifactDownloadUrl } = usePgdasdMonitoring()
const toast = useToast()
const apiBase = useRuntimeConfig().public.apiBase as string

const loading = ref(false)
const collectingPeriod = ref<string | null>(null)
const error = ref<string | null>(null)
const history = ref<PgdasdHistoryPayload | PgdasdHistoryPeriod[] | null>(null)
const documentConfirmOpen = ref(false)
const pendingDocumentRequest = ref<{ periodKey: string, declarationNumber: string | null } | null>(null)
let requestGeneration = 0

const payload = computed<PgdasdHistoryPayload>(() =>
  Array.isArray(history.value) ? { periods: history.value } : history.value || {}
)
const periods = computed(() =>
  [...pgdasdHistoryPeriods(history.value)].sort((a, b) =>
    String(b.period_key || '').localeCompare(String(a.period_key || ''))
  )
)

interface PgdasdTableRow {
  key: string
  period: PgdasdHistoryPeriod
  declaration: PgdasdHistoryDeclaration | null
  das: PgdasdHistoryDas | null
  firstInPeriod: boolean
  periodRowSpan: number
}

const tableRows = computed<PgdasdTableRow[]>(() => periods.value.flatMap((period) => {
  const declarations = period.declarations || []
  const dasItems = period.das || []
  const rowSpan = Math.max(1, declarations.length + dasItems.length)
  const prefix = period.period_key || 'periodo-sem-chave'
  const rows: PgdasdTableRow[] = [
    ...declarations.map((declaration, index) => ({
      key: `${prefix}-declaracao-${declaration.id || declaration.declaration_number || declaration.number || index}`,
      period,
      declaration,
      das: null,
      firstInPeriod: index === 0,
      periodRowSpan: rowSpan
    })),
    ...dasItems.map((das, index) => ({
      key: `${prefix}-das-${das.id || das.das_number || index}`,
      period,
      declaration: null,
      das,
      firstInPeriod: declarations.length === 0 && index === 0,
      periodRowSpan: rowSpan
    }))
  ]

  return rows.length
    ? rows
    : [{
        key: `${prefix}-vazio`,
        period,
        declaration: null,
        das: null,
        firstInPeriod: true,
        periodRowSpan: 1
      }]
}))
const stateMeta = computed(() => pgdasdDeclarationMeta(payload.value.declaration_state))
const summaryStateMeta = computed(() =>
  loading.value
    ? { color: 'neutral' as const, icon: 'i-lucide-loader-circle', label: 'Carregando histórico' }
    : stateMeta.value
)

interface PgdasdMobileEntry {
  key: string
  kind: 'declaration' | 'das'
  label: string
  number: string | null
  when: string | null
  malha?: string | boolean | null
  paid?: boolean | null
}

function resolveOperationLabel(
  raw: string | null | undefined,
  fallback: 'declaration' | 'das' | 'empty'
): string {
  if (!raw) {
    if (fallback === 'declaration') return 'Declaração'
    if (fallback === 'das') return 'Geração de DAS'
    return 'Sem registros'
  }

  const normalized = raw.trim().toUpperCase().replaceAll('-', '_').replaceAll(' ', '_')
  const labels: Record<string, string> = {
    ORIGINAL: 'Original',
    RECTIFIER: 'Retificadora',
    RECTIFYING: 'Retificadora',
    RETIFICADORA: 'Retificadora',
    DAS_GENERATION: 'Geração de DAS',
    GENERATION_OF_DAS: 'Geração de DAS'
  }

  if (labels[normalized]) return labels[normalized]
  if (raw !== raw.toUpperCase()) return raw

  const readable = raw.replaceAll('_', ' ').toLocaleLowerCase('pt-BR')
  return readable.charAt(0).toLocaleUpperCase('pt-BR') + readable.slice(1)
}

function operationLabel(row: PgdasdTableRow): string {
  return resolveOperationLabel(
    row.declaration?.normalized_operation_type
    || row.declaration?.operation_type
    || row.das?.normalized_operation_type,
    row.declaration ? 'declaration' : row.das ? 'das' : 'empty'
  )
}

function periodKeyFallback(period: PgdasdHistoryPeriod): string {
  return period.period_key || `periodo-${period.declarations?.length || 0}-${period.das?.length || 0}`
}

function periodEntries(period: PgdasdHistoryPeriod): PgdasdMobileEntry[] {
  const prefix = period.period_key || 'periodo-sem-chave'
  const declarations = (period.declarations || []).map((declaration, index) => ({
    key: `${prefix}-declaracao-${declaration.id || declaration.declaration_number || declaration.number || index}`,
    kind: 'declaration' as const,
    label: resolveOperationLabel(
      declaration.normalized_operation_type || declaration.operation_type,
      'declaration'
    ),
    number: declaration.declaration_number || declaration.number || null,
    when: declaration.transmitted_at || null,
    malha: declaration.malha
  }))
  const dasItems = (period.das || []).map((das, index) => ({
    key: `${prefix}-das-${das.id || das.das_number || index}`,
    kind: 'das' as const,
    label: resolveOperationLabel(das.normalized_operation_type, 'das'),
    number: das.das_number || null,
    when: das.issued_at || null,
    paid: das.payment_located
  }))
  return [...declarations, ...dasItems]
}

type StatusColor = 'success' | 'warning' | 'neutral'

async function loadHistory() {
  const clientId = props.clientId
  if (!clientId) return
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
  () => props.clientId,
  (clientId) => {
    if (clientId) {
      void loadHistory()
      return
    }
    requestGeneration += 1
    history.value = null
    error.value = null
    loading.value = false
    documentConfirmOpen.value = false
    pendingDocumentRequest.value = null
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  requestGeneration += 1
})

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
    ...(period.artifacts || []),
    ...(period.documents || []),
    ...(period.declarations || []).flatMap(item => item.documents || []),
    ...(period.das || []).flatMap(item => item.documents || [])
  ]
  return [...new Map(all.map(item => [item.id, item])).values()]
}

function artifactDownloadPath(artifact: PgdasdArtifactDescriptor): string {
  const path = artifact.download_path?.trim()
  if (path) {
    return resolveApiUrl(path, apiBase)
  }
  return artifactDownloadUrl(artifact.id)
}

function documentLabel(kind?: string | null): string {
  return {
    DECLARACAO: 'Declaração',
    RECIBO: 'Recibo',
    NOTIFICACAO_MAED: 'MAED',
    DARF_MAED: 'DAS da MAED',
    EXTRATO: 'Extrato'
  }[String(kind || '').toUpperCase()] || 'Documento'
}

function paymentLabel(value?: boolean | null): string {
  if (value === true) return 'Pagamento localizado até a consulta'
  if (value === false) return 'Pagamento não localizado até a consulta'
  return 'Sem observação de pagamento'
}

function yesNoLabel(value?: string | boolean | null): string {
  if (value == null || value === '') return 'Não informado'
  if (value === true || ['SIM', 'TRUE', '1'].includes(String(value).toUpperCase())) return 'Sim'
  if (value === false || ['NAO', 'NÃO', 'FALSE', '0'].includes(String(value).toUpperCase())) return 'Não'
  return String(value)
}

function yesNoColor(value?: string | boolean | null): StatusColor {
  if (value == null || value === '') return 'neutral'
  const label = yesNoLabel(value)
  if (label === 'Sim') return 'warning'
  if (label === 'Não') return 'success'
  return 'neutral'
}

function paymentColor(value?: boolean | null): StatusColor {
  if (value == null) return 'neutral'
  return value ? 'success' : 'warning'
}

function openDocumentConfirmation(periodKey: string, declarationNumber: string | null = null) {
  if (!props.canCollectDocuments || !props.clientId || !periodKey) return
  pendingDocumentRequest.value = { periodKey, declarationNumber }
  documentConfirmOpen.value = true
}

function requestDocuments(period: PgdasdHistoryPeriod) {
  const declaration = latestDeclaration(period)
  openDocumentConfirmation(
    period.period_key || '',
    declaration?.declaration_number || declaration?.number || null
  )
}

const pendingOperationLabel = computed(() =>
  pendingDocumentRequest.value?.declarationNumber
    ? 'recibo da declaração observada'
    : 'última declaração e recibo do período'
)

const pendingPeriodLabel = computed(() =>
  formatPgdasdPeriod(pendingDocumentRequest.value?.periodKey)
)

const pendingClientLabel = computed(() =>
  payload.value.client?.legal_name?.trim()
  || `Cliente #${props.clientId || '—'}`
)

const documentsAcknowledged = ref(false)

watch(documentConfirmOpen, (open) => {
  if (!open) documentsAcknowledged.value = false
})

function closeDocumentConfirmation() {
  documentConfirmOpen.value = false
  documentsAcknowledged.value = false
}

async function confirmDocumentCollection() {
  const request = pendingDocumentRequest.value
  if (!props.canCollectDocuments || !props.clientId || !request) return
  collectingPeriod.value = request.periodKey
  try {
    await collectDocuments(props.clientId, {
      period_key: request.periodKey,
      declaration_number: request.declarationNumber,
      confirmed: true
    })
    toast.add({
      title: 'Consulta de documentos enfileirada.',
      description: 'Esta foi uma ação explícita. O histórico será atualizado após o processamento.',
      color: 'success'
    })
    documentConfirmOpen.value = false
    pendingDocumentRequest.value = null
    documentsAcknowledged.value = false
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
  <div class="space-y-4" data-testid="pgdasd-history-view">
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

    <UPageCard
      title="Histórico PGDAS-D"
      description="Declarações, DAS e documentos armazenados localmente."
      variant="subtle"
      data-testid="pgdasd-compact-summary-card"
    >
      <div class="mb-4 flex flex-col gap-2 text-xs text-muted sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4">
        <UBadge
          :color="summaryStateMeta.color"
          :icon="summaryStateMeta.icon"
          :label="summaryStateMeta.label"
          variant="subtle"
        />
        <span>
          PA esperado <strong class="font-medium text-highlighted">{{ formatPgdasdPeriod(payload.expected_period_key) }}</strong>
        </span>
        <span>
          Última consulta <strong class="font-medium text-highlighted">{{ formatDateTime(payload.last_valid_query_at) }}</strong>
        </span>
      </div>

      <div
        v-if="loading"
        class="space-y-3"
        aria-label="Carregando histórico local"
        aria-live="polite"
      >
        <USkeleton class="h-12 w-full" />
        <USkeleton class="h-56 w-full" />
      </div>

      <template v-else-if="history">
        <template v-if="periods.length">
          <!-- Mobile: um card por PA (sem scroll horizontal) -->
          <div
            class="space-y-3 md:hidden"
            data-testid="pgdasd-history-mobile"
            role="list"
            aria-label="Histórico PGDAS-D em cards"
          >
            <article
              v-for="period in periods"
              :key="period.period_key || periodKeyFallback(period)"
              class="rounded-lg border border-default bg-default p-3"
              role="listitem"
              :data-testid="`pgdasd-mobile-period-${period.period_key || 'sem-chave'}`"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-highlighted">
                    PA {{ formatPgdasdPeriod(period.period_key) }}
                  </p>
                  <p class="mt-0.5 text-xs text-muted">
                    {{ periodEntries(period).length || 0 }} registro(s)
                  </p>
                </div>
                <UButton
                  v-if="canCollectDocuments"
                  size="sm"
                  color="neutral"
                  variant="outline"
                  icon="i-lucide-cloud-download"
                  :aria-label="`Buscar documentos de ${formatPgdasdPeriod(period.period_key)}`"
                  :loading="collectingPeriod === period.period_key"
                  :disabled="!period.period_key || collectingPeriod != null"
                  @click="requestDocuments(period)"
                />
              </div>

              <ul
                v-if="periodEntries(period).length"
                class="mt-3 divide-y divide-default border-t border-default"
              >
                <li
                  v-for="entry in periodEntries(period)"
                  :key="entry.key"
                  class="space-y-2 py-3 first:pt-3"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-md bg-elevated px-2 py-1 text-xs font-medium text-highlighted">
                      {{ entry.label }}
                    </span>
                    <UBadge
                      v-if="entry.malha != null"
                      :color="yesNoColor(entry.malha)"
                      variant="subtle"
                      size="sm"
                      :label="`Malha: ${yesNoLabel(entry.malha)}`"
                    />
                    <UBadge
                      v-if="entry.paid != null"
                      :color="paymentColor(entry.paid)"
                      variant="subtle"
                      size="sm"
                      :label="`Pago: ${yesNoLabel(entry.paid)}`"
                    />
                  </div>
                  <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-xs">
                    <template v-if="entry.number">
                      <dt class="text-muted">
                        {{ entry.kind === 'das' ? 'Nº DAS' : 'Nº declaração' }}
                      </dt>
                      <dd class="min-w-0 break-all font-mono tabular-nums text-highlighted">
                        {{ entry.number }}
                      </dd>
                    </template>
                    <template v-if="entry.when">
                      <dt class="text-muted">
                        {{ entry.kind === 'das' ? 'Emissão' : 'Transmissão' }}
                      </dt>
                      <dd class="text-highlighted">
                        {{ formatDateTime(entry.when) }}
                      </dd>
                    </template>
                  </dl>
                </li>
              </ul>
              <p
                v-else
                class="mt-3 border-t border-default pt-3 text-xs text-muted"
              >
                Sem registros neste PA.
              </p>

              <div
                v-if="artifacts(period).length"
                class="mt-3 flex flex-wrap gap-2 border-t border-default pt-3"
              >
                <UButton
                  v-for="artifact in artifacts(period)"
                  :key="artifact.id"
                  size="xs"
                  color="neutral"
                  variant="soft"
                  icon="i-lucide-download"
                  :label="documentLabel(artifact.kind)"
                  :aria-label="`Baixar ${documentLabel(artifact.kind)}`"
                  :to="artifactDownloadPath(artifact)"
                  external
                  target="_blank"
                  rel="noopener noreferrer"
                />
              </div>
            </article>
          </div>

          <!-- Desktop: tabela densa -->
          <div
            class="hidden overflow-x-auto rounded-lg border border-default focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary md:block"
            data-testid="pgdasd-history-table"
            role="region"
            aria-label="Tabela do histórico PGDAS-D"
            tabindex="0"
          >
            <table class="w-full min-w-[1080px] border-separate border-spacing-0 text-left text-sm">
              <thead class="bg-elevated/60 text-xs text-muted">
                <tr>
                  <th class="sticky left-0 z-20 border-b border-default bg-elevated px-3 py-2.5 font-medium">
                    PA
                  </th>
                  <th class="w-40 min-w-40 border-b border-default px-3 py-2.5 font-medium">
                    Operação
                  </th>
                  <th class="border-b border-default px-3 py-2.5 font-medium">
                    Nº declaração
                  </th>
                  <th class="border-b border-default px-3 py-2.5 font-medium">
                    Transmissão
                  </th>
                  <th class="border-b border-default px-3 py-2.5 text-center font-medium">
                    Malha
                  </th>
                  <th class="border-b border-default px-3 py-2.5 font-medium">
                    Nº DAS
                  </th>
                  <th class="border-b border-default px-3 py-2.5 font-medium">
                    Emissão
                  </th>
                  <th class="border-b border-default px-3 py-2.5 text-center font-medium">
                    Pago
                  </th>
                  <th class="border-b border-default px-3 py-2.5 font-medium">
                    Documentos
                  </th>
                  <th class="border-b border-default px-3 py-2.5 text-center font-medium">
                    Ação
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in tableRows"
                  :key="row.key"
                  class="group"
                >
                  <td
                    v-if="row.firstInPeriod"
                    :rowspan="row.periodRowSpan"
                    class="sticky left-0 z-10 w-28 border-b border-default bg-default px-3 py-3 align-top font-semibold text-highlighted group-hover:bg-elevated/40"
                  >
                    PA {{ formatPgdasdPeriod(row.period.period_key) }}
                  </td>
                  <td class="w-40 min-w-40 border-b border-default px-3 py-3 group-hover:bg-elevated/40">
                    <span class="inline-flex max-w-40 whitespace-normal break-words rounded-md bg-elevated px-2 py-1 text-xs font-medium leading-4 text-highlighted">
                      {{ operationLabel(row) }}
                    </span>
                  </td>
                  <td class="border-b border-default px-3 py-3 font-mono text-xs tabular-nums group-hover:bg-elevated/40">
                    {{ row.declaration?.declaration_number || row.declaration?.number || '—' }}
                  </td>
                  <td class="whitespace-nowrap border-b border-default px-3 py-3 text-xs group-hover:bg-elevated/40">
                    {{ row.declaration ? formatDateTime(row.declaration.transmitted_at) : '—' }}
                  </td>
                  <td class="border-b border-default px-3 py-3 text-center group-hover:bg-elevated/40">
                    <UBadge
                      v-if="row.declaration"
                      :color="yesNoColor(row.declaration.malha)"
                      variant="subtle"
                      :label="yesNoLabel(row.declaration.malha)"
                    />
                    <span v-else>—</span>
                  </td>
                  <td class="border-b border-default px-3 py-3 font-mono text-xs tabular-nums group-hover:bg-elevated/40">
                    {{ row.das?.das_number || '—' }}
                  </td>
                  <td class="whitespace-nowrap border-b border-default px-3 py-3 text-xs group-hover:bg-elevated/40">
                    {{ row.das ? formatDateTime(row.das.issued_at) : '—' }}
                  </td>
                  <td class="border-b border-default px-3 py-3 text-center group-hover:bg-elevated/40">
                    <UTooltip v-if="row.das" :text="paymentLabel(row.das.payment_located)">
                      <UBadge
                        :color="paymentColor(row.das.payment_located)"
                        variant="subtle"
                        :label="yesNoLabel(row.das.payment_located)"
                      />
                    </UTooltip>
                    <span v-else>—</span>
                  </td>
                  <td
                    v-if="row.firstInPeriod"
                    :rowspan="row.periodRowSpan"
                    class="border-b border-default px-3 py-3 align-top group-hover:bg-elevated/40"
                  >
                    <div v-if="artifacts(row.period).length" class="flex max-w-44 flex-wrap gap-1">
                      <UTooltip
                        v-for="artifact in artifacts(row.period)"
                        :key="artifact.id"
                        :text="`Baixar ${documentLabel(artifact.kind).toLowerCase()}`"
                      >
                        <UButton
                          size="xs"
                          color="neutral"
                          variant="soft"
                          icon="i-lucide-download"
                          :aria-label="`Baixar ${documentLabel(artifact.kind)}`"
                          :to="artifactDownloadPath(artifact)"
                          external
                          target="_blank"
                          rel="noopener noreferrer"
                        />
                      </UTooltip>
                    </div>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td
                    v-if="row.firstInPeriod"
                    :rowspan="row.periodRowSpan"
                    class="border-b border-default px-3 py-3 text-center align-top group-hover:bg-elevated/40"
                  >
                    <UTooltip v-if="canCollectDocuments" text="Buscar documentos via SERPRO (ação potencialmente faturável)">
                      <UButton
                        size="xs"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-cloud-download"
                        :aria-label="`Buscar documentos de ${formatPgdasdPeriod(row.period.period_key)}`"
                        :loading="collectingPeriod === row.period.period_key"
                        :disabled="!row.period.period_key || collectingPeriod != null"
                        @click="requestDocuments(row.period)"
                      />
                    </UTooltip>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <div v-else class="rounded-lg border border-dashed border-default px-4 py-12 text-center">
          <div class="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-elevated text-dimmed">
            <UIcon name="i-lucide-file-clock" class="size-5" />
          </div>
          <p class="font-medium text-highlighted">
            Nenhum histórico local
          </p>
          <p class="mx-auto mt-1 max-w-md text-sm text-muted">
            Os dados aparecerão depois que uma consulta válida for concluída.
          </p>
          <UButton
            v-if="canCollectDocuments && payload.expected_period_key"
            class="mt-4 w-full sm:w-auto"
            color="primary"
            icon="i-lucide-cloud-download"
            :loading="collectingPeriod === payload.expected_period_key"
            label="Buscar declaração e recibo"
            @click="openDocumentConfirmation(payload.expected_period_key)"
          />
        </div>
      </template>
    </UPageCard>
  </div>

  <ShellScrollableModal
    v-model:open="documentConfirmOpen"
    title="Buscar declaração e recibo"
    :description="`${pendingClientLabel} · PA ${pendingPeriodLabel}`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-xl"
    :dismissible="collectingPeriod == null"
    :show-default-footer="false"
    @cancel="closeDocumentConfirmation"
  >
    <template #body>
      <div class="space-y-5">
        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-primary">
          <UIcon name="i-lucide-file-search" class="size-4 shrink-0" />
          PGDAS-D
        </div>

        <UAlert
          color="warning"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          title="Consulta manual e potencialmente faturável"
        />

        <div class="rounded-lg border border-default bg-elevated/30 p-3">
          <p class="text-xs font-medium uppercase tracking-wide text-muted">
            Solicitação
          </p>
          <p class="mt-1 text-sm font-medium text-highlighted">
            Buscar {{ pendingOperationLabel }}
          </p>
          <p class="mt-1 text-xs text-muted">
            Os PDFs encontrados ficam protegidos no cofre e aparecem neste histórico quando o processamento terminar.
          </p>
        </div>

        <UCheckbox
          v-model="documentsAcknowledged"
          label="Entendo que esta ação solicita uma consulta para este cliente e período."
          :disabled="collectingPeriod != null"
        />
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Cancelar"
        submit-label="Solicitar documentos"
        submit-icon="i-lucide-check"
        :loading="collectingPeriod != null"
        :disabled="!documentsAcknowledged"
        :cancel-disabled="collectingPeriod != null"
        @cancel="closeDocumentConfirmation"
        @submit="confirmDocumentCollection"
      />
    </template>
  </ShellScrollableModal>
</template>
