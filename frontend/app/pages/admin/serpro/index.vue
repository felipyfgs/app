<script setup lang="ts">
/**
 * Readiness + kill switch + visão de contrato ativo (console global).
 * Sem exibir Consumer Secret, PFX, token ou vault id.
 */
import type { SerproGlobalHealth, SerproKillSwitchStatus, SerproReadinessSnapshot } from '~/types/api'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const health = ref<SerproGlobalHealth | null>(null)
const readiness = ref<SerproReadinessSnapshot | null>(null)
const kill = ref<SerproKillSwitchStatus | null>(null)
const environment = ref('TRIAL')
const killReason = ref('')
const killLoading = ref(false)

const envItems = [
  { label: 'Trial', value: 'TRIAL' },
  { label: 'Produção', value: 'PRODUCTION' }
]

function deriveReadiness(h: SerproGlobalHealth | null): SerproReadinessSnapshot {
  if (!h) {
    return { overall: 'UNKNOWN', gates: [] }
  }
  const gates: SerproReadinessSnapshot['gates'] = []
  const ksActive = Boolean(h.kill_switch?.global?.active)
  gates.push({
    code: 'KILL_SWITCH',
    scope: 'global',
    status: ksActive ? 'FAIL' : 'PASS',
    message: ksActive
      ? `Kill switch ativo (${h.kill_switch?.global?.source || 'runtime'})`
      : 'Kill switch desligado'
  })
  gates.push({
    code: 'FAKE_CLIENTS',
    scope: 'global',
    status: h.fake_clients ? 'WARN' : 'PASS',
    message: h.fake_clients
      ? 'SERPRO_USE_FAKE_CLIENTS ainda habilitado — bloqueia produção'
      : 'Clientes fake desligados'
  })
  gates.push({
    code: 'ACTIVE_CONTRACT',
    scope: 'global',
    status: h.active_contract ? 'PASS' : 'FAIL',
    message: h.active_contract
      ? `Contrato #${h.active_contract.id} · ${h.active_contract.status}`
      : 'Nenhum contrato ativo no ambiente'
  })
  if (h.active_contract?.credentials_exposed) {
    gates.push({
      code: 'CREDENTIALS_EXPOSED',
      scope: 'global',
      status: 'FAIL',
      message: 'Credencial marcada como exposta — rotação obrigatória'
    })
  }
  const breakerState = String(h.circuit_breaker?.state || 'CLOSED').toUpperCase()
  gates.push({
    code: 'CIRCUIT_BREAKER',
    scope: 'global',
    status: breakerState === 'OPEN' ? 'FAIL' : breakerState === 'HALF_OPEN' ? 'WARN' : 'PASS',
    message: `Breaker ${breakerState}`
  })
  gates.push({
    code: 'SMOKE',
    scope: 'global',
    status: h.smoke_status === 'FREE_SMOKE_OK' ? 'PASS' : 'WARN',
    message: `Smoke: ${h.smoke_status || 'PENDING'}`
  })

  const failed = gates.some(g => g.status === 'FAIL')
  const warned = gates.some(g => g.status === 'WARN')
  return {
    overall: failed ? 'BLOCKED' : warned ? 'DEGRADED' : 'READY',
    environment: h.environment,
    gates,
    evidence_kind: 'offline',
    evaluated_at: new Date().toISOString()
  }
}

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const [healthRes, killRes, readinessRes] = await Promise.allSettled([
      api.platform.serpro.health({ environment: environment.value }),
      api.platform.serpro.killSwitch.status(),
      api.platform.serpro.readiness({ environment: environment.value })
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    if (healthRes.status === 'fulfilled') {
      health.value = healthRes.value.data
    } else {
      health.value = null
      loadError.value = apiErrorMessage(healthRes.reason, 'Falha ao carregar saúde global SERPRO.')
    }

    if (killRes.status === 'fulfilled') {
      kill.value = killRes.value.data
    } else if (health.value?.kill_switch) {
      kill.value = health.value.kill_switch
    }

    if (readinessRes.status === 'fulfilled') {
      readiness.value = readinessRes.value.data
    } else {
      readiness.value = deriveReadiness(health.value)
    }
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function toggleKill(active: boolean) {
  if (!killReason.value.trim()) {
    toast.add({ title: 'Informe o motivo auditável do kill switch.', color: 'warning' })
    return
  }
  killLoading.value = true
  try {
    const res = await api.platform.serpro.killSwitch.set({
      active,
      reason: killReason.value.trim()
    })
    kill.value = res.data
    toast.add({
      title: active ? 'Kill switch SERPRO global ligado.' : 'Kill switch SERPRO desligado.',
      color: 'warning'
    })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha no kill switch SERPRO.'), color: 'error' })
  } finally {
    killLoading.value = false
  }
}

function gateColor(status?: string): 'success' | 'warning' | 'error' | 'neutral' {
  switch (String(status || '').toUpperCase()) {
    case 'PASS': return 'success'
    case 'WARN': return 'warning'
    case 'FAIL': return 'error'
    default: return 'neutral'
  }
}

watch(environment, () => {
  void load()
})
watch(sessionEpoch, () => {
  health.value = null
  readiness.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <div data-testid="admin-serpro-readiness">
    <UPageCard
      title="Readiness e kill switch"
      description="Avaliação global sanitizada — sem tokens, XML ou IDs de vault. Não dispara chamada fiscal."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <div class="flex w-fit flex-wrap items-end gap-2 lg:ms-auto">
        <UFormField label="Ambiente">
          <USelect
            v-model="environment"
            :items="envItems"
            value-key="value"
            class="w-40"
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
      class="mb-4"
    />

    <div
      v-if="loading && !health"
      class="space-y-2"
      role="status"
    >
      <USkeleton class="h-4 w-1/2" />
      <USkeleton class="h-4 w-2/3" />
    </div>

    <div
      v-else
      class="flex flex-col gap-4 sm:gap-6"
    >
      <UPageCard
        variant="subtle"
        title="Estado geral"
      >
        <div class="flex flex-wrap items-center gap-3">
          <UBadge
            :color="gateColor(readiness?.overall === 'READY' ? 'PASS' : readiness?.overall === 'BLOCKED' ? 'FAIL' : 'WARN')"
            variant="subtle"
            size="lg"
            data-testid="admin-serpro-overall"
          >
            {{ readiness?.overall || 'UNKNOWN' }}
          </UBadge>
          <span class="text-sm text-muted">
            Ambiente {{ readiness?.environment || environment }}
            · evidência {{ readiness?.evidence_kind || 'offline' }}
          </span>
          <SerproProvenanceBadge
            v-if="health?.fake_clients"
            code="simulado"
          />
          <SerproProvenanceBadge
            v-else
            code="real"
          />
        </div>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Gates"
        description="Fail-closed: qualquer FAIL bloqueia egress produtivo."
      >
        <ul
          v-if="readiness?.gates?.length"
          class="divide-y divide-default"
        >
          <li
            v-for="gate in readiness.gates"
            :key="gate.code"
            class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm"
          >
            <div>
              <p class="font-medium text-highlighted">
                {{ gate.code }}
                <span class="text-xs font-normal text-muted">· {{ gate.scope || 'global' }}</span>
              </p>
              <p class="text-muted">
                {{ gate.message }}
              </p>
            </div>
            <UBadge
              :color="gateColor(gate.status)"
              variant="subtle"
            >
              {{ gate.status }}
            </UBadge>
          </li>
        </ul>
        <p
          v-else
          class="text-sm text-muted"
        >
          Nenhum gate disponível.
        </p>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Kill switch SERPRO"
        description="Bloqueia egress Integra Contador. Persistência no backend; não apaga contratos nem ledger."
        data-testid="admin-serpro-kill-switch"
      >
        <div class="flex flex-wrap items-center gap-3 text-sm">
          <UBadge
            :color="kill?.global?.active ? 'error' : 'success'"
            variant="subtle"
          >
            {{ kill?.global?.active ? 'GLOBAL ATIVO' : 'Global off' }}
          </UBadge>
          <span
            v-if="kill?.global?.source"
            class="text-muted"
          >
            Fonte: {{ kill.global.source }}
          </span>
        </div>
        <div class="mt-3 flex flex-wrap items-end gap-2">
          <UFormField
            label="Motivo"
            class="min-w-[16rem] flex-1"
          >
            <UInput
              v-model="killReason"
              class="w-full"
              placeholder="Motivo auditável…"
              autocomplete="off"
            />
          </UFormField>
          <UButton
            color="error"
            variant="soft"
            label="Ligar"
            :loading="killLoading"
            data-testid="serpro-kill-on"
            @click="toggleKill(true)"
          />
          <UButton
            variant="ghost"
            label="Desligar"
            :loading="killLoading"
            data-testid="serpro-kill-off"
            @click="toggleKill(false)"
          />
        </div>
      </UPageCard>

      <UPageCard
        v-if="health?.active_contract"
        variant="subtle"
        title="Contrato ativo (sanitizado)"
      >
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted">
              ID
            </dt>
            <dd class="font-medium">
              {{ health.active_contract.id }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Status
            </dt>
            <dd class="font-medium">
              {{ health.active_contract.status }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Contratante (mascarado)
            </dt>
            <dd class="font-medium">
              {{ health.active_contract.contractor_cnpj_masked || '—' }}
              <span
                v-if="health.active_contract.contractor_name"
                class="text-muted"
              > · {{ health.active_contract.contractor_name }}</span>
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Certificado
            </dt>
            <dd class="font-medium">
              até {{ formatDateTime(health.active_contract.cert_valid_to) }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Materiais (flags)
            </dt>
            <dd class="font-medium">
              PFX {{ health.active_contract.has_pfx ? 'sim' : 'não' }}
              · OAuth {{ health.active_contract.has_oauth ? 'sim' : 'não' }}
              · token cache {{ health.active_contract.has_cached_token ? 'sim' : 'não' }}
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Hint Consumer Key
            </dt>
            <dd class="font-mono text-xs">
              {{ health.active_contract.consumer_key_hint || '—' }}
            </dd>
          </div>
        </dl>
        <UAlert
          v-if="health.active_contract.credentials_exposed"
          class="mt-4"
          color="error"
          icon="i-lucide-shield-alert"
          title="Credencial marcada como exposta"
          description="Cutover e rotação obrigatórios antes de qualquer egress real."
        />
      </UPageCard>
    </div>
  </div>
</template>
