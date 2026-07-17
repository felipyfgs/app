<script setup lang="ts">
/**
 * Configuração global SERPRO (Proprietário).
 * Arquétipo Settings: seções com UPageCard naked + subtle.
 * Sem preenchimento de segredo em re-leitura; sem download de vault.
 */
import type {
  SerproCredentialVersionSanitized,
  SerproExternalGateSanitized,
  SerproPlatformConfiguration
} from '~/types/api'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

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

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.serpro.configuration.show({ environment: environment.value })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
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
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    configuration.value = null
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar configuração SERPRO.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
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
      upload.pfx = null
      upload.password = ''
      upload.consumer_key = ''
      upload.consumer_secret = ''
      upload.notes = ''
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

function onPfxChange(e: Event) {
  const input = e.target as HTMLInputElement
  upload.pfx = input.files?.[0] || null
}

const summary = computed(() => configuration.value?.summary)
const pendingVersions = computed(() => configuration.value?.pending_credential_versions || [])
const history = computed(() => configuration.value?.credential_history || [])
const gates = computed(() => configuration.value?.external_gates || [])
const pendingOffices = computed(() => configuration.value?.pending_offices?.items || [])

watch(environment, () => {
  void load()
})
watch(sessionEpoch, () => {
  configuration.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <div
    data-testid="admin-serpro-configuration"
    class="space-y-6"
  >
    <UPageCard
      title="Configuração SERPRO"
      description="Ambiente Trial/Production isolados. Segredos só no vault — sem recuperação pela API."
      variant="naked"
      orientation="horizontal"
      class="mb-2"
    >
      <div class="flex w-fit flex-wrap items-end gap-2 lg:ms-auto">
        <UFormField label="Ambiente">
          <USelect
            v-model="environment"
            :items="envItems"
            value-key="value"
            class="w-40"
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
      :title="loadError"
      data-testid="serpro-config-error"
    />

    <div
      v-if="loading && !configuration"
      class="text-sm text-muted"
      data-testid="serpro-config-loading"
    >
      Carregando configuração…
    </div>

    <template v-if="configuration">
      <!-- Readiness summary -->
      <UPageCard
        title="Prontidão"
        description="Resumo sanitizado do ambiente selecionado."
        variant="subtle"
        data-testid="serpro-config-summary"
      >
        <div class="flex flex-wrap gap-2">
          <UBadge
            :color="summary?.configuration_ready ? 'success' : 'warning'"
            variant="subtle"
          >
            {{ summary?.configuration_ready ? 'Pronto' : 'Incompleto' }}
          </UBadge>
          <UBadge
            :color="summary?.has_active_credential ? 'success' : 'neutral'"
            variant="subtle"
          >
            Credencial ativa: {{ summary?.has_active_credential ? 'sim' : 'não' }}
          </UBadge>
          <UBadge
            :color="summary?.kill_switch_active ? 'error' : 'success'"
            variant="subtle"
          >
            Kill switch: {{ summary?.kill_switch_active ? `ON (${summary?.kill_switch_source || '—'})` : 'OFF' }}
          </UBadge>
          <UBadge
            v-if="environment === 'PRODUCTION'"
            :color="summary?.gates_blocking ? 'error' : 'success'"
            variant="subtle"
          >
            Gates: {{ summary?.gates_blocking ? 'bloqueando' : 'ok' }}
          </UBadge>
          <UBadge
            :color="summary?.usage_allowed ? (summary?.usage_alert_reached ? 'warning' : 'success') : 'error'"
            variant="subtle"
          >
            Limite: {{ summary?.usage_allowed ? (summary?.usage_alert_reached ? 'alerta 80%' : 'ok') : 'bloqueado' }}
          </UBadge>
        </div>
        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted">
              OAuth oficial
            </dt>
            <dd class="font-mono text-xs break-all">
              {{ configuration.endpoints?.oauth_token_url || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              API Integra
            </dt>
            <dd class="font-mono text-xs break-all">
              {{ configuration.endpoints?.api_base_url || '—' }}
            </dd>
          </div>
        </dl>
      </UPageCard>

      <!-- Credenciais -->
      <UPageCard
        title="Credenciais versionadas"
        description="Ciclo PENDING → VERIFIED → teste OAuth → cutover. Não reexibe segredo."
        variant="subtle"
        data-testid="serpro-config-credentials"
      >
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
          <UFormField
            label="PFX do Contratante"
            required
          >
            <input
              type="file"
              accept=".pfx,.p12"
              class="text-sm"
              data-testid="serpro-config-pfx"
              @change="onPfxChange"
            >
          </UFormField>
          <UFormField
            label="Senha do PFX"
            required
          >
            <UInput
              v-model="upload.password"
              type="password"
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
              autocomplete="new-password"
              data-testid="serpro-config-cs"
            />
          </UFormField>
        </div>
        <UButton
          label="Cadastrar versão PENDING"
          icon="i-lucide-upload"
          :loading="acting"
          data-testid="serpro-config-upload"
          @click="submitUpload"
        />

        <div
          v-if="configuration.active_credential_version"
          class="mt-6 rounded-lg border border-default p-3 text-sm"
        >
          <p class="font-medium">
            Ativa: v{{ configuration.active_credential_version.version_number }}
            · {{ configuration.active_credential_version.status }}
          </p>
          <p class="text-muted">
            Key …{{ configuration.active_credential_version.consumer_key_last4 || '—' }}
            · CNPJ {{ configuration.active_credential_version.contractor_cnpj_masked || '—' }}
            · FP {{ (configuration.active_credential_version.fingerprint_sha256 || '').slice(0, 12) }}…
          </p>
        </div>

        <ul
          v-if="pendingVersions.length"
          class="mt-4 space-y-3"
        >
          <li
            v-for="v in pendingVersions"
            :key="v.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-default p-3"
            :data-testid="`serpro-config-version-${v.id}`"
          >
            <div class="text-sm">
              <span class="font-medium">v{{ v.version_number }}</span>
              <UBadge
                class="ms-2"
                variant="subtle"
                color="neutral"
              >
                {{ v.status }}
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
                label="Verificar"
                :loading="acting"
                @click="verifyVersion(v)"
              />
              <UButton
                v-if="v.status === 'VERIFIED' || v.status === 'ACTIVE'"
                size="xs"
                variant="soft"
                label="Testar OAuth"
                :loading="acting"
                @click="testConnection(v)"
              />
              <UButton
                v-if="v.status === 'VERIFIED'"
                size="xs"
                color="primary"
                label="Cutover"
                :loading="acting"
                @click="cutover(v)"
              />
            </div>
          </li>
        </ul>
        <p
          v-else
          class="mt-4 text-sm text-muted"
        >
          Nenhuma versão pendente neste ambiente.
        </p>
      </UPageCard>

      <!-- Gates (Production) -->
      <UPageCard
        v-if="environment === 'PRODUCTION'"
        title="Gates externos"
        description="Seis gates baseline. Referência, resumo, responsável e data — sem PDF/waiver."
        variant="subtle"
        data-testid="serpro-config-gates"
      >
        <div class="space-y-4">
          <div
            v-for="g in gates"
            :key="g.kind"
            class="rounded-lg border border-default p-3"
          >
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span class="text-sm font-medium">{{ g.label || g.kind }}</span>
              <UBadge
                :color="g.status === 'ACCEPTED' ? 'success' : 'warning'"
                variant="subtle"
              >
                {{ g.status }}
              </UBadge>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <UFormField label="Referência">
                <UInput
                  v-model="gateDrafts[g.kind]!.ticket_ref"
                  :disabled="g.status === 'ACCEPTED'"
                />
              </UFormField>
              <UFormField label="Responsável">
                <UInput
                  v-model="gateDrafts[g.kind]!.responsible_name"
                  :disabled="g.status === 'ACCEPTED'"
                />
              </UFormField>
              <UFormField label="Data">
                <UInput
                  v-model="gateDrafts[g.kind]!.reference_date"
                  type="date"
                  :disabled="g.status === 'ACCEPTED'"
                />
              </UFormField>
              <UFormField label="Resumo">
                <UInput
                  v-model="gateDrafts[g.kind]!.answer_summary"
                  :disabled="g.status === 'ACCEPTED'"
                />
              </UFormField>
            </div>
            <UButton
              v-if="g.status !== 'ACCEPTED'"
              class="mt-2"
              size="xs"
              label="Aceitar gate"
              :loading="acting"
              @click="acceptGate(g)"
            />
          </div>
        </div>
      </UPageCard>

      <!-- Limites -->
      <UPageCard
        title="Limites quantitativos"
        description="Ciclo 1–28, alerta 80%, teto positivo. Null/zero bloqueiam (fail-closed)."
        variant="subtle"
        data-testid="serpro-config-limits"
      >
        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField label="Dia inicial do ciclo (1–28)">
            <UInput
              v-model.number="limitsForm.cycle_start_day"
              type="number"
              min="1"
              max="28"
            />
          </UFormField>
          <UFormField label="Alerta (%)">
            <UInput
              v-model.number="limitsForm.alert_percent"
              type="number"
              min="1"
              max="100"
            />
          </UFormField>
          <UFormField label="Limite global (quantidade)">
            <UInput
              v-model.number="limitsForm.global_limit_quantity"
              type="number"
              min="1"
              placeholder="obrigatório positivo"
            />
          </UFormField>
        </div>
        <UButton
          class="mt-3"
          label="Salvar limites"
          :loading="acting"
          @click="saveLimits"
        />
      </UPageCard>

      <!-- Histórico -->
      <UPageCard
        title="Histórico de versões"
        description="Metadados sanitizados — sem segredo, vault id ou download."
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
              color="neutral"
            >
              {{ h.status }}
            </UBadge>
            <span class="text-muted">…{{ h.consumer_key_last4 || '—' }}</span>
            <span class="text-muted">{{ h.created_at || '' }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-muted"
        >
          Sem histórico neste ambiente.
        </p>
      </UPageCard>

      <!-- Offices pendentes -->
      <UPageCard
        title="Offices pendentes"
        description="Resumo sanitizado. Detalhes no contexto do Office em /settings."
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
              label="Abrir settings"
              to="/settings"
              icon="i-lucide-external-link"
            />
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-muted"
        >
          Nenhum Office pendente listado.
        </p>
      </UPageCard>
    </template>

    <p
      v-if="!loading && !configuration && !loadError"
      class="text-sm text-muted"
      data-testid="serpro-config-empty"
    >
      Nenhuma configuração carregada.
    </p>

    <UModal
      v-model:open="passwordModalOpen"
      title="Reconfirmar senha"
      data-testid="serpro-config-password-modal"
    >
      <template #body>
        <p class="mb-3 text-sm text-muted">
          Mutações sensíveis exigem senha da sessão (válida por 15 minutos).
        </p>
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
            variant="ghost"
            label="Cancelar"
            @click="() => { passwordModalOpen = false }"
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
</template>
