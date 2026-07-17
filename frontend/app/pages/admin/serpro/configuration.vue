<script setup lang="ts">
/**
 * Configuração global SERPRO (Proprietário).
 * Arquétipo Settings: seções com UPageCard naked + subtle.
 * Sem preenchimento de segredo em re-leitura; sem download de vault.
 */
import type { AccordionItem, TabsItem } from '@nuxt/ui'
import type {
  SerproCredentialVersionSanitized,
  SerproExternalGateSanitized,
  SerproPlatformConfiguration
} from '~/types/api'
import CatalogView from './catalog.vue'
import ContractsView from './contracts.vue'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()
const route = useRoute()

type IntegrationSection = 'access' | 'contracts' | 'coverage'

const integrationSections = [{
  label: 'Acesso',
  icon: 'i-lucide-key-round',
  value: 'access'
}, {
  label: 'Contratos',
  icon: 'i-lucide-file-badge',
  value: 'contracts'
}, {
  label: 'Cobertura',
  icon: 'i-lucide-layout-grid',
  value: 'coverage'
}] satisfies TabsItem[]

const activeSection = computed<IntegrationSection>(() => {
  const section = Array.isArray(route.query.section)
    ? route.query.section[0]
    : route.query.section

  return section === 'contracts' || section === 'coverage' ? section : 'access'
})

function selectSection(value: string | number) {
  const section = String(value) as IntegrationSection
  const query = { ...route.query }

  if (section === 'access') delete query.section
  else query.section = section

  void navigateTo({ path: '/admin/serpro/configuration', query }, { replace: true })
}

const loading = ref(false)
const acting = ref(false)
const loadError = ref<string | null>(null)
const configuration = ref<SerproPlatformConfiguration | null>(null)
const environment = ref<'TRIAL' | 'PRODUCTION'>('TRIAL')
const passwordModalOpen = ref(false)
const passwordInput = ref('')
const pendingAction = ref<null | (() => Promise<void>)>(null)

const envItems = [
  { label: 'Trial', value: 'TRIAL' },
  { label: 'Produção', value: 'PRODUCTION' }
]

// Upload form — segredos só no envio; nunca re-hidratados da API.
const upload = reactive({
  pfx: null as File | null,
  password: '',
  consumer_key: '',
  consumer_secret: '',
  notes: ''
})

const limitsForm = reactive({
  cycle_start_day: 1,
  alert_percent: 80,
  global_limit_quantity: null as number | null
})

const gateDrafts = ref<Record<string, {
  ticket_ref: string
  answer_summary: string
  responsible_name: string
  reference_date: string
}>>({})

function clearUpload() {
  upload.pfx = null
  upload.password = ''
  upload.consumer_key = ''
  upload.consumer_secret = ''
  upload.notes = ''
}

function resetLimits() {
  limitsForm.cycle_start_day = 1
  limitsForm.alert_percent = 80
  limitsForm.global_limit_quantity = null
}

interface GateAccordionItem extends AccordionItem {
  gate: SerproExternalGateSanitized
}

let loadSeq = 0

async function load() {
  if (activeSection.value !== 'access') return

  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  const requestedEnvironment = environment.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.serpro.configuration.show({ environment: requestedEnvironment })
    if (
      seq !== loadSeq
      || epoch !== sessionEpoch.value
      || requestedEnvironment !== environment.value
    ) return
    configuration.value = res.data || null
    const cfg = res.data?.usage_limits?.config as Record<string, unknown> | undefined
    if (cfg) {
      limitsForm.cycle_start_day = Number(cfg.cycle_start_day ?? 1)
      limitsForm.alert_percent = Number(cfg.alert_percent ?? 80)
      limitsForm.global_limit_quantity = cfg.global_limit_quantity != null
        ? Number(cfg.global_limit_quantity)
        : null
    }
    const gates = res.data?.external_gates || []
    const drafts: typeof gateDrafts.value = {}
    for (const g of gates) {
      drafts[g.kind] = {
        ticket_ref: g.ticket_ref || '',
        answer_summary: g.answer_summary || '',
        responsible_name: g.responsible_name || '',
        reference_date: g.reference_date || ''
      }
    }
    gateDrafts.value = drafts
  } catch (caught) {
    if (
      seq !== loadSeq
      || epoch !== sessionEpoch.value
      || requestedEnvironment !== environment.value
    ) return
    configuration.value = null
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar configuração SERPRO.')
  } finally {
    if (
      seq === loadSeq
      && epoch === sessionEpoch.value
      && requestedEnvironment === environment.value
    ) {
      loading.value = false
    }
  }
}

function requestPasswordThen(fn: () => Promise<void>) {
  pendingAction.value = fn
  passwordInput.value = ''
  passwordModalOpen.value = true
}

async function submitPassword() {
  if (!passwordInput.value.trim()) {
    toast.add({ title: 'Informe a senha da sessão.', color: 'warning' })
    return
  }
  acting.value = true
  try {
    await api.confirmPassword(passwordInput.value.trim())
    passwordModalOpen.value = false
    const action = pendingAction.value
    pendingAction.value = null
    passwordInput.value = ''
    if (action) await action()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Senha inválida ou confirmação expirada.'),
      color: 'error'
    })
  } finally {
    acting.value = false
  }
}

function submitUpload() {
  if (!upload.pfx || !upload.password || !upload.consumer_key || !upload.consumer_secret) {
    toast.add({ title: 'Preencha PFX, senha, Key e Secret.', color: 'warning' })
    return
  }
  requestPasswordThen(async () => {
    try {
      const body = new FormData()
      body.append('environment', environment.value)
      body.append('pfx', upload.pfx!)
      body.append('password', upload.password)
      body.append('consumer_key', upload.consumer_key)
      body.append('consumer_secret', upload.consumer_secret)
      if (upload.notes) body.append('notes', upload.notes)
      await api.platform.serpro.credentialVersions.store(body)
      clearUpload()
      toast.add({ title: 'Versão PENDING cadastrada no vault.', color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha no upload.'), color: 'error' })
    }
  })
}

function verifyVersion(v: SerproCredentialVersionSanitized) {
  requestPasswordThen(async () => {
    try {
      await api.platform.serpro.credentialVersions.verify(v.id)
      toast.add({ title: `Versão v${v.version_number} verificada.`, color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha na verificação.'), color: 'error' })
    }
  })
}

function testConnection(v: SerproCredentialVersionSanitized) {
  requestPasswordThen(async () => {
    try {
      await api.platform.serpro.credentialVersions.testConnection(v.id)
      toast.add({ title: 'Teste OAuth mTLS OK (evidência registrada).', color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Teste OAuth falhou.'), color: 'error' })
    }
  })
}

function cutover(v: SerproCredentialVersionSanitized) {
  requestPasswordThen(async () => {
    try {
      await api.platform.serpro.credentialVersions.cutover(v.id, {
        reason: 'Cutover via Configuração SERPRO'
      })
      toast.add({ title: `Cutover v${v.version_number} concluído.`, color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Cutover bloqueado.'), color: 'error' })
    }
  })
}

function acceptGate(g: SerproExternalGateSanitized) {
  const draft = gateDrafts.value[g.kind]
  if (!draft?.ticket_ref || !draft.answer_summary || !draft.responsible_name || !draft.reference_date) {
    toast.add({ title: 'Referência, resumo, responsável e data são obrigatórios.', color: 'warning' })
    return
  }
  requestPasswordThen(async () => {
    try {
      await api.platform.serpro.externalGates.update(g.kind, {
        ...draft,
        environment: 'PRODUCTION'
      })
      toast.add({ title: `Gate ${g.kind} aceito.`, color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao aceitar gate.'), color: 'error' })
    }
  })
}

function saveLimits() {
  requestPasswordThen(async () => {
    try {
      await api.platform.serpro.usageLimits.update({
        environment: environment.value,
        cycle_start_day: limitsForm.cycle_start_day,
        alert_percent: limitsForm.alert_percent,
        global_limit_quantity: limitsForm.global_limit_quantity ?? undefined
      })
      toast.add({ title: 'Limites quantitativos salvos.', color: 'success' })
      await load()
    } catch (caught) {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao salvar limites.'), color: 'error' })
    }
  })
}

const summary = computed(() => configuration.value?.summary)
const pendingVersions = computed(() => configuration.value?.pending_credential_versions || [])
const history = computed(() => configuration.value?.credential_history || [])
const gates = computed(() => configuration.value?.external_gates || [])
const pendingOffices = computed(() => configuration.value?.pending_offices?.items || [])
const gateItems = computed<GateAccordionItem[]>(() => gates.value.map(gate => ({
  label: gate.label || gate.kind,
  value: gate.kind,
  icon: gate.status === 'ACCEPTED' ? 'i-lucide-circle-check' : 'i-lucide-circle-dashed',
  gate
})))

function credentialStatusLabel(status: string): string {
  return {
    PENDING: 'Pendente',
    VERIFIED: 'Verificada',
    ACTIVE: 'Ativa',
    RETIRED: 'Substituída',
    COMPROMISED: 'Comprometida'
  }[status] || status
}

function credentialStatusColor(status: string): 'success' | 'warning' | 'error' | 'neutral' {
  if (status === 'ACTIVE') return 'success'
  if (status === 'PENDING' || status === 'VERIFIED') return 'warning'
  if (status === 'COMPROMISED') return 'error'
  return 'neutral'
}

function gateStatusLabel(status: string): string {
  return status === 'ACCEPTED' ? 'Aceito' : 'Pendente'
}

function cancelPasswordConfirmation() {
  passwordModalOpen.value = false
  passwordInput.value = ''
  pendingAction.value = null
}

function resetEnvironmentState() {
  loadSeq++
  loading.value = false
  configuration.value = null
  loadError.value = null
  gateDrafts.value = {}
  clearUpload()
  resetLimits()
  cancelPasswordConfirmation()
}

watch(environment, () => {
  resetEnvironmentState()
  void load()
})
watch(sessionEpoch, () => {
  resetEnvironmentState()
  void load()
})
watch(activeSection, (section) => {
  resetEnvironmentState()
  if (section === 'access') void load()
})
onMounted(() => {
  if (activeSection.value === 'access') void load()
})
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="admin-serpro-integration">
    <UTabs
      :model-value="activeSection"
      :items="integrationSections"
      :content="false"
      color="neutral"
      variant="pill"
      class="w-full"
      aria-label="Seções da integração SERPRO"
      data-testid="admin-serpro-integration-sections"
      @update:model-value="selectSection"
    />

    <div
      v-if="activeSection === 'access'"
      data-testid="admin-serpro-configuration"
      class="space-y-8"
    >
      <UPageCard
        title="Configuração por ambiente"
        variant="naked"
        orientation="horizontal"
      >
        <div class="flex w-fit flex-wrap items-end gap-2 lg:ms-auto">
          <UFormField label="Ambiente">
            <USelect
              v-model="environment"
              :items="envItems"
              value-key="value"
              class="w-40"
              aria-label="Ambiente da configuração SERPRO"
              data-testid="serpro-config-env"
            />
          </UFormField>
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            @click="load"
          />
        </div>
      </UPageCard>

      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-circle-x"
        title="Não foi possível carregar a configuração"
        :description="loadError"
        data-testid="serpro-config-error"
      >
        <template #actions>
          <UButton
            label="Tentar novamente"
            color="error"
            variant="soft"
            size="sm"
            :loading="loading"
            @click="load"
          />
        </template>
      </UAlert>

      <div
        v-if="loading && !configuration"
        class="space-y-4"
        role="status"
        aria-live="polite"
        data-testid="serpro-config-loading"
      >
        <span class="sr-only">Carregando configuração SERPRO.</span>
        <USkeleton class="h-32 w-full rounded-lg" />
        <USkeleton class="h-52 w-full rounded-lg" />
      </div>

      <template v-if="configuration">
        <UPageCard
          variant="subtle"
          data-testid="serpro-config-summary"
        >
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="text-sm text-muted">
                {{ environment === 'PRODUCTION' ? 'Produção' : 'Trial' }}
              </p>
              <h2 class="mt-1 text-lg font-semibold text-highlighted">
                Estado da configuração
              </h2>
            </div>
            <UBadge
              :color="summary?.configuration_ready ? 'success' : 'warning'"
              variant="subtle"
              size="lg"
            >
              {{ summary?.configuration_ready ? 'Pronta para operar' : 'Requer configuração' }}
            </UBadge>
          </div>

          <USeparator class="my-4" />

          <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <dt class="text-muted">
                Credencial
              </dt>
              <dd class="mt-1 font-medium text-highlighted">
                {{ summary ? (summary.has_active_credential ? 'Ativa' : 'Não ativada') : 'Indisponível' }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Kill switch
              </dt>
              <dd
                class="mt-1 font-medium"
                :class="summary?.kill_switch_active ? 'text-error' : 'text-highlighted'"
              >
                {{ summary ? (summary.kill_switch_active ? 'Ativo' : 'Desligado') : 'Indisponível' }}
              </dd>
            </div>
            <div v-if="environment === 'PRODUCTION'">
              <dt class="text-muted">
                Liberações externas
              </dt>
              <dd class="mt-1 font-medium text-highlighted">
                {{ summary ? (summary.gates_blocking ? 'Pendentes' : 'Concluídas') : 'Indisponível' }}
              </dd>
            </div>
            <div>
              <dt class="text-muted">
                Limite de uso
              </dt>
              <dd class="mt-1 font-medium text-highlighted">
                {{ summary ? (summary.usage_allowed ? (summary.usage_alert_reached ? 'Próximo do limite' : 'Disponível') : 'Bloqueado') : 'Indisponível' }}
              </dd>
            </div>
          </dl>

          <details class="mt-4 border-t border-default pt-4 text-sm">
            <summary class="cursor-pointer font-medium text-muted hover:text-highlighted">
              Ver endpoints oficiais
            </summary>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  OAuth
                </dt>
                <dd class="mt-1 break-all font-mono text-xs text-highlighted">
                  {{ configuration.endpoints?.oauth_token_url || 'Não informado' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Integra Contador
                </dt>
                <dd class="mt-1 break-all font-mono text-xs text-highlighted">
                  {{ configuration.endpoints?.api_base_url || 'Não informado' }}
                </dd>
              </div>
            </dl>
          </details>
        </UPageCard>

        <UPageCard
          title="Credenciais"
          variant="subtle"
          data-testid="serpro-config-credentials"
        >
          <div class="mb-4 grid gap-4 sm:grid-cols-2">
            <UFormField
              label="Certificado do contratante (.pfx ou .p12)"
              required
              class="sm:col-span-2"
            >
              <UFileUpload
                v-model="upload.pfx"
                accept=".pfx,.p12,application/x-pkcs12"
                label="Selecione ou arraste o arquivo"
                icon="i-lucide-file-key-2"
                layout="list"
                position="inside"
                class="w-full"
                :ui="{ base: 'min-h-28' }"
                data-testid="serpro-config-pfx"
              />
            </UFormField>
            <UFormField
              label="Senha do PFX"
              required
            >
              <UInput
                v-model="upload.password"
                type="password"
                class="w-full"
                autocomplete="new-password"
                data-testid="serpro-config-pfx-password"
              />
            </UFormField>
            <UFormField
              label="Consumer Key"
              required
            >
              <UInput
                v-model="upload.consumer_key"
                class="w-full"
                autocomplete="off"
                data-testid="serpro-config-ck"
              />
            </UFormField>
            <UFormField
              label="Consumer Secret"
              required
            >
              <UInput
                v-model="upload.consumer_secret"
                type="password"
                class="w-full"
                autocomplete="new-password"
                data-testid="serpro-config-cs"
              />
            </UFormField>
          </div>
          <UButton
            label="Cadastrar nova versão"
            icon="i-lucide-upload"
            :loading="acting"
            data-testid="serpro-config-upload"
            @click="submitUpload"
          />

          <div
            v-if="configuration.active_credential_version"
            class="mt-6 rounded-lg bg-elevated/50 p-4 text-sm"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="font-medium text-highlighted">
                Versão ativa · v{{ configuration.active_credential_version.version_number }}
              </p>
              <UBadge color="success" variant="subtle">
                Ativa
              </UBadge>
            </div>
            <p class="mt-2 text-muted">
              Key …{{ configuration.active_credential_version.consumer_key_last4 || 'Não informada' }}
              · CNPJ {{ configuration.active_credential_version.contractor_cnpj_masked || 'não informado' }}
            </p>
          </div>

          <div class="mt-6 flex items-center justify-between gap-3">
            <h3 class="font-medium text-highlighted">
              Versões em preparação
            </h3>
            <UBadge color="neutral" variant="subtle">
              {{ pendingVersions.length }}
            </UBadge>
          </div>

          <ul
            v-if="pendingVersions.length"
            class="mt-3 divide-y divide-default"
          >
            <li
              v-for="v in pendingVersions"
              :key="v.id"
              class="flex flex-wrap items-center justify-between gap-3 py-3"
              :data-testid="`serpro-config-version-${v.id}`"
            >
              <div class="text-sm">
                <span class="font-medium">v{{ v.version_number }}</span>
                <UBadge
                  class="ms-2"
                  variant="subtle"
                  :color="credentialStatusColor(v.status)"
                >
                  {{ credentialStatusLabel(v.status) }}
                </UBadge>
                <span class="ms-2 text-muted">…{{ v.consumer_key_last4 || '—' }}</span>
                <span
                  v-if="v.has_recent_connection_test"
                  class="ms-2 text-success"
                >teste OK</span>
              </div>
              <div class="flex flex-wrap gap-2">
                <UButton
                  v-if="v.status === 'PENDING'"
                  size="xs"
                  variant="soft"
                  label="Verificar certificado"
                  :loading="acting"
                  @click="verifyVersion(v)"
                />
                <UButton
                  v-else-if="v.status === 'VERIFIED' && !v.has_recent_connection_test"
                  size="xs"
                  variant="soft"
                  label="Testar OAuth"
                  :loading="acting"
                  @click="testConnection(v)"
                />
                <UButton
                  v-else-if="v.status === 'VERIFIED' && v.has_recent_connection_test"
                  size="xs"
                  color="primary"
                  label="Cutover"
                  :loading="acting"
                  @click="cutover(v)"
                />
                <UButton
                  v-else-if="v.status === 'ACTIVE'"
                  size="xs"
                  color="neutral"
                  variant="outline"
                  label="Testar conexão"
                  :loading="acting"
                  @click="testConnection(v)"
                />
              </div>
            </li>
          </ul>
          <p
            v-else
            class="mt-4 text-sm text-muted"
          >
            Nenhuma versão aguardando ação neste ambiente.
          </p>
        </UPageCard>

        <UPageCard
          v-if="environment === 'PRODUCTION'"
          title="Liberações externas"
          variant="subtle"
          data-testid="serpro-config-gates"
        >
          <UAccordion
            v-if="gateItems.length"
            :items="gateItems"
            :unmount-on-hide="false"
            :ui="{ label: 'w-full', body: 'pb-4' }"
          >
            <template #default="{ item }">
              <span class="flex w-full items-center justify-between gap-3 pe-2">
                <span>{{ item.label }}</span>
                <UBadge
                  :color="item.gate.status === 'ACCEPTED' ? 'success' : 'warning'"
                  variant="subtle"
                >
                  {{ gateStatusLabel(item.gate.status) }}
                </UBadge>
              </span>
            </template>

            <template #body="{ item }">
              <div class="grid gap-3 sm:grid-cols-2">
                <UFormField label="Referência externa">
                  <UInput
                    v-model="gateDrafts[item.gate.kind]!.ticket_ref"
                    class="w-full"
                    :disabled="item.gate.status === 'ACCEPTED'"
                  />
                </UFormField>
                <UFormField label="Responsável">
                  <UInput
                    v-model="gateDrafts[item.gate.kind]!.responsible_name"
                    class="w-full"
                    :disabled="item.gate.status === 'ACCEPTED'"
                  />
                </UFormField>
                <UFormField label="Data de referência">
                  <UInput
                    v-model="gateDrafts[item.gate.kind]!.reference_date"
                    type="date"
                    class="w-full"
                    :disabled="item.gate.status === 'ACCEPTED'"
                  />
                </UFormField>
                <UFormField label="Resumo da evidência">
                  <UTextarea
                    v-model="gateDrafts[item.gate.kind]!.answer_summary"
                    class="w-full"
                    autoresize
                    :rows="2"
                    :maxrows="4"
                    :disabled="item.gate.status === 'ACCEPTED'"
                  />
                </UFormField>
              </div>
              <UButton
                v-if="item.gate.status !== 'ACCEPTED'"
                class="mt-3"
                size="sm"
                label="Registrar liberação"
                :loading="acting"
                @click="acceptGate(item.gate)"
              />
            </template>
          </UAccordion>

          <div
            v-else
            class="flex items-center gap-3 py-4 text-sm text-muted"
            role="status"
          >
            <UIcon name="i-lucide-clipboard-check" class="size-5" aria-hidden="true" />
            Nenhuma liberação externa foi listada.
          </div>
        </UPageCard>

        <UPageCard
          title="Limites de uso"
          variant="subtle"
          data-testid="serpro-config-limits"
        >
          <div class="grid gap-4 sm:grid-cols-3">
            <UFormField
              label="Dia inicial (1–28)"
            >
              <UInputNumber
                v-model="limitsForm.cycle_start_day"
                :min="1"
                :max="28"
                class="w-full"
              />
            </UFormField>
            <UFormField
              label="Alerta (%)"
            >
              <UInputNumber
                v-model="limitsForm.alert_percent"
                :min="1"
                :max="100"
                class="w-full"
              />
            </UFormField>
            <UFormField
              label="Teto do ciclo"
            >
              <UInputNumber
                v-model="limitsForm.global_limit_quantity"
                :min="1"
                placeholder="Informe o teto"
                class="w-full"
              />
            </UFormField>
          </div>
          <UButton
            class="mt-4"
            label="Salvar limites"
            :loading="acting"
            @click="saveLimits"
          />
        </UPageCard>

        <div class="grid items-start gap-6 lg:grid-cols-2">
          <UPageCard
            title="Histórico de versões"
            variant="subtle"
            data-testid="serpro-config-history"
          >
            <ul
              v-if="history.length"
              class="space-y-2 text-sm"
            >
              <li
                v-for="h in history"
                :key="h.id"
                class="flex flex-wrap gap-2 border-b border-default pb-2 last:border-0"
              >
                <span>v{{ h.version_number }}</span>
                <UBadge
                  variant="subtle"
                  :color="credentialStatusColor(h.status)"
                >
                  {{ credentialStatusLabel(h.status) }}
                </UBadge>
                <span class="text-muted">…{{ h.consumer_key_last4 || '—' }}</span>
                <span class="text-muted">{{ h.created_at ? formatDateTime(h.created_at) : '' }}</span>
              </li>
            </ul>
            <p
              v-else
              class="text-sm text-muted"
            >
              Sem histórico neste ambiente.
            </p>
          </UPageCard>

          <UPageCard
            title="Aguardando ADMIN local"
            variant="subtle"
            data-testid="serpro-config-pending-offices"
          >
            <ul
              v-if="pendingOffices.length"
              class="space-y-2 text-sm"
            >
              <li
                v-for="o in pendingOffices"
                :key="o.office_id"
                class="flex flex-wrap items-center justify-between gap-2"
              >
                <span>{{ o.office_name || o.office_slug || `#${o.office_id}` }} · {{ o.status }}</span>
                <UButton
                  size="xs"
                  variant="ghost"
                  label="Ver escritório"
                  :to="`/admin/offices/${o.office_id}`"
                  icon="i-lucide-arrow-right"
                />
              </li>
            </ul>
            <p
              v-else
              class="text-sm text-muted"
            >
              Nenhum escritório pendente.
            </p>
          </UPageCard>
        </div>
      </template>

      <div
        v-if="!loading && !configuration && !loadError"
        class="flex min-h-40 flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-default text-center text-sm text-muted"
        role="status"
        data-testid="serpro-config-empty"
      >
        <UIcon name="i-lucide-settings-2" class="size-7" aria-hidden="true" />
        Nenhuma configuração carregada.
      </div>

      <UModal
        v-model:open="passwordModalOpen"
        title="Reconfirmar senha"
        data-testid="serpro-config-password-modal"
      >
        <template #body>
          <UFormField
            label="Senha"
            required
          >
            <UInput
              v-model="passwordInput"
              type="password"
              autocomplete="current-password"
              data-testid="serpro-config-session-password"
              @keyup.enter="submitPassword"
            />
          </UFormField>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="ghost"
              label="Cancelar"
              @click="cancelPasswordConfirmation"
            />
            <UButton
              label="Confirmar"
              :loading="acting"
              data-testid="serpro-config-password-submit"
              @click="submitPassword"
            />
          </div>
        </template>
      </UModal>
    </div>

    <ContractsView v-else-if="activeSection === 'contracts'" />
    <CatalogView v-else />
  </div>
</template>
