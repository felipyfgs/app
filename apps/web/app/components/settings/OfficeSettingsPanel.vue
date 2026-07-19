<script setup lang="ts">
/**
 * Configuração do escritório.
 * Seções: perfil · consentimento · A1 canônico · agendas.
 * Sem campos técnicos SERPRO (autor, Termo, tokens ou ambiente).
 * UAlert apenas para erro real; estados normais usam badge/empty state.
 */
import type { AccordionItem, StepperItem } from '@nuxt/ui'
import type {
  OfficeCanonicalCredential,
  OfficeInstitutionalProfile,
  OfficeMonitorSchedulePolicy,
  OfficeOnboardingActionable,
  OfficeTechnicalConsent
} from '~/types/api'
import {
  actionableOfficeError,
  emptyOnboarding,
  OFFICE_ONBOARDING_STAGES,
  onboardingIsInProgress,
  onboardingStageIndex,
  onboardingStatusColor,
  onboardingStatusLabel
} from '~/utils/office-settings'

const api = useApi()
const toast = useToast()
const { sessionEpoch, canManageCredentials } = useDashboard()

const loading = ref(true)
const saving = ref(false)
const savingScheduleKey = ref<string | null>(null)
const loadError = ref<string | null>(null)
const apiUnavailable = ref(false)

const profile = ref<OfficeInstitutionalProfile | null>(null)
const consent = ref<OfficeTechnicalConsent | null>(null)
const credential = ref<OfficeCanonicalCredential | null>(null)
const policies = ref<OfficeMonitorSchedulePolicy[]>([])
const onboarding = ref<OfficeOnboardingActionable | null>(null)

const readonly = computed(() => !canManageCredentials.value)
const onboardingStagePosition = computed(() => onboardingStageIndex(onboarding.value?.stage))

const onboardingStepperItems = computed((): StepperItem[] =>
  OFFICE_ONBOARDING_STAGES.map((stage, index) => ({
    title: stage.label,
    icon: index < onboardingStagePosition.value
      ? 'i-lucide-circle-check'
      : index === onboardingStagePosition.value
        ? 'i-lucide-loader-circle'
        : 'i-lucide-circle',
    value: index
  }))
)

const officeAccordionItems: AccordionItem[] = [
  {
    label: 'Perfil',
    icon: 'i-lucide-building-2',
    value: 'perfil',
    slot: 'perfil'
  },
  {
    label: 'Consentimento',
    icon: 'i-lucide-shield-check',
    value: 'consentimento',
    slot: 'consentimento'
  },
  {
    label: 'Certificado A1',
    icon: 'i-lucide-badge-check',
    value: 'certificado',
    slot: 'certificado'
  },
  {
    label: 'Agendas',
    icon: 'i-lucide-calendar-days',
    value: 'agendas',
    slot: 'agendas'
  }
]
const onboardingAvailableModules = computed(() =>
  onboarding.value?.modules?.filter(module => module.allowed).length || 0
)
const onboardingUnavailableModules = computed(() =>
  onboarding.value?.modules?.filter(module => !module.allowed) || []
)
const shouldPollOnboarding = computed(() =>
  onboardingIsInProgress(onboarding.value?.status)
  && !(onboarding.value?.actions?.length)
)

function procuracaoStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    authorized: 'Autorizadas',
    missing: 'Não encontradas',
    expired: 'Vencidas',
    unverified: 'Não verificadas',
    verifying: 'Verificando',
    failed: 'Falha ao verificar'
  }
  return labels[status] || status
}

function procuracaoStatusColor(status: string) {
  if (status === 'authorized') return 'success' as const
  if (status === 'verifying' || status === 'unverified') return 'info' as const
  if (status === 'missing' || status === 'expired') return 'warning' as const
  return 'error' as const
}

let onboardingPollTimer: ReturnType<typeof setInterval> | null = null

function stopOnboardingPolling() {
  if (onboardingPollTimer !== null) {
    clearInterval(onboardingPollTimer)
    onboardingPollTimer = null
  }
}

async function refreshOnboarding() {
  const epoch = sessionEpoch.value
  try {
    const response = await api.office.onboardingStatus()
    if (epoch !== sessionEpoch.value) return
    onboarding.value = response.data
  } catch {
    // O carregamento completo já apresenta erros; polling é silencioso e tenta novamente.
  }
}

function syncOnboardingPolling(active: boolean) {
  stopOnboardingPolling()
  if (!active) return
  onboardingPollTimer = setInterval(() => {
    void refreshOnboarding()
  }, 3000)
}

let loadSeq = 0

function isNotFound(err: unknown): boolean {
  const status = (err as { status?: number, statusCode?: number, response?: { status?: number } })?.status
    ?? (err as { statusCode?: number })?.statusCode
    ?? (err as { response?: { status?: number } })?.response?.status
  return status === 404
}

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  apiUnavailable.value = false

  try {
    const [profileRes, consentRes, credRes, schedRes, onboardRes] = await Promise.allSettled([
      api.office.profile.show(),
      api.office.technicalConsent.show(),
      api.office.canonicalCredential.show(),
      api.office.monitorSchedules.list(),
      api.office.onboardingStatus()
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    const notFoundCount = [profileRes, consentRes, credRes, schedRes].filter(
      r => r.status === 'rejected' && isNotFound(r.reason)
    ).length

    // Fallback legado: identidade fiscal se profile unificado ainda não existe.
    if (profileRes.status === 'fulfilled') {
      profile.value = profileRes.value.data
    } else if (isNotFound(profileRes.reason)) {
      try {
        const legacy = await api.officeFiscal.get()
        if (seq !== loadSeq || epoch !== sessionEpoch.value) return
        const id = legacy.data.identity
        profile.value = {
          cnpj: id?.cnpj != null ? String(id.cnpj) : null,
          legal_name: id?.legal_name != null ? String(id.legal_name) : null,
          institutional_email: id?.institutional_email != null ? String(id.institutional_email) : null,
          institutional_phone: id?.institutional_phone != null ? String(id.institutional_phone) : null
        }
        const cred = legacy.data.credential
        if (cred && !credential.value) {
          credential.value = {
            id: cred.id != null ? Number(cred.id) : null,
            status: String(cred.status || 'ACTIVE'),
            subject_name: cred.subject_name != null ? String(cred.subject_name) : null,
            holder_cnpj: cred.holder_cnpj != null ? String(cred.holder_cnpj) : (profile.value.cnpj),
            fingerprint_sha256: cred.fingerprint_sha256 != null ? String(cred.fingerprint_sha256) : null,
            valid_to: cred.valid_to != null ? String(cred.valid_to) : null,
            expires_alert_30: Boolean(cred.expires_alert_30),
            expires_alert_7: Boolean(cred.expires_alert_7),
            expires_alert_1: Boolean(cred.expires_alert_1)
          }
        }
      } catch {
        profile.value = null
      }
    } else {
      profile.value = null
      loadError.value = actionableOfficeError(
        apiErrorMessage(profileRes.reason, 'Falha ao carregar perfil.')
      )
    }

    if (consentRes.status === 'fulfilled') {
      consent.value = consentRes.value.data
    } else {
      consent.value = {
        version: '1',
        accepted: false,
        purposes: [],
        requires_reacceptance: true,
        text_summary: notFoundCount >= 2
          ? 'API de consentimento ainda não publicada neste ambiente.'
          : undefined
      }
    }

    if (credRes.status === 'fulfilled') {
      credential.value = credRes.value.data
    }

    if (schedRes.status === 'fulfilled') {
      policies.value = schedRes.value.data || []
    } else {
      policies.value = []
    }

    if (onboardRes.status === 'fulfilled') {
      onboarding.value = onboardRes.value.data
    } else {
      onboarding.value = emptyOnboarding()
    }

    if (notFoundCount >= 3) {
      apiUnavailable.value = true
    }
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    loadError.value = actionableOfficeError(
      apiErrorMessage(caught, 'Falha ao carregar configuração do escritório.')
    )
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function saveProfile(payload: {
  cnpj: string
  legal_name: string
  institutional_email: string
  institutional_phone: string
  confirm_cnpj_change?: boolean
}) {
  saving.value = true
  try {
    try {
      const res = await api.office.profile.update(payload)
      profile.value = res.data
    } catch (err) {
      if (!isNotFound(err)) throw err
      // Fallback legado (somente CNPJ/razão).
      const res = await api.officeFiscal.upsertIdentity({
        cnpj: payload.cnpj,
        legal_name: payload.legal_name
      })
      profile.value = {
        cnpj: res.data.cnpj != null ? String(res.data.cnpj) : payload.cnpj,
        legal_name: res.data.legal_name != null ? String(res.data.legal_name) : payload.legal_name,
        institutional_email: payload.institutional_email,
        institutional_phone: payload.institutional_phone
      }
    }
    toast.add({ title: 'Perfil salvo', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({
      title: actionableOfficeError(apiErrorMessage(caught, 'Falha ao salvar perfil.')),
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}

async function acceptConsent() {
  saving.value = true
  try {
    const res = await api.office.technicalConsent.accept()
    consent.value = res.data
    toast.add({ title: 'Consentimento registrado', color: 'success' })
  } catch (caught) {
    if (isNotFound(caught)) {
      toast.add({
        title: 'API de consentimento ainda não disponível neste ambiente.',
        color: 'warning'
      })
    } else {
      toast.add({
        title: actionableOfficeError(apiErrorMessage(caught, 'Falha ao registrar consentimento.')),
        color: 'error'
      })
    }
  } finally {
    saving.value = false
  }
}

async function revokeConsent() {
  saving.value = true
  try {
    const res = await api.office.technicalConsent.revoke()
    consent.value = res.data
    toast.add({ title: 'Consentimento revogado', color: 'warning' })
  } catch (caught) {
    toast.add({
      title: actionableOfficeError(apiErrorMessage(caught, 'Falha ao revogar consentimento.')),
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}

async function uploadCredential(payload: {
  file: File
  password: string
  consent_accepted: boolean
}) {
  // Um único formulário inicia consentimento, validação e onboarding assíncrono.
  const cnpj = (profile.value?.cnpj || '').replace(/\D/g, '')
  if (!cnpj) {
    toast.add({
      title: 'Cadastre o CNPJ no perfil institucional antes de enviar o certificado A1.',
      color: 'warning'
    })
    return
  }

  saving.value = true
  try {
    try {
      const res = credential.value
        ? await api.office.canonicalCredential.replace(payload.file, payload.password, {
            consent_accepted: payload.consent_accepted
          })
        : await api.office.canonicalCredential.upload(payload.file, payload.password, {
            consent_accepted: payload.consent_accepted
          })
      credential.value = res.data
    } catch (err) {
      if (!isNotFound(err)) throw err
      await api.officeFiscal.uploadCredential(payload.file, payload.password)
      await load()
      toast.add({ title: 'Certificado A1 armazenado (sem recuperação)', color: 'success' })
      return
    }
    toast.add({
      title: 'Ativação iniciada',
      description: 'Agora o sistema valida, autoriza, carrega procurações e executa a primeira coleta.',
      color: 'success'
    })
    await load()
  } catch (caught) {
    toast.add({
      title: actionableOfficeError(apiErrorMessage(caught, 'Falha ao enviar A1.')),
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}

async function removeCredential(_payload: { reconfirm_password?: string } = {}) {
  saving.value = true
  try {
    await api.office.canonicalCredential.remove({ confirm: true })
    credential.value = null
    toast.add({ title: 'Certificado removido — finalidades bloqueadas', color: 'warning' })
    await load()
  } catch (caught) {
    toast.add({
      title: actionableOfficeError(apiErrorMessage(caught, 'Falha ao remover A1.')),
      color: 'error'
    })
  } finally {
    saving.value = false
  }
}

async function saveSchedule(payload: { monitor_key: string, day_of_month: number }) {
  savingScheduleKey.value = payload.monitor_key
  try {
    const res = await api.office.monitorSchedules.update(payload.monitor_key, {
      day_of_month: payload.day_of_month
    })
    const idx = policies.value.findIndex(p => p.monitor_key === payload.monitor_key)
    if (idx >= 0) policies.value[idx] = res.data
    else policies.value = [...policies.value, res.data]
    toast.add({ title: 'Agenda atualizada', color: 'success' })
  } catch (caught) {
    toast.add({
      title: isNotFound(caught)
        ? 'API de agendas ainda não disponível neste ambiente.'
        : actionableOfficeError(apiErrorMessage(caught, 'Falha ao salvar agenda.')),
      color: isNotFound(caught) ? 'warning' : 'error'
    })
  } finally {
    savingScheduleKey.value = null
  }
}

watch(sessionEpoch, () => {
  profile.value = null
  consent.value = null
  credential.value = null
  policies.value = []
  void load()
})
watch(shouldPollOnboarding, syncOnboardingPolling, { immediate: true })
onMounted(load)
onBeforeUnmount(stopOnboardingPolling)
</script>

<template>
  <div data-testid="settings-office-unified">
    <div class="flex flex-col gap-4 sm:gap-6">
      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-circle-x"
        :title="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
        data-testid="settings-load-error"
      />

      <UPageCard
        v-if="onboarding"
        variant="subtle"
        data-testid="settings-onboarding-status"
      >
        <div class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge
                :color="onboardingStatusColor(onboarding.status)"
                variant="subtle"
              >
                {{ onboardingStatusLabel(onboarding.status) }}
              </UBadge>
              <span
                v-if="onboarding.correlation_id"
                class="font-mono text-xs text-muted"
              >
                ref {{ onboarding.correlation_id }}
              </span>
            </div>
            <UButton
              color="neutral"
              variant="ghost"
              size="sm"
              icon="i-lucide-refresh-cw"
              label="Atualizar estado"
              @click="refreshOnboarding"
            />
          </div>

          <div
            v-if="!['incomplete', 'action_required', 'technical_error', 'revoked'].includes(onboarding.status)"
            class="min-w-0 overflow-x-auto"
            data-testid="settings-onboarding-stepper"
          >
            <UStepper
              :model-value="onboardingStagePosition"
              :items="onboardingStepperItems"
              color="primary"
              size="sm"
              class="w-full min-w-[36rem]"
              disabled
            />
          </div>

          <p v-if="onboarding.message" class="text-sm text-muted">
            {{ onboarding.message }}
          </p>

          <ul
            v-if="onboarding.actions?.length"
            class="space-y-1 text-sm text-muted"
          >
            <li
              v-for="action in onboarding.actions"
              :key="action.code"
              class="flex items-center gap-2"
            >
              <UIcon name="i-lucide-circle" class="size-1.5 shrink-0" />
              <span>{{ action.label }}</span>
            </li>
          </ul>

          <div
            v-if="onboarding.procuracoes || onboarding.modules?.length || onboarding.initial_collection"
            class="grid gap-3 sm:grid-cols-3"
          >
            <div class="rounded-lg border border-default p-3">
              <p class="text-xs font-medium text-muted">
                Procurações verificadas
              </p>
              <p class="mt-1 text-lg font-semibold tabular-nums">
                {{ onboarding.procuracoes?.verified || 0 }} / {{ onboarding.procuracoes?.total_clients || 0 }}
              </p>
              <div class="mt-2 flex flex-wrap gap-1">
                <UBadge
                  v-for="(count, status) in onboarding.procuracoes?.by_status || {}"
                  :key="status"
                  :color="procuracaoStatusColor(String(status))"
                  variant="subtle"
                  size="sm"
                >
                  {{ procuracaoStatusLabel(String(status)) }}: {{ count }}
                </UBadge>
              </div>
            </div>
            <div class="rounded-lg border border-default p-3">
              <p class="text-xs font-medium text-muted">
                Módulos consultivos
              </p>
              <p class="mt-1 text-lg font-semibold tabular-nums">
                {{ onboardingAvailableModules }} / {{ onboarding.modules?.length || 0 }} disponíveis
              </p>
              <p v-if="onboardingUnavailableModules.length" class="mt-2 text-xs text-muted">
                Pausados: {{ onboardingUnavailableModules.map(module => module.label).join(', ') }}
              </p>
            </div>
            <div class="rounded-lg border border-default p-3">
              <p class="text-xs font-medium text-muted">
                Primeira coleta
              </p>
              <p class="mt-1 text-lg font-semibold tabular-nums">
                {{ onboarding.initial_collection?.runs_finished || 0 }} / {{ onboarding.initial_collection?.runs_total || 0 }} concluídas
              </p>
              <p v-if="onboarding.initial_collection?.runs_pending" class="mt-2 text-xs text-muted">
                {{ onboarding.initial_collection.runs_pending }} execução(ões) em andamento
              </p>
            </div>
          </div>
        </div>
      </UPageCard>

      <ShellPanelAccordion
        :items="officeAccordionItems"
        type="multiple"
        :default-value="['perfil']"
        test-id="settings-office-accordion"
      >
        <template #perfil-body>
          <SettingsOfficeProfileSection
            :profile="profile"
            :loading="loading"
            :saving="saving"
            :readonly="readonly"
            :show-header="false"
            @save="saveProfile"
          />
        </template>
        <template #consentimento-body>
          <SettingsOfficeConsentSection
            :consent="consent"
            :loading="loading"
            :saving="saving"
            :readonly="readonly"
            :show-header="false"
            @accept="acceptConsent"
            @revoke="revokeConsent"
          />
        </template>
        <template #certificado-body>
          <SettingsOfficeCredentialSection
            :credential="credential"
            :loading="loading"
            :saving="saving"
            :readonly="readonly"
            :require-password-reconfirm="false"
            :show-header="false"
            @upload="uploadCredential"
            @remove="removeCredential"
          />
        </template>
        <template #agendas-body>
          <SettingsOfficeSchedulesSection
            :policies="policies"
            :loading="loading"
            :saving-key="savingScheduleKey"
            :readonly="readonly"
            :show-header="false"
            @save="saveSchedule"
          />
        </template>
      </ShellPanelAccordion>

      <SettingsDteCanaryOfficeCard />
    </div>
  </div>
</template>
