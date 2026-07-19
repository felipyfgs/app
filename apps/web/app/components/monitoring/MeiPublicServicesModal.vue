<script setup lang="ts">
import type { FiscalMutationPreflight } from '~/types/api'
import type {
  DasnHistoryPayload,
  MeiAutomationAttempt,
  MeiCoverage
} from '~/types/mei-public-services'

const props = defineProps<{
  open: boolean
  clientId: number | null
  clientName?: string | null
  cnpjMasked?: string | null
  canGenerateDas: boolean
  canConsultDasn: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const activeService = ref<'das' | 'dasn'>('das')
const serviceTabs = [
  { label: 'Emitir DAS', value: 'das', icon: 'i-lucide-barcode' },
  { label: 'DASN-SIMEI', value: 'dasn', icon: 'i-lucide-files' }
]

const competenceDraft = ref('')
const competencies = ref<string[]>([])
const outputFormat = ref<'PDF' | 'BARCODE'>('PDF')
const outputTabs = [
  { label: 'PDF', value: 'PDF', icon: 'i-lucide-file-text' },
  { label: 'Código de barras', value: 'BARCODE', icon: 'i-lucide-barcode' }
]
const dasIdempotencyKey = ref('')
const dasPreflight = ref<FiscalMutationPreflight | null>(null)
const dasAttempt = ref<MeiAutomationAttempt | null>(null)
const dasError = ref<string | null>(null)
const dasLoading = ref(false)
const totpCode = ref('')
const confirmationPhrase = ref('')
const consequenceConfirmed = ref(false)
let dasPollTimer: ReturnType<typeof setTimeout> | null = null

const dasnYear = ref(new Date().getFullYear())
const includeFullReceipt = ref(false)
const dasnHistory = ref<DasnHistoryPayload | null>(null)
const dasnError = ref<string | null>(null)
const dasnLoading = ref(false)
const dasnPolling = ref(false)
const dasnBaselineAttemptId = ref<number | null>(null)
let dasnPollCount = 0
let dasnPollTimer: ReturnType<typeof setTimeout> | null = null

const requiredPhrase = computed(() => String(
  dasPreflight.value?.confirmation_phrase || 'CONFIRMAR EMISSAO DAS'
))
const dasEligible = computed(() => dasPreflight.value?.eligible === true)
const dasStatusMeta = computed(() => dasAttempt.value
  ? meiAttemptStatusMeta(dasAttempt.value.status)
  : null)
const dasProviderMeta = computed(() => dasAttempt.value
  ? meiProviderMeta(dasAttempt.value.provider, null)
  : null)
const dasnAttempt = computed(() => dasnHistory.value?.attempt || null)
const dasnStatusMeta = computed(() => dasnAttempt.value
  ? meiAttemptStatusMeta(dasnAttempt.value.status)
  : null)
const dasnProviderMeta = computed(() => dasnAttempt.value
  ? meiProviderMeta(dasnAttempt.value.provider, null)
  : null)

function randomIdempotencyKey(): string {
  const suffix = typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`
  return `mei-das-${suffix}`
}

function clearTimers() {
  if (dasPollTimer) clearTimeout(dasPollTimer)
  if (dasnPollTimer) clearTimeout(dasnPollTimer)
  dasPollTimer = null
  dasnPollTimer = null
}

function resetSensitiveState() {
  clearTimers()
  activeService.value = props.canGenerateDas ? 'das' : 'dasn'
  competenceDraft.value = ''
  competencies.value = []
  outputFormat.value = 'PDF'
  dasIdempotencyKey.value = randomIdempotencyKey()
  dasPreflight.value = null
  dasAttempt.value = null
  dasError.value = null
  dasLoading.value = false
  totpCode.value = ''
  confirmationPhrase.value = ''
  consequenceConfirmed.value = false
  dasnYear.value = new Date().getFullYear()
  includeFullReceipt.value = false
  dasnHistory.value = null
  dasnError.value = null
  dasnLoading.value = false
  dasnPolling.value = false
  dasnBaselineAttemptId.value = null
  dasnPollCount = 0
}

function invalidateDasPreflight() {
  dasPreflight.value = null
  dasAttempt.value = null
  dasError.value = null
  totpCode.value = ''
  confirmationPhrase.value = ''
  consequenceConfirmed.value = false
  dasIdempotencyKey.value = randomIdempotencyKey()
}

function addCompetence() {
  const value = competenceDraft.value.trim()
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(value)) {
    toast.add({ title: 'Informe uma competência válida.', color: 'warning' })
    return
  }
  if (competencies.value.includes(value)) {
    competenceDraft.value = ''
    return
  }
  if (competencies.value.length >= 12) {
    toast.add({ title: 'Selecione no máximo 12 competências.', color: 'warning' })
    return
  }
  competencies.value = [...competencies.value, value].sort()
  competenceDraft.value = ''
  invalidateDasPreflight()
}

function removeCompetence(value: string) {
  competencies.value = competencies.value.filter(item => item !== value)
  invalidateDasPreflight()
}

watch(outputFormat, () => invalidateDasPreflight())

async function runDasPreflight() {
  if (!props.clientId || !competencies.value.length || dasLoading.value) return
  dasLoading.value = true
  dasError.value = null
  try {
    const response = await api.fiscal.meiPublicServices.preflightDas({
      client_id: props.clientId,
      competencies: competencies.value,
      output_format: outputFormat.value
    }, dasIdempotencyKey.value)
    dasPreflight.value = response.data
  } catch (caught) {
    dasPreflight.value = (caught as { data?: { data?: FiscalMutationPreflight } })?.data?.data || null
    dasError.value = apiErrorMessage(caught, 'Não foi possível concluir o preflight da emissão.')
  } finally {
    dasLoading.value = false
  }
}

function scheduleDasPoll() {
  if (!props.open || !dasAttempt.value || !shouldPollMeiAttempt(dasAttempt.value)) return
  dasPollTimer = setTimeout(() => void pollDasAttempt(), 2500)
}

async function pollDasAttempt() {
  const attemptId = dasAttempt.value?.id
  if (!attemptId || !props.open) return
  try {
    const response = await api.fiscal.meiPublicServices.attempt(attemptId)
    dasAttempt.value = response.data
    if (response.data.status === 'SUCCEEDED') {
      toast.add({ title: 'DAS disponível para download.', color: 'success' })
    }
  } catch (caught) {
    dasError.value = apiErrorMessage(caught, 'Não foi possível atualizar o estado da emissão.')
    return
  }
  scheduleDasPoll()
}

async function generateDas() {
  if (!props.clientId || !dasPreflight.value || dasLoading.value) return
  if (!consequenceConfirmed.value) {
    toast.add({ title: 'Confirme a consequência fiscal da operação.', color: 'warning' })
    return
  }
  if (confirmationPhrase.value.trim() !== requiredPhrase.value) {
    toast.add({ title: 'Frase de confirmação incorreta.', color: 'warning' })
    return
  }
  if (dasPreflight.value.requires_totp !== false && totpCode.value.trim().length < 6) {
    toast.add({ title: 'Informe o código TOTP recente.', color: 'warning' })
    return
  }

  dasLoading.value = true
  dasError.value = null
  try {
    if (dasPreflight.value.requires_totp !== false) {
      await api.fiscal.mutations.confirmTotp(totpCode.value.trim())
    }
    const response = await api.fiscal.meiPublicServices.generateDas({
      client_id: props.clientId,
      competencies: competencies.value,
      output_format: outputFormat.value,
      preflight_token: String(dasPreflight.value.preflight_token || ''),
      confirmation_phrase: confirmationPhrase.value.trim(),
      confirmed: true
    }, dasIdempotencyKey.value)
    dasAttempt.value = response.data.attempt
    toast.add({
      title: dasAttempt.value ? 'Emissão de DAS enfileirada.' : 'Emissão de DAS registrada.',
      color: 'success'
    })
    scheduleDasPoll()
  } catch (caught) {
    dasError.value = apiErrorMessage(caught, 'Não foi possível emitir o DAS.')
  } finally {
    dasLoading.value = false
  }
}

async function loadDasnHistory(silent = false) {
  if (!props.clientId || (dasnLoading.value && !silent)) return
  if (!silent) dasnLoading.value = true
  dasnError.value = null
  try {
    const response = await api.fiscal.meiPublicServices.dasn.history(
      props.clientId,
      dasnYear.value
    )
    dasnHistory.value = response.data
  } catch (caught) {
    dasnError.value = apiErrorMessage(caught, 'Não foi possível carregar o histórico DASN-SIMEI.')
  } finally {
    if (!silent) dasnLoading.value = false
  }
}

function scheduleDasnPoll() {
  if (!props.open || !dasnPolling.value) return
  if (dasnPollCount >= 48) {
    dasnPolling.value = false
    dasnError.value = 'A consulta continua em segundo plano. Atualize o histórico em alguns instantes.'
    return
  }
  dasnPollCount += 1
  dasnPollTimer = setTimeout(() => void pollDasnHistory(), 2500)
}

async function pollDasnHistory() {
  await loadDasnHistory(true)
  const current = dasnHistory.value?.attempt || null
  const isNewAttempt = current && current.id !== dasnBaselineAttemptId.value
  if (isNewAttempt && !shouldPollMeiAttempt(current)) {
    dasnPolling.value = false
    return
  }
  scheduleDasnPoll()
}

async function consultDasn() {
  if (!props.clientId || dasnLoading.value || dasnPolling.value) return
  dasnLoading.value = true
  dasnError.value = null
  dasnBaselineAttemptId.value = dasnHistory.value?.attempt?.id || null
  try {
    await api.fiscal.meiPublicServices.dasn.consult({
      client_ids: [props.clientId],
      calendar_year: dasnYear.value,
      include_full_receipt: includeFullReceipt.value,
      confirmed: true
    })
    dasnPolling.value = true
    dasnPollCount = 0
    toast.add({ title: 'Consulta DASN-SIMEI enfileirada.', color: 'success' })
    scheduleDasnPoll()
  } catch (caught) {
    dasnError.value = apiErrorMessage(caught, 'Não foi possível solicitar a consulta DASN-SIMEI.')
  } finally {
    dasnLoading.value = false
  }
}

function coverageMeta(coverage: MeiCoverage | string | null | undefined) {
  return meiCoverageMeta(coverage)
}

watch(
  () => [props.open, props.clientId, sessionEpoch.value] as const,
  ([open, clientId], previous) => {
    const clientChanged = previous && clientId !== previous[1]
    if (!open || clientChanged) resetSensitiveState()
    if (open && clientId && activeService.value === 'dasn') void loadDasnHistory()
  },
  { immediate: true }
)

watch(activeService, (service) => {
  if (props.open && service === 'dasn' && props.clientId) void loadDasnHistory()
})

watch(dasnYear, () => {
  if (props.open && activeService.value === 'dasn') void loadDasnHistory()
})

onBeforeUnmount(clearTimers)
</script>

<template>
  <ShellScrollableModal
    :open="open"
    title="Serviços públicos MEI"
    :description="`${clientName || `Cliente #${clientId || '—'}`} · CNPJ ${cnpjMasked || '—'}`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-4xl"
    test-id="mei-public-services-modal"
    :show-default-footer="false"
    @update:open="emit('update:open', $event)"
    @cancel="emit('update:open', false)"
  >
    <template #body>
      <div class="min-w-0 space-y-4">
        <ShellScrollableTabs
          v-model="activeService"
          :items="serviceTabs"
          size="sm"
          variant="pill"
          aria-label="Selecionar serviço público MEI"
          test-id="mei-public-services-tabs"
        />

        <section v-if="activeService === 'das'" class="space-y-4" data-testid="mei-das-service">
          <UAlert
            v-if="!canGenerateDas"
            color="warning"
            icon="i-lucide-shield-off"
            title="Seu perfil não pode emitir DAS"
          />

          <template v-else>
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(220px,0.65fr)]">
              <div class="space-y-3">
                <UFormField label="Competência" name="competence">
                  <div class="flex min-w-0 gap-2">
                    <UInput
                      v-model="competenceDraft"
                      type="month"
                      class="min-w-0 flex-1"
                      :disabled="dasLoading || !!dasAttempt"
                      @keydown.enter.prevent="addCompetence"
                    />
                    <UTooltip text="Adicionar competência">
                      <UButton
                        icon="i-lucide-plus"
                        color="neutral"
                        variant="outline"
                        aria-label="Adicionar competência"
                        :disabled="dasLoading || !!dasAttempt"
                        @click="addCompetence"
                      />
                    </UTooltip>
                  </div>
                </UFormField>

                <div v-if="competencies.length" class="flex flex-wrap gap-2">
                  <UBadge
                    v-for="competence in competencies"
                    :key="competence"
                    color="neutral"
                    variant="subtle"
                    class="gap-1"
                  >
                    {{ competence }}
                    <UButton
                      icon="i-lucide-x"
                      size="xs"
                      color="neutral"
                      variant="link"
                      class="p-0"
                      :aria-label="`Remover ${competence}`"
                      :disabled="dasLoading || !!dasAttempt"
                      @click="removeCompetence(competence)"
                    />
                  </UBadge>
                </div>
              </div>

              <UFormField label="Formato" name="output-format">
                <ShellScrollableTabs
                  v-model="outputFormat"
                  :items="outputTabs"
                  size="sm"
                  variant="pill"
                  aria-label="Selecionar formato do DAS"
                />
              </UFormField>
            </div>

            <UAlert
              v-if="dasError"
              color="error"
              icon="i-lucide-circle-x"
              :title="dasError"
            />

            <div
              v-if="dasAttempt"
              class="space-y-3"
              role="status"
              aria-live="polite"
            >
              <div class="flex flex-wrap items-center gap-2">
                <UBadge
                  v-if="dasStatusMeta"
                  :color="dasStatusMeta.color"
                  :icon="dasStatusMeta.icon"
                  :label="dasStatusMeta.label"
                  variant="subtle"
                />
                <UBadge
                  v-if="dasProviderMeta"
                  :color="dasProviderMeta.color"
                  :icon="dasProviderMeta.icon"
                  :label="dasProviderMeta.label"
                  variant="outline"
                />
                <UBadge
                  v-if="dasAttempt.fallback_reason"
                  color="warning"
                  icon="i-lucide-route"
                  label="Contingência"
                  variant="outline"
                />
                <span class="text-xs text-muted">
                  Atualizado {{ formatDateTime(dasAttempt.last_synced_at || dasAttempt.created_at) }}
                </span>
              </div>

              <UProgress
                v-if="shouldPollMeiAttempt(dasAttempt)"
                animation="carousel"
                aria-label="Emissão de DAS em processamento"
              />
              <UAlert
                v-else-if="dasAttempt.status === 'WAITING_USER_ACTION'"
                color="warning"
                icon="i-lucide-user-round-check"
                title="A emissão aguarda ação humana"
              />
              <UAlert
                v-else-if="dasAttempt.status === 'UNCERTAIN'"
                color="warning"
                icon="i-lucide-circle-help"
                title="Resultado incerto; a emissão não será reenviada automaticamente"
              />

              <div v-if="dasAttempt.artifacts.length" class="flex flex-wrap gap-2">
                <UButton
                  v-for="artifact in dasAttempt.artifacts"
                  :key="artifact.id"
                  icon="i-lucide-download"
                  color="neutral"
                  variant="outline"
                  :label="artifact.name || 'Baixar DAS'"
                  :to="api.fiscal.meiPublicServices.artifactDownloadUrl(dasAttempt.id, artifact.id)"
                  external
                  target="_blank"
                  rel="noopener noreferrer"
                />
              </div>
            </div>

            <template v-else-if="dasPreflight">
              <dl class="grid gap-2 border-y border-default py-3 text-sm">
                <div class="flex justify-between gap-4">
                  <dt class="text-muted">
                    Efeito
                  </dt>
                  <dd class="max-w-[70%] text-right font-medium">
                    {{ dasPreflight.effect_summary || 'Gerar documento DAS' }}
                  </dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-muted">
                    Custo estimado
                  </dt>
                  <dd class="text-right">
                    {{ dasPreflight.estimated_cost_micros === 0 ? 'Sem consumo SERPRO no portal' : 'Conforme provider selecionado' }}
                  </dd>
                </div>
              </dl>

              <UAlert
                v-if="!dasEligible"
                color="warning"
                icon="i-lucide-shield-off"
                :title="dasPreflight.denial_message || 'Emissão não elegível'"
              />

              <div v-else class="grid gap-4 sm:grid-cols-2">
                <UFormField
                  v-if="dasPreflight.requires_totp !== false"
                  label="Código TOTP"
                  name="totp"
                  required
                >
                  <UInput
                    v-model="totpCode"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="16"
                    placeholder="000000"
                    class="w-full"
                  />
                </UFormField>
                <UFormField :label="`Digite: ${requiredPhrase}`" name="confirmation" required>
                  <UInput v-model="confirmationPhrase" :placeholder="requiredPhrase" class="w-full" />
                </UFormField>
                <UCheckbox
                  v-model="consequenceConfirmed"
                  label="Li e confirmo a geração fiscal destas competências."
                  class="sm:col-span-2"
                />
              </div>
            </template>

            <div class="flex justify-end gap-2">
              <UButton
                v-if="!dasPreflight && !dasAttempt"
                icon="i-lucide-shield-check"
                color="primary"
                label="Validar emissão"
                :disabled="!competencies.length"
                :loading="dasLoading"
                @click="runDasPreflight"
              />
              <UButton
                v-else-if="dasEligible && !dasAttempt"
                icon="i-lucide-file-output"
                color="error"
                label="Emitir DAS"
                :loading="dasLoading"
                @click="generateDas"
              />
            </div>
          </template>
        </section>

        <section v-else class="space-y-4" data-testid="mei-dasn-service">
          <UAlert
            v-if="!canConsultDasn"
            color="warning"
            icon="i-lucide-shield-off"
            title="Seu perfil não pode consultar DASN-SIMEI"
          />

          <template v-else>
            <div class="flex flex-wrap items-end gap-3">
              <UFormField label="Ano-calendário" name="dasn-year">
                <UInputNumber
                  v-model="dasnYear"
                  :min="2009"
                  :max="2100"
                  :format-options="{ useGrouping: false }"
                  class="w-36"
                />
              </UFormField>
              <UCheckbox
                v-model="includeFullReceipt"
                label="Buscar recibo integral quando disponível"
                class="min-h-8 pb-1"
              />
              <div class="ml-auto flex gap-2">
                <UTooltip text="Atualizar histórico">
                  <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    aria-label="Atualizar histórico DASN-SIMEI"
                    :loading="dasnLoading"
                    @click="loadDasnHistory()"
                  />
                </UTooltip>
                <UButton
                  icon="i-lucide-search"
                  color="primary"
                  label="Consultar"
                  :loading="dasnLoading || dasnPolling"
                  @click="consultDasn"
                />
              </div>
            </div>

            <UAlert
              v-if="dasnError"
              color="error"
              icon="i-lucide-circle-x"
              :title="dasnError"
            />

            <div
              v-if="dasnAttempt"
              class="flex flex-wrap items-center gap-2"
              role="status"
              aria-live="polite"
            >
              <UBadge
                v-if="dasnStatusMeta"
                :color="dasnStatusMeta.color"
                :icon="dasnStatusMeta.icon"
                :label="dasnStatusMeta.label"
                variant="subtle"
              />
              <UBadge
                v-if="dasnProviderMeta"
                :color="dasnProviderMeta.color"
                :icon="dasnProviderMeta.icon"
                :label="dasnProviderMeta.label"
                variant="outline"
              />
              <UBadge
                v-if="dasnAttempt.fallback_reason"
                color="warning"
                icon="i-lucide-route"
                label="Contingência"
                variant="outline"
              />
              <UBadge
                :color="coverageMeta(dasnHistory?.coverage).color"
                :icon="coverageMeta(dasnHistory?.coverage).icon"
                :label="coverageMeta(dasnHistory?.coverage).label"
                variant="outline"
              />
            </div>

            <UProgress
              v-if="dasnPolling || (dasnAttempt && shouldPollMeiAttempt(dasnAttempt))"
              animation="carousel"
              aria-label="Consulta DASN-SIMEI em processamento"
            />
            <UAlert
              v-else-if="dasnAttempt?.status === 'WAITING_USER_ACTION'"
              color="warning"
              icon="i-lucide-user-round-check"
              title="A consulta aguarda ação humana"
            />

            <ShellLoadingModalBody v-if="dasnLoading && !dasnHistory" :rows="2" />

            <div v-else-if="dasnHistory?.declarations.length" class="overflow-x-auto">
              <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="text-xs text-muted">
                  <tr>
                    <th class="pb-2 pr-3 font-medium">
                      Ano
                    </th>
                    <th class="pb-2 pr-3 font-medium">
                      Situação
                    </th>
                    <th class="pb-2 pr-3 font-medium">
                      Transmissão
                    </th>
                    <th class="pb-2 pr-3 font-medium">
                      Cobertura
                    </th>
                    <th class="pb-2 text-right font-medium">
                      Artefato
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="declaration in dasnHistory.declarations"
                    :key="`${declaration.calendar_year}-${declaration.transmitted_at || declaration.status}`"
                    class="border-t border-default"
                  >
                    <td class="py-3 pr-3 tabular-nums">
                      {{ declaration.calendar_year }}
                    </td>
                    <td class="py-3 pr-3 font-medium">
                      {{ declaration.status }}
                    </td>
                    <td class="py-3 pr-3">
                      {{ formatDateTime(declaration.transmitted_at) }}
                    </td>
                    <td class="py-3 pr-3">
                      <UBadge
                        :color="coverageMeta(declaration.coverage).color"
                        :label="coverageMeta(declaration.coverage).label"
                        variant="subtle"
                      />
                    </td>
                    <td class="py-3 text-right">
                      <UButton
                        v-if="hasIntegralDasnReceipt(declaration.coverage, declaration.receipt_available) && declaration.artifact && declaration.artifact_attempt_id"
                        icon="i-lucide-download"
                        color="neutral"
                        variant="outline"
                        size="xs"
                        label="Recibo"
                        :to="api.fiscal.meiPublicServices.artifactDownloadUrl(declaration.artifact_attempt_id, declaration.artifact.id)"
                        external
                        target="_blank"
                        rel="noopener noreferrer"
                      />
                      <span v-else class="text-xs text-muted">Resumo público</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else-if="!dasnLoading" class="border-y border-dashed border-default py-8 text-center">
              <UIcon name="i-lucide-files" class="mx-auto mb-2 size-8 text-dimmed" />
              <p class="font-medium text-highlighted">
                Nenhuma declaração armazenada para {{ dasnYear }}
              </p>
            </div>
          </template>
        </section>
      </div>
    </template>
    <template #footer>
      <ShellModalFooter
        cancel-label="Fechar"
        :show-submit="false"
        @cancel="emit('update:open', false)"
      />
    </template>
  </ShellScrollableModal>
</template>
