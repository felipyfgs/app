<script setup lang="ts">
/**
 * Rollout / canário / smoke status (PLATFORM_ADMIN).
 */
import type { SerproGlobalHealth, SerproRolloutState } from '~/types/api'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rollout = ref<SerproRolloutState | null>(null)
const health = ref<SerproGlobalHealth | null>(null)
const rolloutMissing = ref(false)
const notes = ref('')
const saving = ref(false)

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
    const [rollRes, healthRes] = await Promise.allSettled([
      api.platform.serpro.rollout.show(),
      api.platform.serpro.health()
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    if (healthRes.status === 'fulfilled') {
      health.value = healthRes.value.data
    } else {
      health.value = null
    }

    if (rollRes.status === 'fulfilled') {
      rollout.value = rollRes.value.data
      rolloutMissing.value = false
    } else {
      rolloutMissing.value = true
      rollout.value = deriveFromHealth(health.value)
      if (!health.value) {
        loadError.value = apiErrorMessage(
          healthRes.status === 'rejected' ? healthRes.reason : rollRes.status === 'rejected' ? rollRes.reason : null,
          'Falha ao carregar estado de rollout.'
        )
      }
    }
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function saveNotes() {
  if (rolloutMissing.value) {
    toast.add({
      title: 'Endpoint de rollout ainda não disponível; use readiness/kill switch.',
      color: 'warning'
    })
    return
  }
  saving.value = true
  try {
    const res = await api.platform.serpro.rollout.update({ notes: notes.value })
    rollout.value = res.data
    toast.add({ title: 'Rollout atualizado', color: 'success' })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao atualizar rollout.'), color: 'error' })
  } finally {
    saving.value = false
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
      v-if="rolloutMissing && rollout"
      color="info"
      icon="i-lucide-info"
      title="Rollout derivado do health"
      description="A rota /platform/serpro/rollout ainda não responde; exibindo snapshot sanitizado do readiness."
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
        variant="subtle"
        title="Notas operacionais"
      >
        <UTextarea
          v-model="notes"
          class="w-full"
          :rows="3"
          placeholder="Registro operacional (sem segredos, sem PII de cliente)…"
        />
        <div class="mt-3 flex justify-end">
          <UButton
            color="primary"
            label="Salvar notas"
            :loading="saving"
            :disabled="rolloutMissing"
            @click="saveNotes"
          />
        </div>
      </UPageCard>
    </div>
  </div>
</template>
