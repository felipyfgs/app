<script setup lang="ts">
/**
 * Card “XML NFC-e via SVRS” — arquétipo Settings (UPageCard + estados async).
 * Nunca renderiza HTML remoto, XML, PFX ou vault_object_id.
 * Lista sempre escopada ao client_id (nunca office-wide sem filtro).
 */
import type {
  OutboundCaptureProfile,
  SvrsNfceChannelSummary,
  SvrsNfceProfileSummary,
  SvrsNfceRecovery
} from '~/types/api'

const props = defineProps<{
  clientId: number
  profiles: OutboundCaptureProfile[]
  canManage: boolean
  canAdmin: boolean
}>()

const api = useApi()
const toast = useToast()

/** Sentinela USelect — Reka UI proíbe SelectItem com value "". */
const STATUS_ALL = 'all'

const loading = ref(true)
const error = ref<string | null>(null)
const channel = ref<SvrsNfceChannelSummary | null>(null)
const profileSummary = ref<SvrsNfceProfileSummary | null>(null)
const recoveries = ref<SvrsNfceRecovery[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const busyId = ref<number | null>(null)

const statusFilterItems = [
  { label: 'Todos', value: STATUS_ALL },
  { label: 'Elegível', value: 'ELIGIBLE' },
  { label: 'Na fila', value: 'QUEUED' },
  { label: 'Em recuperação', value: 'RUNNING' },
  { label: 'Retry', value: 'RETRY_SCHEDULED' },
  { label: 'Capturado', value: 'CAPTURED' },
  { label: 'Bloqueado', value: 'BLOCKED' },
  { label: 'Indisponível', value: 'NOT_AVAILABLE_VISIBLE' },
  { label: 'Fallback', value: 'RESOLVED_BY_OTHER_SOURCE' }
] as const
const statusFilter = ref<(typeof statusFilterItems)[number]['value']>(STATUS_ALL)
const page = ref(1)
const killReason = ref('')
const killBusy = ref(false)
const breakerReason = ref('')
const breakerBusy = ref(false)
const profilesReady = computed(() => props.profiles.length >= 0)

const profile65List = computed(() =>
  props.profiles.filter(p => p.model === '65')
)

const profile65 = computed(() =>
  profile65List.value.find(p => (p.uf || '').toUpperCase() === 'MA')
  || profile65List.value[0]
  || null
)

const reprocessable = (status?: string | null) =>
  ['ELIGIBLE', 'RETRY_SCHEDULED', 'NOT_AVAILABLE_VISIBLE', 'BLOCKED', 'QUEUED'].includes(status || '')

const statusColor = (status?: string | null): 'success' | 'warning' | 'error' | 'info' | 'neutral' => {
  switch (status) {
    case 'CAPTURED':
    case 'RESOLVED_BY_OTHER_SOURCE':
      return 'success'
    case 'QUEUED':
    case 'RUNNING':
    case 'ELIGIBLE':
      return 'info'
    case 'RETRY_SCHEDULED':
    case 'NOT_AVAILABLE_VISIBLE':
      return 'warning'
    case 'BLOCKED':
      return 'error'
    default:
      return 'neutral'
  }
}

const statusLabel = (status?: string | null): string => {
  const map: Record<string, string> = {
    ELIGIBLE: 'Elegível (XML pendente)',
    QUEUED: 'Na fila',
    RUNNING: 'Em recuperação',
    RETRY_SCHEDULED: 'Retry agendado',
    CAPTURED: 'Capturado',
    NOT_AVAILABLE_VISIBLE: 'Indisponível',
    BLOCKED: 'Bloqueado',
    RESOLVED_BY_OTHER_SOURCE: 'Fallback / outra fonte'
  }
  return status ? (map[status] || status) : '—'
}

async function load() {
  // Não carregar lista office-wide: exige clientId
  if (!props.clientId) return

  loading.value = true
  // Mantém dados anteriores em erro (não limpar channel/recoveries no início)
  const previousError = error.value
  error.value = null
  try {
    const [sum, list] = await Promise.all([
      api.outbound.svrsNfce.summary(),
      api.outbound.svrsNfce.recoveries({
        status: statusFilter.value && statusFilter.value !== STATUS_ALL
          ? statusFilter.value
          : undefined,
        client_id: props.clientId,
        profile_id: profile65.value?.id,
        page: page.value,
        per_page: 10
      })
    ])
    channel.value = sum.data
    recoveries.value = list.data || []
    meta.value = list.meta || meta.value

    if (profile65.value) {
      try {
        const ps = await api.outbound.svrsNfce.profileSummary(profile65.value.id)
        profileSummary.value = ps.data
      } catch {
        profileSummary.value = null
      }
    } else {
      profileSummary.value = null
    }
  } catch (caught) {
    error.value = apiErrorMessage(caught, 'Falha ao carregar canal SVRS.')
    if (!channel.value && previousError) {
      // keep previous
    }
  } finally {
    loading.value = false
  }
}

async function applyFilter() {
  page.value = 1
  await load()
}

async function goPage(p: number) {
  page.value = p
  await load()
}

async function retry(id: number) {
  if (!props.canManage) return
  busyId.value = id
  try {
    await api.outbound.svrsNfce.retry(id)
    toast.add({ title: 'Reprocessamento enfileirado', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({
      title: 'Não foi possível reprocessar',
      description: apiErrorMessage(caught, 'Use o fallback de upload se o breaker estiver aberto.'),
      color: 'error'
    })
  } finally {
    busyId.value = null
  }
}

async function toggleKill(active: boolean) {
  if (!props.canAdmin) return
  if (!killReason.value.trim()) {
    toast.add({ title: 'Informe o motivo', color: 'warning' })
    return
  }
  killBusy.value = true
  try {
    await api.outbound.svrsNfce.killSwitch({ active, reason: killReason.value.trim() })
    toast.add({ title: active ? 'Kill switch ativado' : 'Kill switch desativado', color: 'success' })
    killReason.value = ''
    await load()
  } catch (caught) {
    toast.add({ title: 'Falha no kill switch', description: apiErrorMessage(caught, 'Falha no kill switch.'), color: 'error' })
  } finally {
    killBusy.value = false
  }
}

async function resetBreaker() {
  if (!props.canAdmin) return
  if (!breakerReason.value.trim()) {
    toast.add({ title: 'Informe o motivo do reset', color: 'warning' })
    return
  }
  breakerBusy.value = true
  try {
    await api.outbound.svrsNfce.breakerReset({
      scope: 'global',
      reason: breakerReason.value.trim()
    })
    toast.add({ title: 'Breaker global resetado', color: 'success' })
    breakerReason.value = ''
    await load()
  } catch (caught) {
    toast.add({
      title: 'Falha ao resetar breaker',
      description: apiErrorMessage(caught, 'Tente novamente.'),
      color: 'error'
    })
  } finally {
    breakerBusy.value = false
  }
}

// Aguarda perfis do parent (evita 1ª carga office-wide) e reage a mudanças
watch(
  () => [props.clientId, props.profiles.map(p => p.id).join(',')] as const,
  () => { load() },
  { immediate: true }
)
</script>

<template>
  <div class="space-y-4" data-testid="svrs-nfce-panel">
    <UPageCard
      variant="naked"
      title="XML NFC-e via SVRS"
      description="Recuperação de nfeProc modelo 65 (MA) por chave descoberta e A1. Lista deste cliente; badges de canal são do escritório."
    />

    <UAlert
      color="neutral"
      variant="subtle"
      icon="i-lucide-shield"
      title="Sem RPA / sem HTML remoto"
      description="O portal SVRS é chamado só no backend com mTLS. Esta tela nunca renderiza HTML ou XML da SVRS."
    />

    <UAlert
      v-if="error"
      color="warning"
      variant="subtle"
      icon="i-lucide-wifi-off"
      title="Falha ao atualizar"
      :description="error"
      class="mb-2"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
    />

    <UPageCard variant="subtle">
      <div v-if="loading && !channel" class="flex items-center gap-2 text-muted text-sm">
        <UIcon name="i-lucide-loader-circle" class="animate-spin" />
        Carregando estado do canal…
      </div>

      <div v-else-if="channel" class="space-y-4">
        <p class="text-xs text-muted">
          Estado do canal (escritório inteiro) — backlog e breaker não são só deste cliente.
        </p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <p class="text-xs text-muted uppercase mb-1">Master</p>
            <UBadge :color="channel.retrieval_enabled ? 'success' : 'neutral'" variant="subtle">
              {{ channel.retrieval_enabled ? 'Habilitado' : 'Desligado' }}
            </UBadge>
          </div>
          <div>
            <p class="text-xs text-muted uppercase mb-1">Auto-queue</p>
            <UBadge :color="channel.auto_queue_enabled ? 'info' : 'neutral'" variant="subtle">
              {{ channel.auto_queue_enabled ? 'On' : 'Off' }}
            </UBadge>
          </div>
          <div>
            <p class="text-xs text-muted uppercase mb-1">Backlog (escritório)</p>
            <p class="font-semibold text-highlighted" data-testid="svrs-nfce-backlog">
              {{ channel.backlog }}
            </p>
          </div>
          <div>
            <p class="text-xs text-muted uppercase mb-1">Breaker</p>
            <UBadge
              :color="channel.breaker_global?.state === 'open' ? 'error' : channel.breaker_global?.state === 'half_open' ? 'warning' : 'success'"
              variant="subtle"
            >
              {{ channel.breaker_global?.state || 'closed' }}
            </UBadge>
          </div>
        </div>

        <div
          v-if="profileSummary"
          class="grid gap-2 sm:grid-cols-3 text-sm border-t border-default pt-3"
          data-testid="svrs-nfce-profile-summary"
        >
          <div>
            <span class="text-muted">Perfil 65</span>
            <p class="font-medium">
              #{{ profileSummary.profile_id }} · {{ profileSummary.allowlisted ? 'allowlisted' : 'fora da allowlist' }}
            </p>
          </div>
          <div>
            <span class="text-muted">Breaker da raiz</span>
            <p class="font-medium">
              {{ profileSummary.breaker_root?.state || 'closed' }}
            </p>
          </div>
          <div>
            <span class="text-muted">Última captura</span>
            <p class="font-medium font-mono text-xs">
              {{ profileSummary.last_captured?.access_key_masked || '—' }}
            </p>
          </div>
        </div>

        <UAlert
          v-if="channel.kill_switch?.active"
          color="error"
          variant="subtle"
          icon="i-lucide-octagon-x"
          title="Kill switch SVRS ativo"
          description="Nenhum GET/POST novo. Estado fiscal e tentativas são preservados. Use upload assistido."
        />

        <UAlert
          v-if="profilesReady && !profile65"
          color="neutral"
          variant="subtle"
          icon="i-lucide-info"
          title="Sem perfil NFC-e 65"
          description="Cadastre semente/perfil de saída modelo 65 em Captura de saídas para habilitar recovery por chave."
        />
      </div>
    </UPageCard>

    <UPageCard
      title="Recuperações deste cliente"
      description="Filtro server-side por client_id. Chaves mascaradas."
      variant="subtle"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end mb-4">
        <UFormField label="Status" class="min-w-48">
          <USelect
            v-model="statusFilter"
            :items="[...statusFilterItems]"
            data-testid="svrs-nfce-status-filter"
          />
        </UFormField>
        <UButton color="neutral" variant="subtle" label="Filtrar" @click="applyFilter" />
        <UButton color="neutral" variant="ghost" icon="i-lucide-refresh-cw" :loading="loading" @click="load" />
      </div>

      <UEmpty
        v-if="!loading && recoveries.length === 0"
        icon="i-lucide-inbox"
        title="Nenhuma recuperação"
        description="Quando houver chave descoberta elegível neste cliente, ela aparecerá aqui."
      />

      <ul v-else class="divide-y divide-default" data-testid="svrs-nfce-recovery-list">
        <li
          v-for="row in recoveries"
          :key="row.id"
          class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <UBadge :color="statusColor(row.recovery_status)" variant="subtle">
                {{ statusLabel(row.recovery_status) }}
              </UBadge>
              <span class="font-mono text-sm text-muted">
                {{ row.access_key_masked || 'chave mascarada' }}
              </span>
              <span v-if="row.attempt_count != null" class="text-xs text-muted">
                tent. {{ row.attempt_count }}
              </span>
            </div>
            <p v-if="row.failure_label" class="text-sm text-muted mt-1">
              {{ row.failure_label }}
            </p>
            <p v-if="row.next_attempt_at" class="text-xs text-muted mt-0.5">
              Próximo retry: {{ row.next_attempt_at }}
            </p>
          </div>
          <div class="flex gap-2 shrink-0">
            <UButton
              v-if="canManage && reprocessable(row.recovery_status)"
              size="sm"
              color="primary"
              variant="soft"
              label="Reprocessar"
              :loading="busyId === row.id"
              data-testid="svrs-nfce-retry"
              @click="retry(row.id)"
            />
            <UButton
              v-if="canManage && row.recovery_status !== 'CAPTURED'"
              size="sm"
              color="neutral"
              variant="ghost"
              label="Fallback upload"
              :to="`/clients/${clientId}/saidas`"
            />
          </div>
        </li>
      </ul>

      <div v-if="meta.total > 0" class="flex items-center justify-between mt-3 gap-2">
        <p class="text-xs text-muted">
          {{ meta.total }} item(ns) · página {{ meta.current_page }}/{{ meta.last_page }}
        </p>
        <div class="flex gap-1">
          <UButton
            size="xs"
            color="neutral"
            variant="ghost"
            label="Anterior"
            :disabled="meta.current_page <= 1"
            @click="goPage(meta.current_page - 1)"
          />
          <UButton
            size="xs"
            color="neutral"
            variant="ghost"
            label="Próxima"
            :disabled="meta.current_page >= meta.last_page"
            @click="goPage(meta.current_page + 1)"
          />
        </div>
      </div>
    </UPageCard>

    <UPageCard
      v-if="canAdmin"
      title="Controles ADMIN"
      description="Kill switch e breaker exigem 2FA. Motivo obrigatório."
      variant="subtle"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end mb-4">
        <UFormField label="Motivo kill switch" class="flex-1">
          <UInput v-model="killReason" placeholder="Ex.: drill operacional" data-testid="svrs-nfce-kill-reason" />
        </UFormField>
        <UButton
          color="error"
          variant="soft"
          label="Ativar kill switch"
          :loading="killBusy"
          data-testid="svrs-nfce-kill-on"
          @click="toggleKill(true)"
        />
        <UButton
          color="neutral"
          variant="subtle"
          label="Desativar"
          :loading="killBusy"
          data-testid="svrs-nfce-kill-off"
          @click="toggleKill(false)"
        />
      </div>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <UFormField label="Motivo reset breaker" class="flex-1">
          <UInput v-model="breakerReason" placeholder="Ex.: smoke OK, reabrir canal" data-testid="svrs-nfce-breaker-reason" />
        </UFormField>
        <UButton
          color="warning"
          variant="soft"
          label="Reset breaker global"
          :loading="breakerBusy"
          data-testid="svrs-nfce-breaker-reset"
          @click="resetBreaker"
        />
      </div>
    </UPageCard>
  </div>
</template>
