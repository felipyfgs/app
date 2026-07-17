<script setup lang="ts">
/**
 * Rollout / canário / smoke status (PLATFORM_ADMIN).
 */
import type { SerproGlobalHealth, SerproRolloutState } from '~/types/api'

const api = useApi()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rollout = ref<SerproRolloutState | null>(null)
const health = ref<SerproGlobalHealth | null>(null)
const pendingApprovals = ref<Array<Record<string, unknown>>>([])

function deriveFromHealth(h: SerproGlobalHealth | null): SerproRolloutState {
  return {
    smoke_status: h?.smoke_status || 'PENDING_OPS',
    kill_switch: h?.kill_switch,
    fake_clients: h?.fake_clients,
    free_smoke_ok: h?.smoke_status === 'FREE_SMOKE_OK',
    canary_enabled: false,
    notes: null
  }
}

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    // API real: GET /serpro/health + GET /serpro/rollouts (lista de aprovações).
    // Não existe GET /serpro/rollout singular — evita 404 no console.
    const [healthRes, rolloutsRes] = await Promise.allSettled([
      api.platform.serpro.health(),
      api.platform.serpro.rollouts.list()
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    if (healthRes.status === 'fulfilled') {
      health.value = healthRes.value.data
      rollout.value = deriveFromHealth(healthRes.value.data)
    } else {
      health.value = null
      rollout.value = deriveFromHealth(null)
      loadError.value = apiErrorMessage(healthRes.reason, 'Falha ao carregar health SERPRO.')
    }

    if (rolloutsRes.status === 'fulfilled') {
      pendingApprovals.value = Array.isArray(rolloutsRes.value.data) ? rolloutsRes.value.data : []
    } else {
      pendingApprovals.value = []
    }
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

watch(sessionEpoch, () => {
  rollout.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <div data-testid="admin-serpro-rollout">
    <UPageCard
      title="Rollout e smoke"
      description="Estado de go-live controlado. Drivers reais permanecem OFF até aprovação operacional."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <UButton
        class="lg:ms-auto"
        color="neutral"
        variant="ghost"
        icon="i-lucide-refresh-cw"
        label="Atualizar"
        :loading="loading"
        @click="load"
      />
    </UPageCard>

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
      class="mb-4"
    />

    <UAlert
      color="info"
      icon="i-lucide-info"
      title="Snapshot de smoke e kill switch"
      description="Health de /platform/serpro/health. Ações globais (kill-off, cutover, contrato) usam confirmação do proprietário; canário faturável permanece dual (Proprietário + Office ADMIN)."
      class="mb-4"
    />

    <div class="flex flex-col gap-4 sm:gap-6">
      <UPageCard
        variant="subtle"
        title="Estado"
      >
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted">
              Smoke
            </dt>
            <dd class="font-medium">
              <UBadge
                :color="rollout?.free_smoke_ok || rollout?.smoke_status === 'FREE_SMOKE_OK' ? 'success' : 'warning'"
                variant="subtle"
              >
                {{ rollout?.smoke_status || '—' }}
              </UBadge>
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Fake clients
            </dt>
            <dd class="font-medium">
              <SerproProvenanceBadge :code="rollout?.fake_clients ? 'simulado' : 'real'" />
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Kill switch
            </dt>
            <dd class="font-medium">
              <UBadge
                :color="rollout?.kill_switch?.global?.active ? 'error' : 'success'"
                variant="subtle"
              >
                {{ rollout?.kill_switch?.global?.active ? 'ATIVO' : 'off' }}
              </UBadge>
            </dd>
          </div>
          <div>
            <dt class="text-muted">
              Canário faturável
            </dt>
            <dd class="font-medium">
              {{ rollout?.canary_enabled ? 'Habilitado (requer aprovação)' : 'Desligado' }}
            </dd>
          </div>
        </dl>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Política desta change"
      >
        <ul class="list-disc space-y-1 ps-4 text-sm text-muted">
          <li>Nenhum driver real é ligado apenas porque o código foi implantado.</li>
          <li>Smoke gratuito: TLS, OAuth, Termo/Apoiar, /Monitorar nos limites oficiais.</li>
          <li>Consultar/Emitir/Declarar permanecem bloqueados até gate operacional separado.</li>
          <li>Kill switch e budgets positivos são pré-requisitos de qualquer canário.</li>
        </ul>
        <div class="mt-4 flex flex-wrap gap-2">
          <UButton
            to="/admin/serpro"
            color="neutral"
            variant="soft"
            label="Ver readiness"
            icon="i-lucide-heart-pulse"
          />
          <UButton
            to="/admin/serpro/usage"
            color="neutral"
            variant="ghost"
            label="Orçamento"
            icon="i-lucide-wallet"
          />
        </div>
      </UPageCard>

      <UPageCard
        v-if="pendingApprovals.length"
        variant="subtle"
        title="Aprovações de rollout"
        description="Metadados sanitizados: política, status e frase esperada (sem segredos)."
      >
        <ul class="divide-y divide-default text-sm">
          <li
            v-for="(item, idx) in pendingApprovals.slice(0, 10)"
            :key="String(item.id ?? idx)"
            class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
            data-testid="serpro-rollout-approval-row"
          >
            <span class="font-medium text-highlighted">
              {{ item.action || '—' }}
              <UBadge
                v-if="item.approval_policy"
                class="ms-1"
                size="sm"
                variant="subtle"
                :color="item.approval_policy === 'OWNER_CONFIRMATION' ? 'warning' : 'info'"
              >
                {{ item.approval_policy }}
              </UBadge>
              <span class="text-muted font-normal">
                · {{ item.status || '—' }}
              </span>
            </span>
            <span class="text-xs text-muted">
              #{{ item.id ?? '—' }}
            </span>
          </li>
        </ul>
      </UPageCard>
    </div>
  </div>
</template>
